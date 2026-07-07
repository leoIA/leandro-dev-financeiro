<?php
declare(strict_types=1);

/**
 * @file    HttpTrait.php
 * @package App\Nfse\Adapters
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Trait compartilhado pelos adapters de provedor NFSe.
 *
 * Reúne utilidades comuns a WebISS, Betha, DSF e Salvador:
 *   - sendSoap(): POST SOAP sobre cURL (timeout 60s, SSL_VERIFYPEER em PRODUCAO)
 *   - wrapSoap(): monta envelope SOAP 1.1 com namespace arbitrário
 *   - parseXmlResponse(): carrega SimpleXMLElement + registra namespace ABRASF p/ XPath
 *   - extractBetween(): extrai primeira ocorrência de uma tag por regex
 *   - extractAllBetween(): extrai todas as ocorrências (usado em ListaMensagemRetorno)
 *   - renderDanfseHtml(): gera HTML print-friendly do DANFSE (Bootstrap 5 inline)
 *   - logError(): append em /storage/logs/error_YYYYMMDD.log via error_log()
 *
 * @see    App\Nfse\Adapters\*Adapter
 */

namespace App\Nfse\Adapters;

use RuntimeException;
use SimpleXMLElement;

trait HttpTrait
{
    /** @var array<string,mixed> Registro do certificado (com arquivo_path e senha). */
    protected array $cert;

    /** @var string Ambiente atual: 'HOMOLOGACAO' ou 'PRODUCAO'. */
    protected string $ambiente;

    /**
     * Envia requisição SOAP 1.1 via cURL.
     *
     * @param  string $endpoint   URL completa do webservice.
     * @param  string $soapAction Header SOAPAction (sem aspas — estas são adicionadas).
     * @param  string $body       XML completo do envelope SOAP a ser POSTado.
     * @return array{http_code: int, body: string, error: ?string}
     */
    protected function sendSoap(string $endpoint, string $soapAction, string $body): array
    {
        $headers = [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $soapAction . '"',
            'User-Agent: LeandroDEV-Financeiro/1.0',
            'Accept: text/xml',
            'Content-Length: ' . strlen($body),
        ];

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['http_code' => 0, 'body' => '', 'error' => 'Falha ao inicializar cURL'];
        }

