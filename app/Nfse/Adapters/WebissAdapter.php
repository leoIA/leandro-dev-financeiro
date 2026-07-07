<?php
declare(strict_types=1);

/**
 * @file    WebissAdapter.php
 * @package App\Nfse\Adapters
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Adapter NFSe para prefeituras WebISS (padrão ABRASF v2.03).
 *
 * Municípios atendidos:
 *   - Feira de Santana  (IBGE 2910800)
 *   - Camaçari          (IBGE 2905701)
 *   - Vitória da Conq.  (IBGE 2933307)
 *   - Juazeiro          (IBGE 2918407)
 *   - Lauro de Freitas  (IBGE 2919207)
 *
 * Características do WebISS:
 *   - XML ABRASF v2.03 (namespace http://www.abrasf.org.br/nfse.xsd)
 *   - SOAPAction: "http://nfse.abrasf.org.br/EmitirNfse"
 *   - Endpoint SOAP: variável por município (vindo de MunicipiosCatalog)
 *   - Envelope SOAP wrap com <nfse:GerarNfseRequest xmlns:nfse="http://nfse.abrasf.org.br">
 *   - Retorno: <GerarNfseResposta> ou <EnviarLoteRpsResposta>
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

class WebissAdapter implements NfseAdapterInterface
{
    use HttpTrait;

    /** @var string SOAPAction de emissão WebISS. */
    private const SOAP_ACTION_EMITIR = 'http://nfse.abrasf.org.br/EmitirNfse';

    /** @var string SOAPAction de consulta WebISS. */
    private const SOAP_ACTION_CONSULTAR = 'http://nfse.abrasf.org.br/ConsultarNfse';

    /** @var string SOAPAction de cancelamento WebISS. */
    private const SOAP_ACTION_CANCELAR = 'http://nfse.abrasf.org.br/CancelarNfse';

    /** @var string Namespace do envelope SOAP do WebISS. */
    private const NAMESPACE_NS = 'http://nfse.abrasf.org.br';

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
        // 1) Constrói XML ABRASF via XmlBuilder.
        try {
            $built = (new XmlBuilder())->build($nfse);
            $xml = $built['xml'];
        } catch (RuntimeException $e) {
            $this->logError('WebissAdapter::emitir(build)', $e->getMessage());
            return $this->fail($e->getMessage(), null, null);
        }

        // 2) Assina XML apontando para o elemento <InfRps Id="rpsN">.
        $numeroRps = (string) ($nfse['numero_rps'] ?? '');
        $signedXml = '';
        try {
            $signedXml = (new Signer(
                (string) ($this->cert['arquivo_path'] ?? ''),
                (string) ($this->cert['senha'] ?? '')
            ))->signXml($xml, '#rps' . $numeroRps);
        } catch (RuntimeException $e) {
            $this->logError('WebissAdapter::emitir(sign)', $e->getMessage());
            return $this->fail($e->getMessage(), $xml, null);
        }

        // 3) Wrap em envelope SOAP (sem declaração XML no corpo).
        $body = $this->stripXmlDeclaration($signedXml);
        $soapBody = '<nfse:GerarNfseRequest xmlns:nfse="' . self::NAMESPACE_NS . '">'
            . $body
            . '</nfse:GerarNfseRequest>';
        $envelope = $this->wrapSoap($soapBody, self::NAMESPACE_NS);

        // 4) Resolve endpoint e envia.
        $ibge = (string) ($nfse['municipio_ibge'] ?? $nfse['tomador_codigo_municipio'] ?? '');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            $msg = "Endpoint não encontrado para IBGE {$ibge}";
            $this->logError('WebissAdapter::emitir(endpoint)', $msg);
            return $this->fail($msg, $envelope, null);
        }

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_EMITIR, $envelope);

        // 5) Trata erros de transporte.
        if ($resp['error'] !== null) {
            $this->logError('WebissAdapter::emitir(http)', $resp['error']);
            return $this->fail($resp['error'], $envelope, $resp['body']);
        }

        $xmlRetorno = $resp['body'];

        // 6) Procura mensagens de erro no retorno ABRASF.
        $erro = $this->buildErrorMessage($xmlRetorno);
        if ($erro !== null) {
            return $this->fail($erro, $envelope, $xmlRetorno);
        }

        // 7) Extrai número, código de verificação e protocolo do retorno.
        $numero  = $this->extractBetween($xmlRetorno, 'Numero')
            ?? $this->extractBetween($xmlRetorno, 'NumeroNfse');
        $codigo  = $this->extractBetween($xmlRetorno, 'CodigoVerificacao');
        $protoco = $this->extractBetween($xmlRetorno, 'Protocolo');

        if ($numero === null) {
            $msg = 'Retorno sem <Numero> da NFSe — parsing falhou';
            $this->logError('WebissAdapter::emitir(parse)', $msg . ' | body=' . substr($xmlRetorno, 0, 500));
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
        $body = '<nfse:ConsultarSituacaoNfseRequest xmlns:nfse="' . self::NAMESPACE_NS . '">'
            . '<nfse:Protocolo>' . htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</nfse:Protocolo>'
            . '<nfse:Cnpj>' . $cnpj . '</nfse:Cnpj>'
            . '<nfse:InscricaoMunicipal>' . $im . '</nfse:InscricaoMunicipal>'
            . '</nfse:ConsultarSituacaoNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CONSULTAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('WebissAdapter::consultar', $resp['error']);
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

        $motivoEsc = htmlspecialchars($motivo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $protocoEsc = htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '<nfse:CancelarNfseRequest xmlns:nfse="' . self::NAMESPACE_NS . '">'
            . '<nfse:Pedido>'
            . '<nfse:Protocolo>' . $protocoEsc . '</nfse:Protocolo>'
            . '<nfse:Motivo>' . $motivoEsc . '</nfse:Motivo>'
            . '</nfse:Pedido>'
            . '</nfse:CancelarNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CANCELAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('WebissAdapter::cancelar', $resp['error']);
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
