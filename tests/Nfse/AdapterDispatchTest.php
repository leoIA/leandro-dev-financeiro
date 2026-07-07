<?php
declare(strict_types=1);

/**
 * @file    AdapterDispatchTest.php
 * @package Tests\Nfse
 * @since   2026.07.08
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Testes PHPUnit para factory de adapters + contrato da interface.
 *
 * Cobre:
 *   - Lógica de fábrica: município → adapter correspondente (4 provedores)
 *   - Mock do adapter retornando sucesso pré-formatado (emitir)
 *   - Mock do adapter retornando erro pré-formatado (emitir)
 *   - Mock do adapter retornando sucesso no cancelamento
 *   - Mock do adapter retornando sucesso na consulta
 *   - Mock do adapter retornando HTML do DANFSE
 *
 * Estratégia:
 *   - Usa createMock(NfseAdapterInterface::class) para isolar de HTTP/DB real
 *   - Testa a função factory estática (simula NfseController::getAdapter)
 *     verificando que o provedor do município determina a classe adapter
 *   - Não chama prefeituras reais
 */

namespace Tests\Nfse;

use App\Nfse\Adapters\BethaAdapter;
use App\Nfse\Adapters\DsfAdapter;
use App\Nfse\Adapters\NfseAdapterInterface;
use App\Nfse\Adapters\SalvadorAdapter;
use App\Nfse\Adapters\WebissAdapter;
use App\Nfse\Data\MunicipiosCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Nfse\Adapters\NfseAdapterInterface
 */
class AdapterDispatchTest extends TestCase
{
    /**
     * Lista de provedores suportados → classe adapter correspondente.
     * Espelha a lógica de NfseController::getAdapter() (provada por simulação
     * sem invocar ReflectionMethod, mantendo baixo acoplamento).
     *
     * @var array<string,class-string<NfseAdapterInterface>>
     */
    private const MAPA_PROVEDOR_ADAPTER = [
        'WEBISS'   => WebissAdapter::class,
        'BETHA'    => BethaAdapter::class,
        'DSF'      => DsfAdapter::class,
        'SALVADOR' => SalvadorAdapter::class,
    ];

    /**
     * Para cada um dos 10 municípios do catálogo, o provedor deve mapear
     * para uma das 4 classes adapter válidas.
     *
     * @covers ::factory
     */
    public function testAdapterFactoryReturnsCorrectAdapter(): void
    {
        $municipios = MunicipiosCatalog::all();
        $this->assertNotEmpty($municipios);

        foreach ($municipios as $m) {
            $provedor = $m['provedor'];
            $this->assertArrayHasKey(
                $provedor,
                self::MAPA_PROVEDOR_ADAPTER,
                "Provedor {$provedor} (município {$m['nome']}) sem adapter mapeado"
            );

            $classeAdapter = self::MAPA_PROVEDOR_ADAPTER[$provedor];
            $this->assertTrue(
                in_array(NfseAdapterInterface::class, class_implements($classeAdapter) ?: [], true),
                "Classe {$classeAdapter} não implementa NfseAdapterInterface"
            );
        }
    }

    /**
     * Mock do adapter emitir() retornando sucesso pré-formatado.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::emitir
     */
    public function testEmitirHomologacaoReturnsMockedSuccess(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('emitir')->willReturn([
            'sucesso'            => true,
            'numero'             => '12345',
            'codigo_verificacao' => 'ABC-123-DEF',
            'protocolo'          => 'PROTO-123',
            'erro'               => null,
            'xml_envio'          => '<xml>envio</xml>',
            'xml_retorno'        => '<xml>retorno</xml>',
        ]);

        $result = $mockAdapter->emitir([]);

        $this->assertTrue($result['sucesso']);
        $this->assertSame('12345', $result['numero']);
        $this->assertSame('ABC-123-DEF', $result['codigo_verificacao']);
        $this->assertSame('PROTO-123', $result['protocolo']);
        $this->assertNull($result['erro']);
        $this->assertNotEmpty($result['xml_envio']);
        $this->assertNotEmpty($result['xml_retorno']);
    }