        $sslPeer = ($this->ambiente === 'PRODUCAO');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => $sslPeer,
            CURLOPT_SSL_VERIFYHOST => $sslPeer ? 2 : 0,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return [
                'http_code' => 0,
                'body'      => '',
                'error'     => $err !== '' ? $err : 'Erro HTTP desconhecido',
            ];
        }

        return [
            'http_code' => $code,
            'body'      => (string) $resp,
            'error'     => $code >= 200 && $code < 300 ? null : ('HTTP ' . $code . ': ' . $err),
        ];
    }

    /**
     * Monta envelope SOAP 1.1 com Header vazio e Body contendo $body.
     *
     * @param  string $body      Conteúdo a ir dentro de <soap:Body>.
     * @param  string $namespace Namespace específico do provedor (declarado como xmlns:ns).
     * @return string            Envelope SOAP completo (declarado XML + encoding UTF-8).
     */
    protected function wrapSoap(string $body, string $namespace): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/"'
            . ' xmlns:ns="' . $namespace . '">'
            . '<soap:Header/>'
            . '<soap:Body>' . $body . '</soap:Body>'
            . '</soap:Envelope>';
    }

    /**
     * Remove a declaração `<?xml ... ?>` de um XML (necessário antes de embeddar no SOAP body).
     *
     * @param  string $xml XML com ou sem declaração.
     * @return string      XML sem declaração.
     */
    protected function stripXmlDeclaration(string $xml): string
    {
        return preg_replace('/<\?xml[^>]*\?>\s*/i', '', $xml) ?? $xml;
    }

    /**
     * Carrega XML em SimpleXMLElement e registra o namespace ABRASF para uso em XPath.
     *
     * @param  string $xml XML de retorno da prefeitura.
     * @return SimpleXMLElement
     * @throws RuntimeException Se XML inválido.
     */
    protected function parseXmlResponse(string $xml): SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $simple = simplexml_load_string($xml);
            if ($simple === false) {
                $msg = libxml_get_last_error() !== false
                    ? trim(libxml_get_last_error()->message)
                    : 'erro desconhecido';
                throw new RuntimeException('Falha ao parsear XML de retorno: ' . $msg);
            }
            $simple->registerXPathNamespace('ns', 'http://www.abrasf.org.br/nfse.xsd');
            return $simple;
        } finally {
            libxml_use_internal_errors($previous);
            libxml_clear_errors();
        }
    }

    /**
     * Extrai o conteúdo da primeira ocorrência da tag $tag (regex, ignora namespaces).
     *
     * @param  string $xml XML fonte.
     * @param  string $tag Nome da tag (sem prefixo de namespace).
     * @return string|null Valor da tag ou null se não encontrada.
     */
    protected function extractBetween(string $xml, string $tag): ?string
    {
        $pattern = '/<' . preg_quote($tag, '/') . '(?:\s[^>]*)?>([^<]*)<\/'
            . preg_quote($tag, '/') . '>/i';
        if (preg_match($pattern, $xml, $m)) {
            $val = trim($m[1]);
            return $val === '' ? null : $val;
        }
        return null;
    }

    /**
     * Extrai o conteúdo bruto da primeira ocorrência de $tag (incluindo sub-tags aninhadas).
     * Útil para iterar em <MensagemRetorno> contendo <Codigo> e <Mensagem>.
     *
     * @param  string $xml XML fonte.
     * @param  string $tag Nome da tag externa.
     * @return string|null Conteúdo interno (innerHTML) ou null se não encontrado.
     */
    protected function extractInner(string $xml, string $tag): ?string
    {
        $pattern = '/<' . preg_quote($tag, '/')
            . '(?:\s[^>]*)?>([\s\S]*?)<\/' . preg_quote($tag, '/') . '>/i';
        if (preg_match($pattern, $xml, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extrai todas as mensagens de erro de um retorno ABRASF/Betha/WebISS.
     *
     * Procura por <ListaMensagemRetorno><MensagemRetorno><Codigo> e <Mensagem>
     * (qualquer profundidade / namespace) e retorna pares código→mensagem.
     *
     * @param  string $xml XML de retorno.
     * @return array<int,array{codigo: string, mensagem: string}>
     */
    protected function extractMensagensRetorno(string $xml): array
    {
        $out = [];
        $pattern = '/<MensagemRetorno(?:\s[^>]*)?>([\s\S]*?)<\/MensagemRetorno>/i';
        if (!preg_match_all($pattern, $xml, $matches)) {
            return $out;
        }
        foreach ($matches[1] as $bloco) {
            $codigo = $this->extractBetween($bloco, 'Codigo');
            $mensa  = $this->extractBetween($bloco, 'Mensagem');
            if ($mensa !== null) {
                $out[] = [
                    'codigo'   => $codigo ?? '',
                    'mensagem' => $mensa,
                ];
            }
        }
        return $out;
    }

    /**
     * Monta mensagem de erro combinada a partir de ListaMensagemRetorno.
     *
     * @param  string $xml XML de retorno.
     * @return string|null String "COD: mensagem" (se houver), null caso contrário.
     */
    protected function buildErrorMessage(string $xml): ?string
    {
        $mensagens = $this->extractMensagensRetorno($xml);
        if ($mensagens === []) {
            // Tentativa genérica: <Mensagem> solta.
            $m = $this->extractBetween($xml, 'Mensagem');
            return $m !== null ? $m : null;
        }
        $parts = [];
        foreach ($mensagens as $m) {
            $parts[] = trim($m['codigo'] . ': ' . $m['mensagem']);
        }
        return implode(' | ', $parts);
    }

    /**
     * Registra erro em /storage/logs/error_YYYYMMDD.log via error_log().
     *
     * @param string $contexto Identificador curto (ex: 'WebissAdapter::emitir').
     * @param string $mensagem Mensagem de erro.
     * @return void
     */
    protected function logError(string $contexto, string $mensagem): void
    {
        $linha = sprintf(
            '[%s] %s :: %s' . PHP_EOL,
            date('Y-m-d H:i:s'),
            $contexto,
            $mensagem
        );
        error_log($linha, 3, $this->logFilePath());
    }

    /**
     * Caminho absoluto do arquivo de log do dia (com fallback para sys_get_temp_dir).
     *
     * @return string
     */
    private function logFilePath(): string
    {
        $storage = dirname(__DIR__, 3) . '/storage/logs';
        if (!is_dir($storage)) {
            $storage = sys_get_temp_dir();
        }
        return $storage . '/error_' . date('Ymd') . '.log';
    }

    /**
     * Gera HTML print-friendly do DANFSE (Documento Auxiliar da NFSe).
     *
     * Layout Bootstrap 5 inline (CSS no <style>):
     *   - Cabeçalho: sistema + razão social + CNPJ + IM + endereço do prestador
     *   - Box NFSe: número, data emissão, código verificação
     *   - Tomador: nome, CPF/CNPJ, endereço
     *   - Discriminação do serviço
     *   - Valores: serviços, deduções, base cálculo, alíquota, ISS, líquido
     *   - QR code (api.qrserver.com) opcional
     *   - Botão "Imprimir" (window.print())
     *   - Rodapé com data/hora e sistema
     *
     * @param  array<string,mixed> $nfse Dados completos da NFSe autorizada.
     * @return string                    HTML completo (<!doctype html>).
     */
    protected function renderDanfseHtml(array $nfse): string
    {
        $eh    = static function (string $s): string {
            return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $numeroNfse  = $eh((string) ($nfse['numero_nfse'] ?? '—'));
        $serieRps    = $eh((string) ($nfse['serie_rps'] ?? '1'));
        $numeroRps   = $eh((string) ($nfse['numero_rps'] ?? '—'));
        $dataEmissao = $eh((string) ($nfse['data_emissao'] ?? ''));
        $codVerif    = $eh((string) ($nfse['codigo_verificacao'] ?? '—'));
        $protocolo   = $eh((string) ($nfse['protocolo'] ?? '—'));
        $ambiente    = $eh((string) ($nfse['ambiente'] ?? $this->ambiente));

        $prestRazao  = $eh((string) ($nfse['prestador_razao_social'] ?? 'MM Construtora'));
        $prestCnpj   = $eh((string) ($nfse['prestador_cnpj'] ?? ''));
        $prestIm     = $eh((string) ($nfse['prestador_im'] ?? ''));
        $prestMun    = $eh((string) ($nfse['prestador_cidade'] ?? ''));
        $prestUf     = $eh((string) ($nfse['prestador_uf'] ?? 'BA'));

        $tomNome     = $eh((string) ($nfse['tomador_nome'] ?? '—'));
        $tomDoc      = $eh((string) ($nfse['tomador_cnpj_cpf'] ?? '—'));
        $tomEnd      = $eh(trim(($nfse['tomador_endereco'] ?? '') . ', ' . ($nfse['tomador_numero'] ?? '')));
        $tomEnd      = trim($tomEnd, ', ');
        $tomBairro   = $eh((string) ($nfse['tomador_bairro'] ?? ''));
        $tomCidade   = $eh((string) ($nfse['tomador_cidade'] ?? ''));
        $tomUf       = $eh((string) ($nfse['tomador_uf'] ?? ''));
        $tomCep      = $eh((string) ($nfse['tomador_cep'] ?? ''));
        $tomEmail    = $eh((string) ($nfse['tomador_email'] ?? ''));

        $servCod     = $eh((string) ($nfse['servico_codigo'] ?? ''));
        $servDesc    = $eh((string) ($nfse['servico_descricao'] ?? ''));
        $discrim     = $eh((string) ($nfse['discriminacao'] ?? ''));
        $discrim     = nl2br($discrim);

        $vServ       = $eh(number_format((float) ($nfse['valor_servicos'] ?? 0), 2, ',', '.'));
        $vDed        = $eh(number_format((float) ($nfse['valor_deducoes'] ?? 0), 2, ',', '.'));
        $vBase       = $eh(number_format((float) ($nfse['valor_base_calculo'] ?? 0), 2, ',', '.'));
        $aliquota    = $eh(number_format((float) ($nfse['aliquota'] ?? 0) * 100, 4, ',', '.'));
        $vIss        = $eh(number_format((float) ($nfse['valor_iss'] ?? 0), 2, ',', '.'));
        $vLiq        = $eh(number_format((float) ($nfse['valor_liquido'] ?? 0), 2, ',', '.'));
        $issRetido   = !empty($nfse['iss_retido']) ? 'Sim' : 'Não';

        $qrData      = urlencode($codVerif);
        $qrUrl       = 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=' . $qrData;

        $emitidoEm   = date('d/m/Y H:i:s');

        return <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>DANFSE {$numeroNfse} — Leandro DEV Financeiro</title>
<style>
  *{box-sizing:border-box}
  body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#222;margin:24px auto;max-width:880px;background:#f5f6f8}
  .card{background:#fff;border:1px solid #dee2e6;border-radius:8px;padding:22px;margin-bottom:18px;box-shadow:0 1px 4px rgba(0,0,0,.04)}
  .header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}
  .header .brand{font-weight:700;font-size:18px;color:#0b5ed7}
  .header .brand small{display:block;font-weight:400;color:#555;font-size:12px;margin-top:2px}
  .nfse-box{background:#eef5ff;border:1px solid #b8d3fe;border-radius:6px;padding:14px 18px;display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}
  .nfse-box .num{font-size:22px;font-weight:700;color:#0b5ed7}
  .nfse-box small{color:#555;display:block;font-size:11px;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
  h3{font-size:14px;text-transform:uppercase;color:#0b5ed7;border-bottom:2px solid #e6ecf5;padding-bottom:6px;margin:0 0 12px}
  table{width:100%;border-collapse:collapse;margin-top:6px}
  table td,table th{padding:6px 8px;border-bottom:1px solid #eef0f4;vertical-align:top;text-align:left}
  table th{font-weight:600;color:#555;width:34%}
  .valores td:last-child,.valores th:last-child{text-align:right;font-variant-numeric:tabular-nums}
  .valores tr.total td{font-weight:700;background:#f8fbff;border-top:2px solid #b8d3fe}
  .discrim{white-space:pre-wrap;background:#fafbfd;border:1px dashed #d6dee8;padding:10px 12px;border-radius:4px;font-family:'Consolas','Courier New',monospace;font-size:12px}
  .qr{display:flex;align-items:center;gap:14px;justify-content:flex-end}
  .qr img{border:1px solid #eee;border-radius:4px}
  .btn-print{background:#0b5ed7;color:#fff;border:none;padding:9px 18px;border-radius:5px;cursor:pointer;font-size:13px;font-weight:600}
  .btn-print:hover{background:#0a51b5}
  .footer{text-align:center;color:#777;font-size:11px;padding:14px 0 6px;border-top:1px solid #e6ecf5;margin-top:18px}
  .ambiente-badge{display:inline-block;padding:3px 9px;border-radius:3px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase}
  .ambiente-HOMOLOGACAO{background:#fff3cd;color:#856404;border:1px solid #ffe69c}
  .ambiente-PRODUCAO{background:#d4edda;color:#155724;border:1px solid #b1dfbb}
  @media print{
    body{margin:0;background:#fff;max-width:none}
    .btn-print,.no-print{display:none!important}
    .card{box-shadow:none;border:1px solid #ccc;page-break-inside:avoid}
  }
</style>
</head>
<body>

<div class="card no-print" style="text-align:right">
  <button type="button" class="btn-print" onclick="window.print()">🖨️ Imprimir DANFSE</button>
</div>

<div class="card">
  <div class="header">
    <div>
      <div class="brand">Leandro DEV Financeiro
        <small>MM Construtora — Módulo NFSe Bahia</small>
      </div>
    </div>
    <div style="text-align:right">
      <span class="ambiente-badge ambiente-{$ambiente}">{$ambiente}</span>
    </div>
  </div>
  <hr style="border:0;border-top:1px solid #e6ecf5;margin:14px 0">
  <div class="nfse-box">
    <div>
      <small>Número da NFSe</small>
      <div class="num">{$numeroNfse}</div>
    </div>
    <div>
      <small>RPS / Série</small>
      <div class="num">{$numeroRps} / {$serieRps}</div>
    </div>
    <div>
      <small>Data de Emissão</small>
      <div class="num">{$dataEmissao}</div>
    </div>
    <div>
      <small>Protocolo</small>
      <div class="num" style="font-size:14px">{$protocolo}</div>
    </div>
  </div>
</div>

<div class="card">
  <h3>Prestador de Serviços</h3>
  <table>
    <tr><th>Razão Social</th><td>{$prestRazao}</td></tr>
    <tr><th>CNPJ</th><td>{$prestCnpj}</td></tr>
    <tr><th>Inscrição Municipal</th><td>{$prestIm}</td></tr>
    <tr><th>Município / UF</th><td>{$prestMun} / {$prestUf}</td></tr>
  </table>
</div>

<div class="card">
  <h3>Tomador de Serviços</h3>
  <table>
    <tr><th>Nome / Razão Social</th><td>{$tomNome}</td></tr>
    <tr><th>CPF / CNPJ</th><td>{$tomDoc}</td></tr>
    <tr><th>Endereço</th><td>{$tomEnd}</td></tr>
    <tr><th>Bairro</th><td>{$tomBairro}</td></tr>
    <tr><th>Município / UF / CEP</th><td>{$tomCidade} / {$tomUf} / {$tomCep}</td></tr>
    <tr><th>E-mail</th><td>{$tomEmail}</td></tr>
  </table>
</div>

<div class="card">
  <h3>Discriminação do Serviço</h3>
  <p style="margin:0 0 8px"><strong>Item LC 116:</strong> {$servCod} — {$servDesc}</p>
  <div class="discrim">{$discrim}</div>
</div>

<div class="card">
  <h3>Valores</h3>
  <table class="valores">
    <tr><th>Valor dos Serviços</th><td>R$ {$vServ}</td></tr>
    <tr><th>Deduções</th><td>R$ {$vDed}</td></tr>
    <tr><th>Base de Cálculo</th><td>R$ {$vBase}</td></tr>
    <tr><th>Alíquota</th><td>{$aliquota}%</td></tr>
    <tr><th>ISS Retido</th><td>{$issRetido}</td></tr>
    <tr><th>Valor do ISS</th><td>R$ {$vIss}</td></tr>
    <tr class="total"><td>Valor Líquido da NFSe</td><td>R$ {$vLiq}</td></tr>
  </table>
</div>

<div class="card">
  <h3>Código de Verificação</h3>
  <div class="qr">
    <div style="text-align:right">
      <small style="display:block;color:#555;margin-bottom:4px">Código de Verificação</small>
      <code style="font-family:Consolas,monospace;font-size:11px;word-break:break-all;display:inline-block;max-width:480px">{$codVerif}</code>
    </div>
    <img src="{$qrUrl}" alt="QR Code do código de verificação" width="100" height="100">
  </div>
</div>

<div class="footer">
  Emitido em {$emitidoEm} por Leandro DEV Financeiro — MM Construtora
</div>

</body>
</html>
HTML;
    }
}
