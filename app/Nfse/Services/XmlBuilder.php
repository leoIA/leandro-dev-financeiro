<?php
declare(strict_types=1);

/** @file XmlBuilder.php | @package App\Nfse\Services | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Nfse\Services;

use App\Nfse\Data\MunicipiosCatalog;
use RuntimeException;

/**
 * Geração do XML de envio da NFSe nos 4 padrões suportados.
 *
 * Padrões cobertos:
 *   - ABRASF v2.03 (Betha)        → buildAbrasf($nfse, 'BETHA')
 *   - ABRASF v2.03 (WebISS)       → buildAbrasf($nfse, 'WEBISS')
 *   - DSF próprio (Teixeira)      → buildDsf($nfse)
 *   - Salvador próprio            → buildSalvador($nfse)
 *
 * Betha e WebISS usam o mesmo namespace ABRASF
 * (`http://www.abrasf.org.br/nfse.xsd`); a diferença real entre eles
 * ocorre no adapter (SOAP action e binding), não no XML.
 *
 * DSF usa o namespace ABRASF, porém com wrapper <EnviarLoteRpsEnvio>
 * contendo <Lote Id="lote1"> com identificação do prestador e a lista de RPS.
 *
 * Salvador segue ABRASF mas adiciona 3 tags dentro de <InfRps>:
 *   - <InformacoesAdicionais> com observação
 *   - <CodigoObra> vazia
 *   - <Art> vazia
 *
 * Validação obrigatória (lança RuntimeException antes de gerar XML):
 *   - prestador_cnpj (14 dígitos)
 *   - prestador_im (não vazio)
 *   - tomador_nome (não vazio)
 *   - servico_codigo (formato "X.XX")
 *   - valor_servicos > 0
 *   - aliquota > 0
 *   - discriminacao (não vazio)
 *
 * Convenções:
 *   - Toda string de texto livre é escapada com htmlspecialchars(ENT_XML1|ENT_QUOTES, UTF-8)
 *   - CPF/CNPJ/CEP sem pontuação (XML não aceita formatado)
 *   - Valores monetários com ponto decimal (never vírgula); alíquota com 4 decimais
 *   - Datas em ISO 8601 (YYYY-MM-DDTHH:MM:SS)
 *
 * @see App\Nfse\Data\MunicipiosCatalog
 * @used-by App\Nfse\Adapters\*Adapter (chamam build() antes de Signer::signXml)
 */
class XmlBuilder
{
    /** @var string Namespace ABRASF v2.03 usado por BETHA, WEBISS, DSF e SALVADOR. */
    private const ABRASF_NS = 'http://www.abrasf.org.br/nfse.xsd';

    /**
     * Gera XML da NFSe conforme provedor do município informado nos dados.
     *
     * @param  array<string,mixed> $nfse Dados da NFSe (campos da tabela nfse).
     * @return array{xml: string, padrao: string}
     *                                   - xml: XML de envio (não assinado ainda)
     *                                   - padrao: um de ABRASF_BETHA, ABRASF_WEBISS,
     *                                             DSF_PROPRIO, SALVADOR_PROPRIO
     * @throws RuntimeException          Se provedor não suportado, dados obrigatórios
     *                                   faltando, ou provedor não implementado.
     */
    public function build(array $nfse): array
    {
        // 1) Descobre o provedor pelo IBGE do tomador (fallback para municipio_ibge).
        $municipio = MunicipiosCatalog::byIbge((string) ($nfse['tomador_codigo_municipio'] ?? ''));
        if ($municipio === null) {
            $municipio = MunicipiosCatalog::byIbge((string) ($nfse['municipio_ibge'] ?? ''));
        }
        $provedor = $municipio['provedor'] ?? 'NAO_SUPORTADO';

        // 2) Valida campos obrigatórios (comuns a todos os padrões).
        $this->validateRequired($nfse);

        // 3) Despacha para o builder específico do provedor.
        return match ($provedor) {
            'BETHA'    => $this->buildAbrasf($nfse, 'BETHA'),
            'WEBISS'   => $this->buildAbrasf($nfse, 'WEBISS'),
            'DSF'      => $this->buildDsf($nfse),
            'SALVADOR' => $this->buildSalvador($nfse),
            'SIMPLISS', 'ISSNET' => throw new RuntimeException(
                "Provedor {$provedor} não implementado para esta cidade."
            ),
            default => throw new RuntimeException("Provedor desconhecido: {$provedor}"),
        };
    }

