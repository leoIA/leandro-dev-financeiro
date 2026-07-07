<?php
declare(strict_types=1);

/**
 * @file    MunicipioCatalogTest.php
 * @package Tests\Nfse
 * @since   2026.07.08
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Testes PHPUnit para App\Nfse\Data\MunicipiosCatalog.
 *
 * Cobre:
 *   - Catálogo contém exatamente 10 municípios BA
 *   - Busca por IBGE retorna o provedor correto (Salvador → SALVADOR)
 *   - Todos os 10 municípios têm provedor em ['WEBISS','BETHA','DSF','SALVADOR']
 *   - Todos têm endpoint_producao não vazio
 *   - isSupported() retorna true para os 10 IBGE do catálogo
 *   - getEndpoint('2927408','PRODUCAO') retorna URL contendo 'nfse.salvador'
 *   - byNome() é case-insensitive ('salvador' encontra 'Salvador')
 *
 * Estratégia:
 *   - resetCache() no setUp() para garantir estado limpo entre testes
 *   - Não depende de DB nem de HTTP real
 */

namespace Tests\Nfse;

use App\Nfse\Data\MunicipiosCatalog;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \App\Nfse\Data\MunicipiosCatalog
 */
class MunicipioCatalogTest extends TestCase
{
    /** Lista fixa de provedores efetivamente implementados (4 de 7 do ENUM). */
    private const PROVEDORES_VALIDOS = ['WEBISS', 'BETHA', 'DSF', 'SALVADOR'];

    /**
     * Limpa cache interno entre testes para evitar estado residual.
     */
    protected function setUp(): void
    {
        MunicipiosCatalog::resetCache();
    }

    /**
     * all() deve retornar exatamente 10 municípios BA catalogados.
     *
     * @covers ::all
     */
    public function testAllReturns10Municipios(): void
    {
        $this->assertCount(10, MunicipiosCatalog::all());
    }

    /**
     * byIbge('2927408') deve retornar o município de Salvador com provedor SALVADOR.
     *
     * @covers ::byIbge
     */
    public function testGetMunicipioByIbgeReturnsCorrectProvedor(): void
    {
        $m = MunicipiosCatalog::byIbge('2927408');
        $this->assertNotNull($m);
        $this->assertSame('SALVADOR', $m['provedor']);
        $this->assertSame('Salvador', $m['nome']);
        $this->assertSame('BA', $m['uf']);
    }

    /**
     * Todos os 10 municípios devem ter provedor em ['WEBISS','BETHA','DSF','SALVADOR'].
     *
     * @covers ::all
     */
    public function testAll10MunicipiosHaveProvedor(): void
    {
        foreach (MunicipiosCatalog::all() as $m) {
            $this->assertArrayHasKey('provedor', $m);
            $this->assertContains(
                $m['provedor'],
                self::PROVEDORES_VALIDOS,
                "Município {$m['nome']} tem provedor inválido: {$m['provedor']}"
            );
        }
    }

    /**
     * Todos os 10 municípios devem ter endpoint_producao não vazio.
     *
     * @covers ::all
     */
    public function testEndpointProducaoNotNullForAll(): void
    {
        foreach (MunicipiosCatalog::all() as $m) {
            $this->assertArrayHasKey('endpoint_producao', $m);
            $this->assertNotEmpty(
                $m['endpoint_producao'],
                "Município {$m['nome']} sem endpoint_producao"
            );
            $this->assertStringStartsWith(
                'https://',
                $m['endpoint_producao'],
                "Endpoint de produção de {$m['nome']} deve ser HTTPS"
            );
        }
    }

    /**
     * isSupported() deve retornar true para todos os 10 IBGE do catálogo.
     *
     * @covers ::isSupported
     * @covers ::byIbge
     */
    public function testIsSupportedReturnsTrueFor10Cidades(): void
    {
        $ibges = array_column(MunicipiosCatalog::all(), 'codigo_ibge');
        $this->assertCount(10, $ibges);
        foreach ($ibges as $ibge) {
            $this->assertTrue(
                MunicipiosCatalog::isSupported($ibge),
                "IBGE {$ibge} deveria estar suportado"
            );
        }
    }

