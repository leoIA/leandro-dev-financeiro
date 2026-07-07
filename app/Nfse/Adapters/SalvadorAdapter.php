<?php
declare(strict_types=1);

/**
 * @file    SalvadorAdapter.php
 * @package App\Nfse\Adapters
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Adapter NFSe para a prefeitura de Salvador (XML próprio).
 *
 * Município atendido:
 *   - Salvador (IBGE 2927408)
 *
 * Características de Salvador:
 *   - XML próprio (ABRASF + extensões <InformacoesAdicionais>, <CodigoObra>, <Art>)
 *     — gerado pelo XmlBuilder com padrao SALVADOR_PROPRIO
 *   - SOAPAction: "gerarNfse"
 *   - Namespace do envelope: http://nfse.salvador.ba.gov.br
 *   - Envelope SOAP usa <sal:gerarNfseRequest>
 *   - Retorno: <gerarNfseResponse> com <Nfse><Numero>, <CodigoVerificacao>,
 *     <Protocolo>
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

class SalvadorAdapter implements NfseAdapterInterface
{
    use HttpTrait;

    /** @var string SOAPAction de emissão Salvador. */
    private const SOAP_ACTION_EMITIR = 'gerarNfse';

    /** @var string SOAPAction de consulta Salvador. */
    private const SOAP_ACTION_CONSULTAR = 'consultarNfse';

    /** @var string SOAPAction de cancelamento Salvador. */
    private const SOAP_ACTION_CANCELAR = 'cancelarNfse';

    /** @var string Namespace SOAP de Salvador. */
    private const NAMESPACE_NS = 'http://nfse.salvador.ba.gov.br';

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
        // 1) Constrói XML Salvador próprio via XmlBuilder (retorna SALVADOR_PROPRIO).
        try {
            $built = (new XmlBuilder())->build($nfse);
            $xml = $built['xml'];
        } catch (RuntimeException $e) {
            $this->logError('SalvadorAdapter::emitir(build)', $e->getMessage());
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
            $this->logError('SalvadorAdapter::emitir(sign)', $e->getMessage());
            return $this->fail($e->getMessage(), $xml, null);
        }

        // 3) Wrap em envelope SOAP (Salvador usa sal:gerarNfseRequest).
        $body = $this->stripXmlDeclaration($signedXml);
        $soapBody = '<sal:gerarNfseRequest xmlns:sal="' . self::NAMESPACE_NS . '">'
            . $body
            . '</sal:gerarNfseRequest>';
        $envelope = $this->wrapSoap($soapBody, self::NAMESPACE_NS);

        // 4) Resolve endpoint e envia.
        $ibge = (string) ($nfse['municipio_ibge'] ?? $nfse['tomador_codigo_municipio'] ?? '');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            $msg = "Endpoint não encontrado para IBGE {$ibge}";
            $this->logError('SalvadorAdapter::emitir(endpoint)', $msg);
            return $this->fail($msg, $envelope, null);
        }

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_EMITIR, $envelope);

        // 5) Trata erros de transporte.
        if ($resp['error'] !== null) {
            $this->logError('SalvadorAdapter::emitir(http)', $resp['error']);
            return $this->fail($resp['error'], $envelope, $resp['body']);
        }

        $xmlRetorno = $resp['body'];

        // 6) Procura mensagens de erro no retorno Salvador.
        $erro = $this->buildErrorMessage($xmlRetorno);
        if ($erro !== null) {
            return $this->fail($erro, $envelope, $xmlRetorno);
        }

        // 7) Extrai número, código de verificação e protocolo do retorno Salvador.
        //    Estrutura esperada: <gerarNfseResponse><Nfse><Numero>...
        $numero  = $this->extractBetween($xmlRetorno, 'Numero')
            ?? $this->extractBetween($xmlRetorno, 'NumeroNfse');
        $codigo  = $this->extractBetween($xmlRetorno, 'CodigoVerificacao');
        $protoco = $this->extractBetween($xmlRetorno, 'Protocolo');

        if ($numero === null) {
            $msg = 'Retorno sem <Numero> da NFSe — parsing falhou';
            $this->logError('SalvadorAdapter::emitir(parse)', $msg . ' | body=' . substr($xmlRetorno, 0, 500));
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
        $ibge = (string) ($this->cert['municipio_ibge'] ?? '2927408');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            return ['sucesso' => false, 'status' => null, 'erro' => "Endpoint não encontrado para IBGE {$ibge}", 'xml_retorno' => null];
        }

        $cnpj = $this->onlyDigits((string) ($this->cert['cnpj_titular'] ?? ''));
        $im   = $this->onlyDigits((string) ($this->cert['im'] ?? ''));
        $body = '<sal:consultarNfseRequest xmlns:sal="' . self::NAMESPACE_NS . '">'
            . '<sal:Protocolo>' . htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</sal:Protocolo>'
            . '<sal:Cnpj>' . $cnpj . '</sal:Cnpj>'
            . '<sal:InscricaoMunicipal>' . $im . '</sal:InscricaoMunicipal>'
            . '</sal:consultarNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CONSULTAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('SalvadorAdapter::consultar', $resp['error']);
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

        $ibge = (string) ($this->cert['municipio_ibge'] ?? '2927408');
        $endpoint = MunicipiosCatalog::getEndpoint($ibge, $this->ambiente);
        if ($endpoint === null) {
            return ['sucesso' => false, 'erro' => "Endpoint não encontrado para IBGE {$ibge}", 'xml_retorno' => null];
        }

        $motivoEsc  = htmlspecialchars($motivo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $protocoEsc = htmlspecialchars($protocolo, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $body = '<sal:cancelarNfseRequest xmlns:sal="' . self::NAMESPACE_NS . '">'
            . '<sal:Pedido>'
            . '<sal:Protocolo>' . $protocoEsc . '</sal:Protocolo>'
            . '<sal:Motivo>' . $motivoEsc . '</sal:Motivo>'
            . '</sal:Pedido>'
            . '</sal:cancelarNfseRequest>';
        $envelope = $this->wrapSoap($body, self::NAMESPACE_NS);

        $resp = $this->sendSoap($endpoint, self::SOAP_ACTION_CANCELAR, $envelope);
        if ($resp['error'] !== null) {
            $this->logError('SalvadorAdapter::cancelar', $resp['error']);
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
