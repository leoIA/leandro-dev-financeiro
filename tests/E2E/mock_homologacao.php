<?php
declare(strict_types=1);

/**
 * @file    mock_homologacao.php
 * @package Tests\E2E
 * @since   2026.07.08
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Teste E2E mock do fluxo de emissão NFSe (sem chamar prefeituras reais).
 *
 * O que este script faz:
 *   1) Sobe um mock HTTP server em 127.0.0.1:8765 via `php -S` (processo filho
 *      via pcntl_fork quando disponível, ou exec() background caso contrário).
 *   2) O mock server sempre responde com XML ABRASF de sucesso pré-formatado
 *      contendo <Numero>12345</Numero>, <CodigoVerificacao>MOCK-VERIFICACAO-123
 *      </CodigoVerificacao> e <Protocolo>MOCK-PROTO-123</Protocolo>.
 *   3) Teste A (HTTP direto): dispara POST SOAP para o mock server via cURL e
 *      valida resposta 200 com <GerarNfseResposta>.
 *   4) Teste B (adapter real): instancia WebissAdapter com certificado .pfx
 *      de teste gerado via openssl; injeta via Reflection o mock endpoint no
 *      MunicipiosCatalog::$cache; chama WebissAdapter::emitir($nfse) e valida
 *      que parsing do retorno mock produz sucesso=true com numero=12345.
 *   5) Imprime "E2E MOCK: OK" ou "E2E MOCK: FALHOU" com detalhes e sai com
 *      exit code 0 (sucesso) ou 1 (falha).
 *
 * Pré-requisitos:
 *   - PHP CLI >= 8.1 com extensões openssl, curl, dom, mbstring
 *   - Binário openssl CLI (para gerar .pfx de teste)
 *   - Permissão para abrir socket TCP em 127.0.0.1:8765
 *
 * Uso:
 *   $ php tests/E2E/mock_homologacao.php
 *
 * Saída (exemplo sucesso):
 *   E2E MOCK: OK
 *     HTTP code: 200
 *     Response contains GerarNfseResposta: YES
 *     Numero NFSe: 12345
 *     Adapter emitir(): sucesso=true, numero=12345, protocolo=MOCK-PROTO-123
 *   Exit: 0
 *
 * Não chama prefeituras reais — apenas o mock HTTP local.
 */

// Bootstrap mínimo: registra autoloader PSR-4 do App\ sem chamar App::boot()
// (que iniciaria sessão e exigiria config.php com DB). Apenas precisamos
// carregar classes App\Nfse\*.
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../../app/';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Nfse\Adapters\WebissAdapter;
use App\Nfse\Data\MunicipiosCatalog;

/** @var array<int,int> $childPids PIDs de processos filhos a matar no shutdown. */
$childPids = [];
/** @var string|null $pfxPath Caminho do .pfx de teste (para cleanup). */
$pfxPath = null;
/** @var string|null $mockServerScript Caminho do script PHP do mock server. */
$mockServerScript = null;
/** @var string|null $mockServerLog Caminho do log do mock server. */
$mockServerLog = null;
/** @var string|null $keyPem Caminho do key.pem de teste. */
$keyPem = null;
/** @var string|null $certPem Caminho do cert.pem de teste. */
$certPem = null;

/**
 * Mata processos filhos e remove arquivos temporários no shutdown.
 */
$cleanup = static function () use (&$childPids, &$pfxPath, &$mockServerScript, &$mockServerLog, &$keyPem, &$certPem): void {
    foreach ($childPids as $pid) {
        if ($pid > 0 && function_exists('posix_kill')) {
            @posix_kill($pid, SIGTERM);
        }
    }
    foreach ([$pfxPath, $mockServerScript, $mockServerLog, $keyPem, $certPem] as $f) {
        if ($f !== null && file_exists($f)) {
            @unlink($f);
        }
    }
};
register_shutdown_function($cleanup);

// ============================================================
// 1) Gera o script PHP do mock server (responde sempre sucesso).
// ============================================================
$mockServerScript = tempnam(sys_get_temp_dir(), 'mocksrv') . '.php';
$mockServerLog    = tempnam(sys_get_temp_dir(), 'mocklog') . '.log';