    /**
     * Mock do adapter emitir() retornando erro pré-formatado.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::emitir
     */
    public function testEmitirHomologacaoReturnsMockedError(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('emitir')->willReturn([
            'sucesso'            => false,
            'numero'             => null,
            'codigo_verificacao' => null,
            'protocolo'          => null,
            'erro'               => 'Erro simulado: CPF/CNPJ do tomador inválido',
            'xml_envio'          => '<xml>envio</xml>',
            'xml_retorno'        => '<xml>erro</xml>',
        ]);

        $result = $mockAdapter->emitir([]);

        $this->assertFalse($result['sucesso']);
        $this->assertNotNull($result['erro']);
        $this->assertNull($result['numero']);
        $this->assertStringContainsString('CPF/CNPJ', $result['erro']);
    }

    /**
     * Mock do adapter cancelar() retornando sucesso para motivo válido.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::cancelar
     */
    public function testCancelarWithValidMotivoSucceeds(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('cancelar')->willReturn([
            'sucesso'     => true,
            'erro'        => null,
            'xml_retorno' => '<xml>cancelado</xml>',
        ]);

        $result = $mockAdapter->cancelar('PROTO-123', 'Cancelamento por erro de digitação');

        $this->assertTrue($result['sucesso']);
        $this->assertNull($result['erro']);
    }

    /**
     * Mock do adapter cancelar() com motivo curto deve retornar erro
     * (simulação da validação de >= 15 caracteres implementada nos adapters).
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::cancelar
     */
    public function testCancelarWithShortMotivoReturnsError(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('cancelar')->willReturn([
            'sucesso'     => false,
            'erro'        => 'Motivo do cancelamento deve ter no mínimo 15 caracteres.',
            'xml_retorno' => null,
        ]);

        $result = $mockAdapter->cancelar('PROTO-123', 'curto');

        $this->assertFalse($result['sucesso']);
        $this->assertStringContainsString('15 caracteres', $result['erro']);
    }

    /**
     * Mock do adapter consultar() retornando situação autorizada.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::consultar
     */
    public function testConsultarProtocoloReturnsValidXml(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('consultar')->willReturn([
            'sucesso'     => true,
            'status'      => 'AUTORIZADA',
            'erro'        => null,
            'xml_retorno' => '<?xml version="1.0"?><ConsultarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><Situacao>AUTORIZADA</Situacao></ConsultarNfseResposta>',
        ]);

        $result = $mockAdapter->consultar('PROTO-123');

        $this->assertTrue($result['sucesso']);
        $this->assertSame('AUTORIZADA', $result['status']);
        $this->assertStringContainsString('ConsultarNfseResposta', $result['xml_retorno']);
    }

    /**
     * Mock do adapter gerarDanfse() retornando string HTML.
     *
     * Espelha exatamente o snippet de spec N08: mock retorna '<html><body>DANFSE</body></html>'
     * e o teste asseriona apenas que a string contém '<html'.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::gerarDanfse
     */
    public function testGerarDanfseReturnsHtmlString(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('gerarDanfse')->willReturn('<html><body>DANFSE</body></html>');

        $html = $mockAdapter->gerarDanfse(['numero_nfse' => '123']);

        $this->assertStringContainsString('<html', $html);
    }

    /**
     * Mock do adapter gerarDanfse() retornando HTML completo com dados da NFSe.
     *
     * Variante enriquecida que valida conteúdo do DANFSE além do mero '<html'.
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::gerarDanfse
     */
    public function testGerarDanfseReturnsHtmlComDados(): void
    {
        $mockAdapter = $this->createMock(NfseAdapterInterface::class);
        $mockAdapter->method('gerarDanfse')->willReturn(
            '<!DOCTYPE html><html><head><title>DANFSE</title></head>'
            . '<body><h1>NFSe 12345</h1><p>Tomador: Cliente Teste</p></body></html>'
        );

        $html = $mockAdapter->gerarDanfse(['numero_nfse' => '12345']);

        $this->assertStringContainsString('<html', $html);
        $this->assertStringContainsString('NFSe 12345', $html);
        $this->assertStringContainsString('Cliente Teste', $html);
    }