    /**
     * Gera XML ABRASF v2.03 (Betha e WebISS — mesmo namespace, mesmo corpo).
     *
     * @param  array<string,mixed> $n        Dados da NFSe.
     * @param  string              $provedor 'BETHA' ou 'WEBISS'.
     * @return array{xml: string, padrao: string}
     */
    private function buildAbrasf(array $n, string $provedor): array
    {
        $numeroRps    = (int) ($n['numero_rps'] ?? 0);
        $serieRps     = $this->escape((string) ($n['serie_rps'] ?? '1'));
        $dataEmissao  = $this->formatDateTime((string) ($n['data_emissao'] ?? 'now'));

        $cnpjPrest    = $this->onlyDigits((string) ($n['prestador_cnpj'] ?? ''));
        $imPrest      = $this->onlyDigits((string) ($n['prestador_im'] ?? ''));
        $codMunPrest  = $this->onlyDigits((string) ($n['municipio_ibge'] ?? ''));

        $tomadorDoc   = $this->onlyDigits((string) ($n['tomador_cnpj_cpf'] ?? ''));
        $tomadorTipo  = (string) ($n['tomador_tipo_pessoa'] ?? (strlen($tomadorDoc) <= 11 ? 'FISICA' : 'JURIDICA'));
        $tomadorNome  = $this->escape((string) ($n['tomador_nome'] ?? ''));
        $tomadorEmail = $this->escape((string) ($n['tomador_email'] ?? ''));
        $tomadorEnd   = $this->escape((string) ($n['tomador_endereco'] ?? ''));
        $tomadorNum   = $this->escape((string) ($n['tomador_numero'] ?? ''));
        $tomadorComp  = $this->escape((string) ($n['tomador_complemento'] ?? ''));
        $tomadorBairro = $this->escape((string) ($n['tomador_bairro'] ?? ''));
        $tomadorMun   = $this->onlyDigits((string) ($n['tomador_codigo_municipio'] ?? ''));
        $tomadorUf    = $this->escape((string) ($n['tomador_uf'] ?? ''));
        $tomadorCep   = $this->onlyDigits((string) ($n['tomador_cep'] ?? ''));

        $servicoCod    = $this->escape((string) ($n['servico_codigo'] ?? ''));
        $discriminacao = $this->escape((string) ($n['discriminacao'] ?? ''));

        $valorServicos = $this->formatNumber((float) ($n['valor_servicos'] ?? 0));
        $valorDeducoes = $this->formatNumber((float) ($n['valor_deducoes'] ?? 0));
        $valorIss      = $this->formatNumber((float) ($n['valor_iss'] ?? 0));
        $aliquota      = $this->formatAliquota((float) ($n['aliquota'] ?? 0));
        $descIncond    = $this->formatNumber((float) ($n['desconto_incondicionado'] ?? 0));
        $descCond      = $this->formatNumber((float) ($n['desconto_condicionado'] ?? 0));
        $baseCalculo   = $this->formatNumber((float) ($n['valor_base_calculo'] ?? 0));
        $valorLiquido  = $this->formatNumber((float) ($n['valor_liquido'] ?? 0));
        $issRetido     = !empty($n['iss_retido']) ? '1' : '2';

        // CpfCnpj do tomador (FISICA → <Cpf>, JURIDICA → <Cnpj>).
        $cpfCnpjTag = $tomadorTipo === 'FISICA'
            ? "<Cpf>{$tomadorDoc}</Cpf>"
            : "<Cnpj>{$tomadorDoc}</Cnpj>";

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GerarNfseEnvio xmlns="{$this->abrascNs()}">
  <Rps>
    <InfRps Id="rps{$numeroRps}">
      <IdentificacaoRps>
        <Numero>{$numeroRps}</Numero>
        <Serie>{$serieRps}</Serie>
        <Tipo>1</Tipo>
      </IdentificacaoRps>
      <DataEmissao>{$dataEmissao}</DataEmissao>
      <NaturezaOperacao>1</NaturezaOperacao>
      <RegimeEspecialTributacao>6</RegimeEspecialTributacao>
      <OptanteSimplesNacional>1</OptanteSimplesNacional>
      <IncentivadorCultural>2</IncentivadorCultural>
      <Status>1</Status>
      <Servico>
        <Valores>
          <ValorServicos>{$valorServicos}</ValorServicos>
          <ValorDeducoes>{$valorDeducoes}</ValorDeducoes>
          <ValorPis>0.00</ValorPis>
          <ValorCofins>0.00</ValorCofins>
          <ValorInss>0.00</ValorInss>
          <ValorIr>0.00</ValorIr>
          <ValorCsll>0.00</ValorCsll>
          <IssRetido>{$issRetido}</IssRetido>
          <ValorIss>{$valorIss}</ValorIss>
          <Aliquota>{$aliquota}</Aliquota>
          <DescontoIncondicionado>{$descIncond}</DescontoIncondicionado>
          <DescontoCondicionado>{$descCond}</DescontoCondicionado>
          <BaseCalculo>{$baseCalculo}</BaseCalculo>
          <ValorLiquidoNfse>{$valorLiquido}</ValorLiquidoNfse>
        </Valores>
        <ItemListaServico>{$servicoCod}</ItemListaServico>
        <Discriminacao>{$discriminacao}</Discriminacao>
        <CodigoMunicipio>{$codMunPrest}</CodigoMunicipio>
      </Servico>
      <Prestador>
        <Cnpj>{$cnpjPrest}</Cnpj>
        <InscricaoMunicipal>{$imPrest}</InscricaoMunicipal>
      </Prestador>
      <Tomador>
        <IdentificacaoTomador>
          <CpfCnpj>{$cpfCnpjTag}</CpfCnpj>
        </IdentificacaoTomador>
        <RazaoSocial>{$tomadorNome}</RazaoSocial>
        <Endereco>
          <Endereco>{$tomadorEnd}</Endereco>
          <Numero>{$tomadorNum}</Numero>
          <Complemento>{$tomadorComp}</Complemento>
          <Bairro>{$tomadorBairro}</Bairro>
          <CodigoMunicipio>{$tomadorMun}</CodigoMunicipio>
          <Uf>{$tomadorUf}</Uf>
          <Cep>{$tomadorCep}</Cep>
        </Endereco>
        <Contato>
          <Email>{$tomadorEmail}</Email>
        </Contato>
      </Tomador>
    </InfRps>
  </Rps>
</GerarNfseEnvio>
XML;

        return [
            'xml'    => $xml,
            'padrao' => $provedor === 'WEBISS' ? 'ABRASF_WEBISS' : 'ABRASF_BETHA',
        ];
    }