$mockPhp = <<<'PHP'
<?php
declare(strict_types=1);
/**
 * Mock server de homologação NFSe.
 * Responde a qualquer request POST com XML ABRASF de sucesso pré-formatado.
 */
$input = file_get_contents('php://input');
// Log do request recebido para debug.
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI'] ?? '/';
$action = $_SERVER['HTTP_SOAPACTION'] ?? '(none)';

header('Content-Type: text/xml; charset=utf-8');
header('X-Mock-Server: nfse-hml');
http_response_code(200);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
    . '<soap:Envelope xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
    . '<soap:Body>'
    . '<GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd">'
    . '<ListaNfse>'
    . '<CompNfse>'
    . '<Nfse>'
    . '<Numero>12345</Numero>'
    . '<CodigoVerificacao>MOCK-VERIFICACAO-123</CodigoVerificacao>'
    . '<DataEmissao>2026-07-08T10:00:00</DataEmissao>'
    . '</Nfse>'
    . '<Protocolo>MOCK-PROTO-123</Protocolo>'
    . '</CompNfse>'
    . '</ListaNfse>'
    . '</GerarNfseResposta>'
    . '</soap:Body>'
    . '</soap:Envelope>';
PHP;
file_put_contents($mockServerScript, $mockPhp);

// ============================================================
// 2) Sobe o mock server em background (fork via pcntl ou exec).
// ============================================================
$hasPcntl = function_exists('pcntl_fork');
$mockUrl  = 'http://127.0.0.1:8765/';

if ($hasPcntl) {
    $pid = pcntl_fork();
    if ($pid === -1) {
        fwrite(STDERR, "pcntl_fork() falhou — tentando fallback via exec()\n");
        $hasPcntl = false;
    } elseif ($pid === 0) {
        // Processo filho: roda o mock server em foreground (bloqueante).
        $cmd = 'php -S 127.0.0.1:8765 ' . escapeshellarg($mockServerScript);
        // Redireciona saída para log (filho não deve poluir stdout do pai).
        $fdSpecs = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $mockServerLog, 'a'],
            2 => ['file', $mockServerLog, 'a'],
        ];
        $proc = @proc_open($cmd, $fdSpecs, $pipes);
        if (is_resource($proc)) {
            // Mantém vivo até receber SIGTERM/SIGKILL.
            while (true) {
                sleep(60);
            }
            proc_close($proc);
        }
        exit(0);
    } else {
        // Processo pai: registra PID do filho.
        $childPids[] = $pid;
    }
}

if (!$hasPcntl) {
    // Fallback: exec() em background com & e redirecionamento.
    $cmd = 'php -S 127.0.0.1:8765 ' . escapeshellarg($mockServerScript)
        . ' > ' . escapeshellarg($mockServerLog) . ' 2>&1 &';
    exec($cmd);
    // Em sistemas sem pcntl, não há PID direto — mata via pkill no shutdown.
    register_shutdown_function(static function (): void {
        // Tenta matar o processo php -S em 127.0.0.1:8765 (best-effort).
        @exec('pkill -f "php -S 127.0.0.1:8765" 2>/dev/null');
    });
}

// Aguarda o mock server subir (tentativas com backoff).
$ready = false;
for ($i = 0; $i < 20; $i++) {
    usleep(200000); // 200ms
    $fp = @fsockopen('127.0.0.1', 8765, $errno, $errstr, 0.5);
    if (is_resource($fp)) {
        fclose($fp);
        $ready = true;
        break;
    }
}

if (!$ready) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Erro: mock server não subiu em 127.0.0.1:8765 após 20 tentativas\n";
    echo "  Log do mock server:\n";
    if (is_file($mockServerLog)) {
        echo "  ---\n" . file_get_contents($mockServerLog) . "\n  ---\n";
    }
    $cleanup();
    exit(1);
}

// ============================================================
// 3) Teste A: HTTP direto ao mock server com cURL (valida mock).
// ============================================================
echo "E2E MOCK: iniciando teste A (HTTP direto ao mock)\n";

