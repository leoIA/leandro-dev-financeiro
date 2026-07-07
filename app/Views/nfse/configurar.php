<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $municipios Lista de municipios_nfse ativos.
 * @var array  $config     Configurações NFSe (chaves prefixadas nfse_*).
 */
use App\Core\Config;

$municipios = $municipios ?? [];
$config     = $config     ?? [];

$g = static function (string $key, $default = '') use ($config) {
    return $config[$key] ?? $default;
};

// CNPJ do prestador (somente leitura — vem da config empresa).
$prestadorCnpj = (string) (Config::get('empresa_cnpj', '') ?? '');
$prestadorNome = (string) (Config::get('empresa_razao_social', 'MM Construtora') ?? 'MM Construtora');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-gear me-2 text-primary"></i><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=nfse" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>

    <form method="post" action="index.php?route=nfse/configurar" class="needs-validation" novalidate>
        <?= \App\Core\Csrf::field() ?>

        <div class="row g-3">
            <!-- Município Ativo + Ambiente -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-geo-alt me-1 text-primary"></i>Município Ativo</h2>
                    </div>
                    <div class="card-body">
                        <label for="nfse_municipio_ativo" class="form-label">Selecione o município da prestação</label>
                        <select class="form-select" id="nfse_municipio_ativo" name="nfse_municipio_ativo">
                            <option value="">— Nenhum —</option>
                            <?php foreach ($municipios as $m): ?>
                                <option value="<?= htmlspecialchars($m['codigo_ibge'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($g('nfse_municipio_ativo') === ($m['codigo_ibge'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    — <?= htmlspecialchars($m['provedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Apenas municípios Bahia suportados pelo catálogo (10 maiores cidades).</small>

                        <hr>
                        <label class="form-label">Prestador (somente leitura)</label>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <input type="text" class="form-control form-control-sm" readonly
                                       value="<?= htmlspecialchars($prestadorNome, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-4">
                                <input type="text" class="form-control form-control-sm" readonly
                                       value="<?= htmlspecialchars($prestadorCnpj !== '' ? $prestadorCnpj : 'CNPJ não configurado', ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                        <small class="form-text text-muted">Para alterar o CNPJ/razão social do prestador, acesse <a href="index.php?route=configuracoes">Configurações da Empresa</a>.</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-shield-lock me-1 text-primary"></i>Ambiente</h2>
                    </div>
                    <div class="card-body">
                        <p class="form-label">Selecione o ambiente de emissão</p>
                        <div class="form-check mb-2 p-3 border rounded <?= ($g('nfse_ambiente') === 'HOMOLOGACAO') ? 'border-warning bg-warning-subtle' : 'border-light' ?>">
                            <input class="form-check-input" type="radio" name="nfse_ambiente" id="amb_hom" value="HOMOLOGACAO"
                                   <?= ($g('nfse_ambiente', 'HOMOLOGACAO') === 'HOMOLOGACAO') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="amb_hom">
                                <i class="bi bi-flask text-warning me-1"></i>
                                <strong>Homologação</strong>
                                <span class="badge bg-warning text-dark ms-1">Testes</span>
                                <div class="small text-muted">NFSes emitidas <em>não</em> têm validade fiscal. Use para testes de integração.</div>
                            </label>
                        </div>
                        <div class="form-check p-3 border rounded <?= ($g('nfse_ambiente') === 'PRODUCAO') ? 'border-success bg-success-subtle' : 'border-light' ?>">
                            <input class="form-check-input" type="radio" name="nfse_ambiente" id="amb_prod" value="PRODUCAO"
                                   <?= ($g('nfse_ambiente') === 'PRODUCAO') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="amb_prod">
                                <i class="bi bi-broadcast text-success me-1"></i>
                                <strong>Produção</strong>
                                <span class="badge bg-danger ms-1">Real</span>
                                <div class="small text-muted">Cada emissão é uma nota fiscal real, enviada à prefeitura e com efeitos tributários.</div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RPS -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-123 me-1 text-primary"></i>Configuração de RPS</h2>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="nfse_serie_rps" class="form-label">Série do RPS</label>
                                <input type="text" class="form-control" id="nfse_serie_rps" name="nfse_serie_rps" maxlength="5"
                                       value="<?= htmlspecialchars($g('nfse_serie_rps', '1'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Default: 1.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="nfse_proximo_numero_rps" class="form-label">Próximo Nº RPS</label>
                                <input type="number" class="form-control" id="nfse_proximo_numero_rps" name="nfse_proximo_numero_rps" min="1"
                                       value="<?= (int) $g('nfse_proximo_numero_rps', 1) ?>">
                                <small class="form-text text-muted">Incrementado automaticamente a cada emissão.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="nfse_aliquota_default" class="form-label">Alíquota Default</label>
                                <input type="text" class="form-control" id="nfse_aliquota_default" name="nfse_aliquota_default" inputmode="decimal"
                                       value="<?= htmlspecialchars($g('nfse_aliquota_default', '0.0300'), ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="0.0300">
                                <small class="form-text text-muted">Decimal 4 casas (0.0300 = 3%).</small>
                            </div>
                            <div class="col-md-12">
                                <label for="nfse_servico_codigo_default" class="form-label">Código de Serviço Default (LC 116)</label>
                                <input type="text" class="form-control" id="nfse_servico_codigo_default" name="nfse_servico_codigo_default" maxlength="10"
                                       value="<?= htmlspecialchars($g('nfse_servico_codigo_default'), ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Ex: 7.02">
                                <small class="form-text text-muted">Pré-preenche o formulário de emissão.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inscrição Municipal -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-hash me-1 text-primary"></i>Inscrição Municipal</h2>
                    </div>
                    <div class="card-body">
                        <label for="nfse_inscricao_municipal" class="form-label">Inscrição Municipal do Prestador (MM Construtora)</label>
                        <input type="text" class="form-control" id="nfse_inscricao_municipal" name="nfse_inscricao_municipal" maxlength="20"
                               value="<?= htmlspecialchars($g('nfse_inscricao_municipal'), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Ex: 123456-7">
                        <small class="form-text text-muted">Inscrição Municipal ativa na prefeitura do município selecionado.</small>

                        <div class="alert alert-info mt-3 mb-0 small">
                            <i class="bi bi-info-circle me-1"></i>
                            A inscrição municipal é fornecida pela prefeitura e geralmente está vinculada ao CNPJ da empresa.
                            Sem IM, a maioria das prefeituras recusa a emissão.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 my-3">
            <a href="index.php?route=nfse" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i> Salvar Configurações</button>
        </div>
    </form>
</div>