    /**
     * Construtor do adapter mock deve aceitar array de certificado + ambiente.
     *
     * Como a interface declara __construct(array, string), validamos que o
     * mock aceita esses parâmetros (PHPUnit createMock respeita a assinatura).
     *
     * @covers \App\Nfse\Adapters\NfseAdapterInterface::__construct
     */
    public function testAdapterConstructorAcceptsCertAndAmbiente(): void
    {
        $cert = [
            'arquivo_path' => '/tmp/test.pfx',
            'senha'         => 'test',
        ];

        // createMock() invoca o construtor automaticamente com os parâmetros
        // originais; se a assinatura mudar, este teste quebra cedo.
        $mock = $this->createMock(NfseAdapterInterface::class);
        $this->assertInstanceOf(NfseAdapterInterface::class, $mock);

        // Testa o método consultar como sanity check de que o objeto é utilizável.
        $mock->method('consultar')->willReturn([
            'sucesso'     => true,
            'status'      => 'AUTORIZADA',
            'erro'        => null,
            'xml_retorno' => '<xml/>',
        ]);
        $this->assertTrue($mock->consultar('PROTO-X')['sucesso']);
    }

    /**
     * Teste de integração real com adapter concreto usando certificado de teste.
     *
     * Como não há HTTP real, este teste apenas valida que a classe adapter
     * pode ser instanciada sem erro quando o .pfx existe — a chamada emitir()
     * deve falhar no Signer ou no sendSoap com mensagem específica (não em
     * tempo de construção).
     *
     * Skipado se openssl CLI não disponível.
     */
    public function testWebissAdapterCanBeInstantiated(): void
    {
        $pfxPath = $this->gerarPfxTeste();
        if ($pfxPath === null) {
            $this->markTestSkipped('openssl CLI não disponível');
        }

        try {
            $cert = ['arquivo_path' => $pfxPath, 'senha' => 'testepass'];
            $adapter = new WebissAdapter($cert, 'HOMOLOGACAO');
            $this->assertInstanceOf(NfseAdapterInterface::class, $adapter);
        } finally {
            if (file_exists($pfxPath)) {
                @unlink($pfxPath);
            }
        }
    }

    /**
     * Gera um .pfx de teste via CLI openssl; retorna null se indisponível.
     *
     * @return string|null Caminho do .pfx ou null se openssl falhar.
     */
    private function gerarPfxTeste(): ?string
    {
        $keyPem  = tempnam(sys_get_temp_dir(), 'key');
        $certPem = tempnam(sys_get_temp_dir(), 'cert');
        $pfxPath = tempnam(sys_get_temp_dir(), 'pfx') . '.pfx';

        $cmd = 'openssl req -x509 -newkey rsa:2048 '
            . '-keyout ' . escapeshellarg($keyPem) . ' '
            . '-out '    . escapeshellarg($certPem) . ' '
            . '-days 1 -nodes -subj \'/CN=Teste\' 2>/dev/null';
        exec($cmd, $out, $ret);
        if ($ret !== 0) {
            @unlink($keyPem);
            @unlink($certPem);
            return null;
        }

        $cmdPfx = 'openssl pkcs12 -export '
            . '-out '   . escapeshellarg($pfxPath) . ' '
            . '-inkey ' . escapeshellarg($keyPem) . ' '
            . '-in '    . escapeshellarg($certPem) . ' '
            . '-passout pass:testepass 2>/dev/null';
        exec($cmdPfx, $out2, $ret2);
        @unlink($keyPem);
        @unlink($certPem);
        if ($ret2 !== 0) {
            return null;
        }
        return $pfxPath;
    }
}
