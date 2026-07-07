<?php
declare(strict_types=1);

/**
 * @file    XmlBuilderTest.php
 * @package Tests\Nfse
 * @since   2026.07.08
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Testes PHPUnit para App\Nfse\Services\XmlBuilder.
 *
 * Cobre:
 *   - Geração XML ABRASF para Betha (Itabuna 2914802)
 *   - Geração XML ABRASF para WebISS (Feira de Santana 2910800)
 *   - Geração XML DSF próprio (Teixeira de Freitas 2917900)
 *   - Geração XML Salvador próprio (Salvador 2927408)
 *   - Exceção quando campos obrigatórios faltam
 *   - Exceção quando provedor não suportado (SIMPLISS)
 *
 * Estratégia:
 *   - Cada teste usa sampleNfse($ibge) com dados completos e válidos
 *   - Asserções verificam tags específicas de cada padrão XML
 *   - Não depende de DB nem de HTTP real
 */

namespace Tests\Nfse;

use App\Nfse\Services\XmlBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @coversDefaultClass \App\Nfse\Services\XmlBuilder
 */
class XmlBuilderTest extends TestCase
{
    /**
     * Gera um array $nfse completo e válido para uso em todos os testes.
     *
     * @param  string $ibge Código IBGE do município do tomador (define provedor).
     * @return array<string,mixed>
     */
    private function sampleNfse(string $ibge): array
    {
        return [
            'numero_rps'                => 1,
            'serie_rps'                 => '1',
            'data_emissao'              => '2026-07-08 10:00:00',
            'prestador_cnpj'            => '12345678000190',
            'prestador_im'              => '123456',
            'prestador_razao_social'    => 'MM Construtora LTDA',
            'tomador_tipo_pessoa'       => 'JURIDICA',
            'tomador_nome'              => 'Cliente Teste LTDA',
            'tomador_cnpj_cpf'          => '98765432000110',
            'tomador_email'             => 'cliente@teste.com',
            'tomador_cep'               => '40000000',
            'tomador_endereco'          => 'Rua Teste',
            'tomador_numero'            => '100',
            'tomador_bairro'            => 'Centro',
            'tomador_cidade'            => 'Salvador',
            'tomador_uf'                => 'BA',
            'tomador_codigo_municipio'  => $ibge,
            'servico_codigo'            => '7.02',
            'servico_descricao'         => 'Serviços de informática',
            'discriminacao'             => 'Desenvolvimento de sistema financeiro',
            'valor_servicos'            => 5000.00,
            'valor_deducoes'            => 0.00,
            'aliquota'                  => 0.0300,
            'valor_iss'                 => 150.00,
            'valor_base_calculo'        => 5000.00,
            'valor_liquido'             => 4850.00,
            'iss_retido'                => 0,
            'desconto_incondicionado'   => 0.00,
            'desconto_condicionado'     => 0.00,
            'municipio_ibge'            => $ibge,
        ];
    }

    /**
     * Betha deve produzir XML ABRASF com <GerarNfseEnvio>, <InfRps>,
     * <IdentificacaoRps>, <ValorServicos>.
     *
     * @covers ::build
     * @covers ::buildAbrasf
     */
    public function testBuildXmlBethaProducesValidAbrasfXml(): void
    {
        $builder = new XmlBuilder();
        $result = $builder->build($this->sampleNfse('2914802')); // Itabuna

        $this->assertSame('ABRASF_BETHA', $result['padrao']);
        $xml = $result['xml'];

        $this->assertStringContainsString('<GerarNfseEnvio', $xml);
        $this->assertStringContainsString('xmlns="http://www.abrasf.org.br/nfse.xsd"', $xml);
        $this->assertStringContainsString('<InfRps Id="rps1">', $xml);
        $this->assertStringContainsString('<IdentificacaoRps>', $xml);
        $this->assertStringContainsString('<ValorServicos>5000.00</ValorServicos>', $xml);
        $this->assertStringContainsString('<Aliquota>0.0300</Aliquota>', $xml);
        $this->assertStringContainsString('<InscricaoMunicipal>123456</InscricaoMunicipal>', $xml);
        $this->assertStringContainsString('<Cnpj>12345678000190</Cnpj>', $xml);
        // ABRASF puro (Betha): NÃO deve conter <InformacoesAdicionais>.
        $this->assertStringNotContainsString('<InformacoesAdicionais>', $xml);
    }

    /**
     * WebISS deve produzir XML ABRASF equivalente ao Betha (mesmo namespace).
     *
     * @covers ::build
     * @covers ::buildAbrasf
     */
    public function testBuildXmlWebissProducesValidAbrasfXml(): void
    {
        $builder = new XmlBuilder();
        $result = $builder->build($this->sampleNfse('2910800')); // Feira de Santana

        $this->assertSame('ABRASF_WEBISS', $result['padrao']);
        $xml = $result['xml'];

        $this->assertStringContainsString('<GerarNfseEnvio', $xml);
        $this->assertStringContainsString('xmlns="http://www.abrasf.org.br/nfse.xsd"', $xml);
        $this->assertStringContainsString('<InfRps Id="rps1">', $xml);
        $this->assertStringContainsString('<IdentificacaoRps>', $xml);
        $this->assertStringContainsString('<ValorServicos>5000.00</ValorServicos>', $xml);
        $this->assertStringContainsString('<RazaoSocial>Cliente Teste LTDA</RazaoSocial>', $xml);
        // Tomador JURIDICA → tag <Cnpj> (não <Cpf>).
        $this->assertStringContainsString('<Cnpj>98765432000110</Cnpj>', $xml);
        $this->assertStringNotContainsString('<Cpf>98765432000110</Cpf>', $xml);
    }