    /**
     * Gera XML DSF próprio (usado por Teixeira de Freitas).
     *
     * Estrutura baseada em <EnviarLoteRpsEnvio> com lote unitário, usando o
     * mesmo namespace ABRASF. O conteúdo do <InfRps> é idêntico ao ABRASF.
     *
     * @param  array<string,mixed> $n Dados da NFSe.
     * @return array{xml: string, padrao: string}
     */
    private function buildDsf(array $n): array
    {
        $numeroRps    = (int) ($n['numero_rps'] ?? 0);
        $serieRps     = $this->escape((string) ($n['serie_rps'] ?? '1'));
        $dataEmissao  = $this->formatDateTime((string) ($n['data_emissao'] ?? 'now'));

        $cnpjPrest    = $this->onlyDigits((string) ($n['prestador_cnpj'] ?? ''));
        $imPrest      = $this->onlyDigits((string) ($n['prestador_im'] ?? ''));
        $codMunPrest  = $this->onlyDigits((string) ($n['municipio_ibge'] ?? ''));

        $tomadorDoc   = $this->onlyDigits((string) ($n['tomador_cnpj_cpf'] ?? ''));
        $tomadorTipo  = (string) ($n['tomador_tipo_pessoa'] ?? (strlen($tomadorDoc) <= 11 ? 'FISICA' : 'JURIDICA'));
        $tomadorNome  = $this->escape((string) ($n['tomador_nome'] ?? ''));
        $tomadorEmail = $this->escape((string) ($n['tomador_email'] ?? ''));
        $tomadorEnd   = $this->escape((string) ($n['tomador_endereco'] ?? ''));
        $tomadorNum   = $this->escape((string) ($n['tomador_numero'] ?? ''));
        $tomadorComp  = $this->escape((string) ($n['tomador_complemento'] ?? ''));
        $tomadorBairro = $this->escape((string) ($n['tomador_bairro'] ?? ''));
        $tomadorMun   = $this->onlyDigits((string) ($n['tomador_codigo_municipio'] ?? ''));
        $tomadorUf    = $this->escape((string) ($n['tomador_uf'] ?? ''));
        $tomadorCep   = $this->onlyDigits((string) ($n['tomador_cep'] ?? ''));

        $servicoCod    = $this->escape((string) ($n['servico_codigo'] ?? ''));
        $discriminacao = $this->escape((string) ($n['discriminacao'] ?? ''));

        $valorServicos = $this->formatNumber((float) ($n['valor_servicos'] ?? 0));
        $valorDeducoes = $this->formatNumber((float) ($n['valor_deducoes'] ?? 0));
        $valorIss      = $this->formatNumber((float) ($n['valor_iss'] ?? 0));
        $aliquota      = $this->formatAliquota((float) ($n['aliquota'] ?? 0));
        $descIncond    = $this->formatNumber((float) ($n['desconto_incondicionado'] ?? 0));
        $descCond      = $this->formatNumber((float) ($n['desconto_condicionado'] ?? 0));
        $baseCalculo   = $this->formatNumber((float) ($n['valor_base_calculo'] ?? 0));
        $valorLiquido  = $this->formatNumber((float) ($n['valor_liquido'] ?? 0));
        $issRetido     = !empty($n['iss_retido']) ? '1' : '2';

        // Número do lote (default = próprio número do RPS para lote unitário).
        $numeroLote = (int) ($n['numero_lote'] ?? $numeroRps);

        $cpfCnpjTag = $tomadorTipo === 'FISICA'
            ? "<Cpf>{$tomadorDoc}</Cpf>"
            : "<Cnpj>{$tomadorDoc}</Cnpj>";

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<EnviarLoteRpsEnvio xmlns="{$this->abrascNs()}">
  <Lote Id="lote{$numeroLote}">
    <NumeroLote>{$numeroLote}</NumeroLote>
    <Cnpj>{$cnpjPrest}</Cnpj>
    <InscricaoMunicipal>{$imPrest}</InscricaoMunicipal>
    <QuantidadeRps>1</QuantidadeRps>
    <ListaRps>
      <Rps>
        <InfRps Id="rps{$numeroRps}">
          <IdentificacaoRps>
            <Numero>{$numeroRps}</Numero>
            <Serie>{$serieRps}</Serie>
            <Tipo>1</Tipo>
          </IdentificacaoRps>
          <DataEmissao>{$dataEmissao}</DataEmissao>
          <NaturezaOperacao>1</NaturezaOperacao>
          <RegimeEspecialTributacao>6</RegimeEspecialTributacao>
          <OptanteSimplesNacional>1</OptanteSimplesNacional>
          <IncentivadorCultural>2</IncentivadorCultural>
          <Status>1</Status>
          <Servico>
            <Valores>
              <ValorServicos>{$valorServicos}</ValorServicos>
              <ValorDeducoes>{$valorDeducoes}</ValorDeducoes>
              <ValorPis>0.00</ValorPis>
              <ValorCofins>0.00</ValorCofins>
              <ValorInss>0.00</ValorInss>
              <ValorIr>0.00</ValorIr>
              <ValorCsll>0.00</ValorCsll>
              <IssRetido>{$issRetido}</IssRetido>
              <ValorIss>{$valorIss}</ValorIss>
              <Aliquota>{$aliquota}</Aliquota>
              <DescontoIncondicionado>{$descIncond}</DescontoIncondicionado>
              <DescontoCondicionado>{$descCond}</DescontoCondicionado>
              <BaseCalculo>{$baseCalculo}</BaseCalculo>
              <ValorLiquidoNfse>{$valorLiquido}</ValorLiquidoNfse>
            </Valores>
            <ItemListaServico>{$servicoCod}</ItemListaServico>
            <Discriminacao>{$discriminacao}</Discriminacao>
            <CodigoMunicipio>{$codMunPrest}</CodigoMunicipio>
          </Servico>
          <Prestador>
            <Cnpj>{$cnpjPrest}</Cnpj>
            <InscricaoMunicipal>{$imPrest}</InscricaoMunicipal>
          </Prestador>
          <Tomador>
            <IdentificacaoTomador>
              <CpfCnpj>{$cpfCnpjTag}</CpfCnpj>
            </IdentificacaoTomador>
            <RazaoSocial>{$tomadorNome}</RazaoSocial>
            <Endereco>
              <Endereco>{$tomadorEnd}</Endereco>
              <Numero>{$tomadorNum}</Numero>
              <Complemento>{$tomadorComp}</Complemento>
              <Bairro>{$tomadorBairro}</Bairro>
              <CodigoMunicipio>{$tomadorMun}</CodigoMunicipio>
              <Uf>{$tomadorUf}</Uf>
              <Cep>{$tomadorCep}</Cep>
            </Endereco>
            <Contato>
              <Email>{$tomadorEmail}</Email>
            </Contato>
          </Tomador>
        </InfRps>
      </Rps>
    </ListaRps>
  </Lote>
</EnviarLoteRpsEnvio>
XML;

        return [
            'xml'    => $xml,
            'padrao' => 'DSF_PROPRIO',
        ];
    }