$ch = curl_init($mockUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: text/xml; charset=utf-8',
    'SOAPAction: "http://nfse.abrasf.org.br/EmitirNfse"',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, '<GerarNfseEnvio xmlns="http://www.abrasf.org.br/nfse.xsd"><Rps/></GerarNfseEnvio>');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

$testeAPassou = ($httpCode === 200)
    && is_string($response)
    && str_contains($response, 'GerarNfseResposta');

if (!$testeAPassou) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste A (HTTP direto) falhou:\n";
    echo "    HTTP code: {$httpCode}\n";
    echo "    curl error: {$curlErr}\n";
    if (is_string($response)) {
        echo "    Response (200 chars): " . substr($response, 0, 200) . "\n";
    }
    $cleanup();
    exit(1);
}

$numeroMock = 'NOT FOUND';
if (is_string($response) && preg_match('/<Numero>([^<]+)<\/Numero>/', $response, $m)) {
    $numeroMock = $m[1];
}

echo "  Teste A OK\n";
echo "    HTTP code: {$httpCode}\n";
echo "    Response contains GerarNfseResposta: YES\n";
echo "    Numero NFSe retornado pelo mock: {$numeroMock}\n";

// ============================================================
// 4) Teste B: Adapter WebissAdapter::emitir() com mock endpoint.
// ============================================================
echo "E2E MOCK: iniciando teste B (WebissAdapter::emitir)\n";

// 4.1) Gera .pfx de teste via openssl CLI.
$keyPem  = tempnam(sys_get_temp_dir(), 'e2ekey');
$certPem = tempnam(sys_get_temp_dir(), 'e2ecert');
$pfxPath = tempnam(sys_get_temp_dir(), 'e2epfx') . '.pfx';

$cmdKey = 'openssl req -x509 -newkey rsa:2048 '
    . '-keyout ' . escapeshellarg($keyPem) . ' '
    . '-out '    . escapeshellarg($certPem) . ' '
    . '-days 1 -nodes -subj \'/CN=Teste MM Construtora/O=CNPJ: 00.000.000/0000-00\' 2>/dev/null';
exec($cmdKey, $out, $ret);
if ($ret !== 0) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste B: openssl req falhou (CLI openssl indisponível?)\n";
    $cleanup();
    exit(1);
}

$cmdPfx = 'openssl pkcs12 -export '
    . '-out '   . escapeshellarg($pfxPath) . ' '
    . '-inkey ' . escapeshellarg($keyPem) . ' '
    . '-in '    . escapeshellarg($certPem) . ' '
    . '-passout pass:testepass 2>/dev/null';
exec($cmdPfx, $out2, $ret2);
if ($ret2 !== 0) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste B: openssl pkcs12 falhou\n";
    $cleanup();
    exit(1);
}

