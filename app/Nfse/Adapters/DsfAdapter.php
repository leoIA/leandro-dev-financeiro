<?php
declare(strict_types=1);

/**
 * @file    DsfAdapter.php
 * @package App\Nfse\Adapters
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Adapter NFSe para prefeituras DSF Sistemas (XML próprio).
 *
 * Município atendido:
 *   - Teixeira de Freitas (IBGE 2917900)
 *
 * Características do DSF:
 *   - XML próprio (wrapper <EnviarLoteRpsEnvio> com <Lote Id="loteN">) — gerado pelo XmlBuilder
 *   - SOAPAction: "GerarNfse"
 *   - Namespace do envelope: http://dsfnet.com.br
 *   - Envelope SOAP usa <dsf:EnviarLoteRpsEnvio>
 *   - Retorno: <EnviarLoteRpsResposta> com <ListaNfse><CompNfse><Nfse><Numero>,
 *     <CodigoVerificacao>, <Protocolo>
 *
 * @see    App\Nfse\Services\XmlBuilder
 * @see    App\Nfse\Services\Signer
 * @see    App\Nfse\Data\MunicipiosCatalog
 */

namespace App\Nfse\Adapters;

use App\Nfse\Data\MunicipiosCatalog;
use App\Nfse\Services\Signer;
use App\Nfse\Services\XmlBuilder;
use RuntimeException;

class DsfAdapter implements NfseAdapterInterface
{
    use HttpTrait;

    /** @var string SOAPAction de emissão DSF. */
    private const SOAP_ACTION_EMITIR = 'GerarNfse';

    /** @var string SOAPAction de consulta DSF. */
    private const SOAP_ACTION_CONSULTAR = 'ConsultarNfse';

    /** @var string SOAPAction de cancelamento DSF. */
    private const SOAP_ACTION_CANCELAR = 'CancelarNfse';

    /** @var string Namespace SOAP do DSF. */
    private const NAMESPACE_NS = 'http://dsfnet.com.br';

    /**
     * @param array<string,mixed> $certificado Registro da tabela certificados (com arquivo_path e senha).
     * @param string              $ambiente    'HOMOLOGACAO' ou 'PRODUCAO'.
     */
    public function __construct(array $certificado, string $ambiente)
    {
        $this->cert    = $certificado;
        $this->ambiente = strtoupper($ambiente);
    }