    /**
     * Gera XML próprio de Salvador (ABRASF com extensões).
     *
     * Variações em relação ao ABRASF puro:
     *   - Tag <InformacoesAdicionais> dentro de <InfRps> com observação
     *   - Tag <CodigoObra> vazia
     *   - Tag <Art> vazia
     *
     * @param  array<string,mixed> $n Dados da NFSe.
     * @return array{xml: string, padrao: string}
     */
    private function buildSalvador(array $n): array
    {
        $numeroRps    = (int) ($n['numero_rps'] ?? 0);
        $serieRps     = $this->escape((string) ($n['serie_rps'] ?? '1'));
        $dataEmissao  = $this->formatDateTime((string) ($n['data_emissao'] ?? 'now'));

        $cnpjPrest    = $this->onlyDigits((string) ($n['prestador_cnpj'] ?? ''));
        $imPrest      = $this->onlyDigits((string) ($n['prestador_im'] ?? ''));
        $codMunPrest  = $this->onlyDigits((string) ($n['municipio_ibge'] ?? ''));

        $tomadorDoc   = $this->onlyDigits((string) ($n['tomador_cnpj_cpf'] ?? ''));
        $tomadorTipo  = (string) ($n['tomador_tipo_pessoa'] ?? (strlen($tomadorDoc) <= 11 ? 'FISICA' : 'JURIDICA'));
        $tomadorNome  = $this->escape((string) ($n['tomador_nome'] ?? ''));
        $tomadorEmail = $this->escape((string) ($n['tomador_email'] ?? ''));
        $tomadorEnd   = $this->escape((string) ($n['tomador_endereco'] ?? ''));
        $tomadorNum   = $this->escape((string) ($n['tomador_numero'] ?? ''));
        $tomadorComp  = $this->escape((string) ($n['tomador_complemento'] ?? ''));
        $tomadorBairro = $this->escape((string) ($n['tomador_bairro'] ?? ''));
        $tomadorMun   = $this->onlyDigits((string) ($n['tomador_codigo_municipio'] ?? ''));
        $tomadorUf    = $this->escape((string) ($n['tomador_uf'] ?? ''));
        $tomadorCep   = $this->onlyDigits((string) ($n['tomador_cep'] ?? ''));

        $servicoCod    = $this->escape((string) ($n['servico_codigo'] ?? ''));
        $discriminacao = $this->escape((string) ($n['discriminacao'] ?? ''));
        // Observação adicional: usa campo dedicado se presente, senão reaproveita discriminacao.
        $infoAdicionais = $this->escape((string) ($n['informacoes_adicionais'] ?? $n['discriminacao'] ?? ''));

        $valorServicos = $this->formatNumber((float) ($n['valor_servicos'] ?? 0));
        $valorDeducoes = $this->formatNumber((float) ($n['valor_deducoes'] ?? 0));
        $valorIss      = $this->formatNumber((float) ($n['valor_iss'] ?? 0));
        $aliquota      = $this->formatAliquota((float) ($n['aliquota'] ?? 0));
        $descIncond    = $this->formatNumber((float) ($n['desconto_incondicionado'] ?? 0));
        $descCond      = $this->formatNumber((float) ($n['desconto_condicionado'] ?? 0));
        $baseCalculo   = $this->formatNumber((float) ($n['valor_base_calculo'] ?? 0));
        $valorLiquido  = $this->formatNumber((float) ($n['valor_liquido'] ?? 0));
        $issRetido     = !empty($n['iss_retido']) ? '1' : '2';

        $cpfCnpjTag = $tomadorTipo === 'FISICA'
            ? "<Cpf>{$tomadorDoc}</Cpf>"
            : "<Cnpj>{$tomadorDoc}</Cnpj>";

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<GerarNfseEnvio xmlns="{$this->abrascNs()}">
  <Rps>
    <InfRps Id="rps{$numeroRps}">
      <IdentificacaoRps>
        <Numero>{$numeroRps}</Numero>
        <Serie>{$serieRps}</Serie>
        <Tipo>1</Tipo>
      </IdentificacaoRps>
      <DataEmissao>{$dataEmissao}</DataEmissao>
      <NaturezaOperacao>1</NaturezaOperacao>
      <RegimeEspecialTributacao>6</RegimeEspecialTributacao>
      <OptanteSimplesNacional>1</OptanteSimplesNacional>
      <IncentivadorCultural>2</IncentivadorCultural>
      <Status>1</Status>
      <Servico>
        <Valores>
          <ValorServicos>{$valorServicos}</ValorServicos>
          <ValorDeducoes>{$valorDeducoes}</ValorDeducoes>
          <ValorPis>0.00</ValorPis>
          <ValorCofins>0.00</ValorCofins>
          <ValorInss>0.00</ValorInss>
          <ValorIr>0.00</ValorIr>
          <ValorCsll>0.00</ValorCsll>
          <IssRetido>{$issRetido}</IssRetido>
          <ValorIss>{$valorIss}</ValorIss>
          <Aliquota>{$aliquota}</Aliquota>
          <DescontoIncondicionado>{$descIncond}</DescontoIncondicionado>
          <DescontoCondicionado>{$descCond}</DescontoCondicionado>
          <BaseCalculo>{$baseCalculo}</BaseCalculo>
          <ValorLiquidoNfse>{$valorLiquido}</ValorLiquidoNfse>
        </Valores>
        <ItemListaServico>{$servicoCod}</ItemListaServico>
        <Discriminacao>{$discriminacao}</Discriminacao>
        <CodigoMunicipio>{$codMunPrest}</CodigoMunicipio>
      </Servico>
      <Prestador>
        <Cnpj>{$cnpjPrest}</Cnpj>
        <InscricaoMunicipal>{$imPrest}</InscricaoMunicipal>
      </Prestador>
      <Tomador>
        <IdentificacaoTomador>
          <CpfCnpj>{$cpfCnpjTag}</CpfCnpj>
        </IdentificacaoTomador>
        <RazaoSocial>{$tomadorNome}</RazaoSocial>
        <Endereco>
          <Endereco>{$tomadorEnd}</Endereco>
          <Numero>{$tomadorNum}</Numero>
          <Complemento>{$tomadorComp}</Complemento>
          <Bairro>{$tomadorBairro}</Bairro>
          <CodigoMunicipio>{$tomadorMun}</CodigoMunicipio>
          <Uf>{$tomadorUf}</Uf>
          <Cep>{$tomadorCep}</Cep>
        </Endereco>
        <Contato>
          <Email>{$tomadorEmail}</Email>
        </Contato>
      </Tomador>
      <InformacoesAdicionais>{$infoAdicionais}</InformacoesAdicionais>
      <CodigoObra></CodigoObra>
      <Art></Art>
    </InfRps>
  </Rps>
</GerarNfseEnvio>
XML;

        return [
            'xml'    => $xml,
            'padrao' => 'SALVADOR_PROPRIO',
        ];
    }