// 4.2) Injeta mock endpoint no cache do MunicipiosCatalog via Reflection.
// Como o adapter consulta MunicipiosCatalog::getEndpoint($ibge, $ambiente),
// substituímos o cache interno por um array com Feira de Santana apontando
// para o mock server (127.0.0.1:8765).
MunicipiosCatalog::resetCache();
$ref = new ReflectionClass(MunicipiosCatalog::class);
$cacheProp = $ref->getProperty('cache');
$cacheProp->setAccessible(true);
$cacheProp->setValue(null, [
    [
        'codigo_ibge'          => '2910800',
        'nome'                 => 'Feira de Santana (MOCK)',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => $mockUrl,
        'endpoint_homologacao' => $mockUrl,
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
]);

// 4.3) Dados da NFSe de teste (tomador fake, valor 1,00 — típico de homologação).
$nfse = [
    'numero_rps'                => 1,
    'serie_rps'                 => '1',
    'data_emissao'              => '2026-07-08 10:00:00',
    'prestador_cnpj'            => '12345678000190',
    'prestador_im'              => '123456',
    'tomador_tipo_pessoa'       => 'FISICA',
    'tomador_nome'              => 'Tomador Homologacao Teste',
    'tomador_cnpj_cpf'          => '12345678909',
    'tomador_email'             => 'tomador@hml.teste',
    'tomador_cep'               => '40000000',
    'tomador_endereco'          => 'Rua Teste',
    'tomador_numero'            => '100',
    'tomador_bairro'            => 'Centro',
    'tomador_cidade'            => 'Salvador',
    'tomador_uf'                => 'BA',
    'tomador_codigo_municipio'  => '2910800',
    'servico_codigo'            => '1.01',
    'servico_descricao'         => 'Serviços de teste',
    'discriminacao'             => 'NFSe de homologacao - teste E2E mock',
    'valor_servicos'            => 1.00,
    'valor_deducoes'            => 0.00,
    'aliquota'                  => 0.0300,
    'valor_iss'                 => 0.03,
    'valor_base_calculo'        => 1.00,
    'valor_liquido'             => 0.97,
    'iss_retido'                => 0,
    'desconto_incondicionado'   => 0.00,
    'desconto_condicionado'     => 0.00,
    'municipio_ibge'            => '2910800',
];

// 4.4) Instancia adapter e chama emitir().
$cert = [
    'arquivo_path' => $pfxPath,
    'senha'         => 'testepass',
];

$adapter = new WebissAdapter($cert, 'HOMOLOGACAO');
$resultado = $adapter->emitir($nfse);

$testeBPassou = is_array($resultado)
    && ($resultado['sucesso'] ?? false) === true
    && ($resultado['numero'] ?? null) === '12345'
    && ($resultado['protocolo'] ?? null) === 'MOCK-PROTO-123'
    && ($resultado['xml_envio'] ?? null) !== null
    && ($resultado['xml_retorno'] ?? null) !== null
    && str_contains((string) $resultado['xml_retorno'], 'GerarNfseResposta');

if (!$testeBPassou) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste B (WebissAdapter::emitir) falhou:\n";
    echo "    sucesso: " . var_export($resultado['sucesso'] ?? null, true) . "\n";
    echo "    numero: " . var_export($resultado['numero'] ?? null, true) . "\n";
    echo "    protocolo: " . var_export($resultado['protocolo'] ?? null, true) . "\n";
    echo "    erro: " . var_export($resultado['erro'] ?? null, true) . "\n";
    if (isset($resultado['xml_envio'])) {
        echo "    xml_envio (200 chars): " . substr((string) $resultado['xml_envio'], 0, 200) . "\n";
    }
    if (isset($resultado['xml_retorno'])) {
        echo "    xml_retorno (200 chars): " . substr((string) $resultado['xml_retorno'], 0, 200) . "\n";
    }
    $cleanup();
    exit(1);
}

echo "  Teste B OK\n";
echo "    Adapter: " . WebissAdapter::class . "\n";
echo "    emitir() sucesso: true\n";
echo "    numero retornado: {$resultado['numero']}\n";
echo "    codigo_verificacao: {$resultado['codigo_verificacao']}\n";
echo "    protocolo: {$resultado['protocolo']}\n";

// 4.5) Valida que xml_envio está assinado (contém <Signature>).
$xmlEnvio = (string) $resultado['xml_envio'];
if (!str_contains($xmlEnvio, '<Signature')) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste B: xml_envio não contém <Signature> (assinatura falhou silenciosamente)\n";
    $cleanup();
    exit(1);
}
echo "    xml_envio assinado (contém <Signature>): YES\n";

// 4.6) Valida que xml_retorno é parseável como XML.
$xmlRetorno = (string) $resultado['xml_retorno'];
$doc = new DOMDocument();
$loaded = @$doc->loadXML($xmlRetorno);
if ($loaded === false) {
    echo "E2E MOCK: FALHOU\n";
    echo "  Teste B: xml_retorno não é XML parseável\n";
    $cleanup();
    exit(1);
}
echo "    xml_retorno parseável como XML: YES\n";

// ============================================================
// 5) Sucesso total — imprime OK e sai com exit code 0.
// ============================================================
echo "E2E MOCK: OK\n";
echo "  Teste A (HTTP direto): OK\n";
echo "  Teste B (WebissAdapter::emitir): OK\n";
echo "  Exit: 0\n";

$cleanup();
exit(0);