    /**
     * isSupported() deve retornar false para IBGE inexistente.
     *
     * @covers ::isSupported
     */
    public function testIsSupportedReturnsFalseForUnknownIbge(): void
    {
        $this->assertFalse(MunicipiosCatalog::isSupported('9999999'));
    }

    /**
     * getEndpoint('2927408','PRODUCAO') deve retornar URL contendo 'nfse.salvador'.
     *
     * @covers ::getEndpoint
     */
    public function testGetEndpointReturnsCorrectUrl(): void
    {
        $url = MunicipiosCatalog::getEndpoint('2927408', 'PRODUCAO');
        $this->assertNotNull($url);
        $this->assertStringContainsString('nfse.salvador', $url);
        $this->assertStringStartsWith('https://', $url);
    }

    /**
     * getEndpoint('2927408','HOMOLOGACAO') deve retornar endpoint de homologação.
     *
     * @covers ::getEndpoint
     */
    public function testGetEndpointHomologacaoReturnsHomologUrl(): void
    {
        $url = MunicipiosCatalog::getEndpoint('2927408', 'HOMOLOGACAO');
        $this->assertNotNull($url);
        $this->assertStringContainsString('nfsehml', $url);
    }

    /**
     * byNome('salvador') deve encontrar Salvador (case-insensitive).
     *
     * @covers ::byNome
     */
    public function testByNomeCaseInsensitive(): void
    {
        $lower = MunicipiosCatalog::byNome('salvador');
        $this->assertNotNull($lower);
        $this->assertSame('Salvador', $lower['nome']);

        $upper = MunicipiosCatalog::byNome('SALVADOR');
        $this->assertNotNull($upper);
        $this->assertSame('2927408', $upper['codigo_ibge']);
    }

    /**
     * byNome() com nome inexistente deve retornar null.
     *
     * @covers ::byNome
     */
    public function testByNomeReturnsNullForUnknown(): void
    {
        $this->assertNull(MunicipiosCatalog::byNome('Cidade Inexistente'));
    }

    /**
     * byProvedor('WEBISS') deve retornar exatamente 5 municípios.
     *
     * @covers ::byProvedor
     */
    public function testByProvedorWebissReturns5Municipios(): void
    {
        $webiss = MunicipiosCatalog::byProvedor('WEBISS');
        $this->assertCount(5, $webiss);
        foreach ($webiss as $m) {
            $this->assertSame('WEBISS', $m['provedor']);
        }
    }

    /**
     * byProvedor('BETHA') deve retornar exatamente 3 municípios (Itabuna, Ilhéus, Jequié).
     *
     * @covers ::byProvedor
     */
    public function testByProvedorBethaReturns3Municipios(): void
    {
        $this->assertCount(3, MunicipiosCatalog::byProvedor('BETHA'));
    }

    /**
     * byProvedor('SALVADOR') deve retornar exatamente 1 município.
     * byProvedor('DSF') deve retornar exatamente 1 município (Teixeira de Freitas).
     *
     * @covers ::byProvedor
     */
    public function testByProvedorSalvadorEDsfRetornam1Cada(): void
    {
        $this->assertCount(1, MunicipiosCatalog::byProvedor('SALVADOR'));
        $this->assertCount(1, MunicipiosCatalog::byProvedor('DSF'));
    }

    /**
     * forSelect() deve retornar array chave=>valor com 10 entradas
     * no formato "Nome — PROVEDOR".
     *
     * @covers ::forSelect
     */
    public function testForSelectReturns10Entries(): void
    {
        $opts = MunicipiosCatalog::forSelect();
        $this->assertCount(10, $opts);
        foreach ($opts as $ibge => $label) {
            $this->assertMatchesRegularExpression('/^\d{7}$/', (string) $ibge);
            $this->assertStringContainsString(' — ', $label);
        }
    }

    /**
     * byIbge com IBGE inexistente deve retornar null.
     *
     * @covers ::byIbge
     */
    public function testByIbgeReturnsNullForUnknown(): void
    {
        $this->assertNull(MunicipiosCatalog::byIbge('0000000'));
    }
}