    /**
     * Valida campos obrigatórios antes de gerar o XML (comuns a todos os padrões).
     *
     * @param  array<string,mixed> $n Dados da NFSe.
     * @throws RuntimeException Se qualquer campo obrigatório estiver faltando ou inválido.
     */
    private function validateRequired(array $n): void
    {
        $cnpj = $this->onlyDigits((string) ($n['prestador_cnpj'] ?? ''));
        if (strlen($cnpj) !== 14) {
            throw new RuntimeException('Campo obrigatório ausente: prestador_cnpj');
        }
        $im = $this->onlyDigits((string) ($n['prestador_im'] ?? ''));
        if ($im === '') {
            throw new RuntimeException('Campo obrigatório ausente: prestador_im');
        }
        $tomadorNome = trim((string) ($n['tomador_nome'] ?? ''));
        if ($tomadorNome === '') {
            throw new RuntimeException('Campo obrigatório ausente: tomador_nome');
        }
        $servicoCod = trim((string) ($n['servico_codigo'] ?? ''));
        if (!preg_match('/^\d{1,2}\.\d{2}$/', $servicoCod)) {
            throw new RuntimeException('Campo obrigatório ausente: servico_codigo');
        }
        $valorServicos = (float) ($n['valor_servicos'] ?? 0);
        if ($valorServicos <= 0) {
            throw new RuntimeException('Campo obrigatório ausente: valor_servicos');
        }
        $aliquota = (float) ($n['aliquota'] ?? 0);
        if ($aliquota <= 0) {
            throw new RuntimeException('Campo obrigatório ausente: aliquota');
        }
        $discriminacao = trim((string) ($n['discriminacao'] ?? ''));
        if ($discriminacao === '') {
            throw new RuntimeException('Campo obrigatório ausente: discriminacao');
        }
    }