    /**
     * DSF (Teixeira de Freitas) deve produzir XML com <EnviarLoteRpsEnvio>.
     *
     * @covers ::build
     * @covers ::buildDsf
     */
    public function testBuildXmlDsfProducesValidDsfXml(): void
    {
        $builder = new XmlBuilder();
        $result = $builder->build($this->sampleNfse('2917900')); // Teixeira de Freitas

        $this->assertSame('DSF_PROPRIO', $result['padrao']);
        $xml = $result['xml'];

        $this->assertStringContainsString('<EnviarLoteRpsEnvio', $xml);
        $this->assertStringContainsString('<Lote Id="lote1">', $xml);
        $this->assertStringContainsString('<NumeroLote>1</NumeroLote>', $xml);
        $this->assertStringContainsString('<QuantidadeRps>1</QuantidadeRps>', $xml);
        $this->assertStringContainsString('<ListaRps>', $xml);
        $this->assertStringContainsString('<InfRps Id="rps1">', $xml);
        $this->assertStringContainsString('<ValorServicos>5000.00</ValorServicos>', $xml);
    }

    /**
     * Salvador deve produzir XML ABRASF com extensões próprias
     * (<InformacoesAdicionais>, <CodigoObra>, <Art>).
     *
     * @covers ::build
     * @covers ::buildSalvador
     */
    public function testBuildXmlSalvadorProducesValidSalvadorXml(): void
    {
        $builder = new XmlBuilder();
        $result = $builder->build($this->sampleNfse('2927408')); // Salvador

        $this->assertSame('SALVADOR_PROPRIO', $result['padrao']);
        $xml = $result['xml'];

        $this->assertStringContainsString('<GerarNfseEnvio', $xml);
        $this->assertStringContainsString('<InformacoesAdicionais>', $xml);
        $this->assertStringContainsString('<CodigoObra></CodigoObra>', $xml);
        $this->assertStringContainsString('<Art></Art>', $xml);
        $this->assertStringContainsString('<InfRps Id="rps1">', $xml);
        $this->assertStringContainsString('<ValorServicos>5000.00</ValorServicos>', $xml);
        // Como informacoes_adicionais não foi fornecido, deve reaproveitar discriminacao.
        $this->assertStringContainsString(
            '<InformacoesAdicionais>Desenvolvimento de sistema financeiro</InformacoesAdicionais>',
            $xml
        );
    }

    /**
     * Falta de prestador_cnpj (ou CNPJ com menos de 14 dígitos) deve disparar
     * RuntimeException.
     *
     * @covers ::build
     * @covers ::validateRequired
     */
    public function testBuildXmlWithMissingRequiredFieldsThrowsException(): void
    {
        $builder = new XmlBuilder();
        $data = $this->sampleNfse('2910800');
        $data['prestador_cnpj'] = ''; // sem CNPJ

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prestador_cnpj');
        $builder->build($data);
    }

    /**
     * Provedor SIMPLISS (stub não-implementado) deve disparar RuntimeException.
     *
     * Como o catálogo real não contém SIMPLISS, simulamos o provedor inserindo
     * um município fictício via override da fonte de dados (alterando o IBGE
     * para um valor inexistente, que cairá no default 'Provedor desconhecido').
     *
     * @covers ::build
     */
    public function testBuildXmlWithUnsupportedProvedorThrowsException(): void
    {
        $builder = new XmlBuilder();
        $data = $this->sampleNfse('9999999'); // IBGE inexistente no catálogo
        // dados permanecem válidos para passar pelo validateRequired(),
        // mas o provedor será NAO_SUPORTADO → branch default do match.

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Provedor (desconhecido|nao implementado)/i');
        $builder->build($data);
    }

    /**
     * Falta de aliquota (zero) deve disparar RuntimeException.
     *
     * @covers ::build
     * @covers ::validateRequired
     */
    public function testBuildXmlWithZeroAliquotaThrowsException(): void
    {
        $builder = new XmlBuilder();
        $data = $this->sampleNfse('2910800');
        $data['aliquota'] = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('aliquota');
        $builder->build($data);
    }

    /**
     * servico_codigo fora do formato "X.XX" deve disparar RuntimeException.
     *
     * @covers ::build
     * @covers ::validateRequired
     */
    public function testBuildXmlWithInvalidServicoCodigoThrowsException(): void
    {
        $builder = new XmlBuilder();
        $data = $this->sampleNfse('2910800');
        $data['servico_codigo'] = 'INVALIDO';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('servico_codigo');
        $builder->build($data);
    }

    /**
     * valor_servicos <= 0 deve disparar RuntimeException.
     *
     * @covers ::build
     * @covers ::validateRequired
     */
    public function testBuildXmlWithZeroValorServicosThrowsException(): void
    {
        $builder = new XmlBuilder();
        $data = $this->sampleNfse('2910800');
        $data['valor_servicos'] = 0;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('valor_servicos');
        $builder->build($data);
    }
}