    /**
     * {@inheritDoc}
     */
    public function emitir(array $nfse): array
    {
        // 1) Constrói XML DSF próprio via XmlBuilder (retorna DSF_PROPRIO).
        try {
            $built = (new XmlBuilder())->build($nfse);
            $xml = $built['xml'];
        } catch (RuntimeException $e) {
            $this->logError('DsfAdapter::emitir(build)', $e->getMessage());
            return $this->fail($e->getMessage(), null, null);
        }

        // 2) Assina XML apontando para <InfRps Id="rpsN">.
        $numeroRps = (string) ($nfse['numero_rps'] ?? '');
        $signedXml = '';
        try {
            $signedXml = (new Signer(
                (string) ($this->cert['arquivo_path'] ?? ''),
                (string) ($this->cert['senha'] ?? '')
            ))->signXml($xml, '#rps' . $numeroRps);
        } catch (RuntimeException $e) {
            $this->logError('DsfAdapter::emitir(sign)', $e->getMessage());
            return $this->fail($e->getMessage(), $xml, null);
        }

        // 3) Wrap em envelope SOAP (DSF usa dsf:EnviarLoteRpsEnvio).
        $body = $this->stripXmlDeclaration($signedXml);
        $soapBody = '<dsf:EnviarLoteRpsEnvio xmlns:dsf="' . self::NAMESPACE_NS . '">'
            . $body
            . '</dsf:EnviarLoteRpsEnvio>';
        $envelope = $this->wrapSoap($soapBody, self::NAMESPACE_NS);

        // 4) Resolve endpoint e envia.
        $ibge = (string) ($nfse['municipio_ibge'] ?? $nfse['tomador_codigo_municipio'] ?? '');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            $msg = "Endpoint não encontrado para IBGE {$ibge}";
            $this->logError('DsfAdapter::emitir(endpoint)', $msg);
            return $this->fail($msg, $envelope, null);
        }

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_EMITIR, $envelope);

        // 5) Trata erros de transporte.
        if ($resp['error'] !== null) {
            $this->logError('DsfAdapter::emitir(http)', $resp['error']);
            return $this->fail($resp['error'], $envelope, $resp['body']);
        }

        $xmlRetorno = $resp['body'];

        // 6) Procura mensagens de erro no retorno DSF.
        $erro = $this->buildErrorMessage($xmlRetorno);
        if ($erro !== null) {
            return $this->fail($erro, $envelope, $xmlRetorno);
        }

        // 7) Extrai número, código de verificação e protocolo do retorno DSF.
        //    Estrutura esperada: <EnviarLoteRpsResposta><ListaNfse><CompNfse><Nfse>...
        $numero  = $this->extractBetween($xmlRetorno, 'Numero')
            ?? $this->extractBetween($xmlRetorno, 'NumeroNfse');
        $codigo  = $this->extractBetween($xmlRetorno, 'CodigoVerificacao');
        $protoco = $this->extractBetween($xmlRetorno, 'Protocolo');

        if ($numero === null) {
            $msg = 'Retorno sem <Numero> da NFSe — parsing falhou';
            $this->logError('DsfAdapter::emitir(parse)', $msg . ' | body=' . substr($xmlRetorno, 0, 500));
            return $this->fail($msg, $envelope, $xmlRetorno);
        }

        return [
            'sucesso'            => true,
            'numero'             => $numero,
            'codigo_verificacao' => $codigo,
            'protocolo'          => $protoco,
            'erro'               => null,
            'xml_envio'          => $envelope,
            'xml_retorno'        => $xmlRetorno,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function consultar(string $protocolo): array
    {
        $ibge = (string) ($this->cert['municipio_ibge'] ?? '');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            return ['sucesso' => false, 'status' => null, 'erro' => "Endpoint não encontrado para IBGE {$ibge}", 'xml_retorno' => null];
        }

        $cnpj = $this->onlyDigits((string) ($this->cert['cnpj_titular'] ?? ''));
        $im   = $this->onlyDigits((string) ($this->cert['im'] ?? ''));
        $body = '<dsf:ConsultarNfseRequest xmlns:dsf="' . self::NAMESPACE_NS . '">'
            . '<dsf:Protocolo>' . htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</dsf:Protocolo>'
            . '<dsf:Cnpj>' . $cnpj . '</dsf:Cnpj>'
            . '<dsf:InscricaoMunicipal>' . $im . '</dsf:InscricaoMunicipal>'
            . '</dsf:ConsultarNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CONSULTAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('DsfAdapter::consultar', $resp['error']);
            return ['sucesso' => false, 'status' => null, 'erro' => $resp['error'], 'xml_retorno' => $resp['body']];
        }

        $xmlRetorno = $resp['body'];
        $erro = $this->buildErrorMessage($xmlRetorno);
        if ($erro !== null) {
            return ['sucesso' => false, 'status' => null, 'erro' => $erro, 'xml_retorno' => $xmlRetorno];
        }

        $situacao = $this->extractBetween($xmlRetorno, 'Situacao')
            ?? $this->extractBetween($xmlRetorno, 'Status');

        return [
            'sucesso'     => true,
            'status'      => $situacao,
            'erro'        => null,
            'xml_retorno' => $xmlRetorno,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function cancelar(string $protocolo, string $motivo): array
    {
        if (mb_strlen(trim($motivo)) < 15) {
            return ['sucesso' => false, 'erro' => 'Motivo do cancelamento deve ter no mínimo 15 caracteres.', 'xml_retorno' => null];
        }

        $ibge = (string) ($this->cert['municipio_ibge'] ?? '');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            return ['sucesso' => false, 'erro' => "Endpoint não encontrado para IBGE {$ibge}", 'xml_retorno' => null];
        }

        $motivoEsc  = htmlspecialchars($motivo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $protocoEsc = htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '<dsf:CancelarNfseRequest xmlns:dsf="' . self::NAMESPACE_NS . '">'
            . '<dsf:Pedido>'
            . '<dsf:Protocolo>' . $protocoEsc . '</dsf:Protocolo>'
            . '<dsf:Motivo>' . $motivoEsc . '</dsf:Motivo>'
            . '</dsf:Pedido>'
            . '</dsf:CancelarNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CANCELAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('DsfAdapter::cancelar', $resp['error']);
            return ['sucesso' => false, 'erro' => $resp['error'], 'xml_retorno' => $resp['body']];
        }

        $xmlRetorno = $resp['body'];
        $erro = $this->buildErrorMessage($xmlRetorno);
        if ($erro !== null) {
            return ['sucesso' => false, 'erro' => $erro, 'xml_retorno' => $xmlRetorno];
        }

        $sucesso = $this->extractBetween($xmlRetorno, 'Sucesso');
        if ($sucesso !== null && strtoupper($sucesso) === 'FALSE') {
            return ['sucesso' => false, 'erro' => 'Cancelamento recusado pela prefeitura.', 'xml_retorno' => $xmlRetorno];
        }

        return [
            'sucesso'     => true,
            'erro'        => null,
            'xml_retorno' => $xmlRetorno,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function gerarDanfse(array $nfse): string
    {
        return $this->renderDanfseHtml($nfse);
    }

    /**
     * Monta retorno de falha padronizado.
     *
     * @param  string      $erro    Mensagem de erro.
     * @param  string|null $envio   XML de envio (ou null).
     * @param  string|null $retorno XML de retorno (ou null).
     * @return array{
     *   sucesso: bool, numero: ?string, codigo_verificacao: ?string,
     *   protocolo: ?string, erro: ?string, xml_envio: ?string, xml_retorno: ?string
     * }
     */
    private function fail(string $erro, ?string $envio, ?string $retorno): array
    {
        return [
            'sucesso'            => false,
            'numero'             => null,
            'codigo_verificacao' => null,
            'protocolo'          => null,
            'erro'               => $erro,
            'xml_envio'          => $envio,
            'xml_retorno'        => $retorno,
        ];
    }

    /**
     * Remove pontuação de CPF/CNPJ/IM/CEP.
     *
     * @param  string $s String com pontuação.
     * @return string    Apenas dígitos.
     */
    private function onlyDigits(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }
}