    /**
     * Retorna o namespace ABRASF v2.03 (constante encapsulada para uso nos heredocs).
     *
     * @return string
     */
    private function abrascNs(): string
    {
        return self::ABRASF_NS;
    }

    /**
     * Escapa string para uso seguro em XML (ENT_XML1 + ENT_QUOTES, UTF-8).
     *
     * @param  string $s String original.
     * @return string    String escapada.
     */
    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Remove todos os caracteres não numéricos de uma string.
     * Usado para CPF/CNPJ/CEP/IM — XML não aceita pontuação nestes campos.
     *
     * @param  string $s String original (ex: "12.345.678/0001-90").
     * @return string    Apenas dígitos (ex: "12345678000190").
     */
    private function onlyDigits(string $s): string
    {
        return preg_replace('/\D/', '', $s) ?? '';
    }

    /**
     * Formata número decimal no padrão XML (ponto como separador, 2 casas).
     *
     * @param  float $n Valor numérico.
     * @return string   Valor formatado (ex: "1234.56").
     */
    private function formatNumber(float $n): string
    {
        return number_format($n, 2, '.', '');
    }

    /**
     * Formata alíquota com 4 casas decimais (ex: 0.0300 = 3%).
     *
     * @param  float $n Alíquota (fração decimal, ex: 0.03).
     * @return string   Valor formatado (ex: "0.0300").
     */
    private function formatAliquota(float $n): string
    {
        return number_format($n, 4, '.', '');
    }

    /**
     * Formata data/hora em ISO 8601 (YYYY-MM-DDTHH:MM:SS).
     *
     * @param  string $isoDateTime Data/hora em qualquer formato aceito por strtotime().
     * @return string               Data/hora em ISO 8601.
     */
    private function formatDateTime(string $isoDateTime): string
    {
        return date('Y-m-d\TH:i:s', strtotime($isoDateTime));
    }
}
