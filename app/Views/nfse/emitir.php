<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $municipios      Lista de municipios_nfse ativos.
 * @var array  $contas          Lista de contas ativas.
 * @var array  $clientes        Lista de clientes/fornecedores ativos.
 * @var int    $proximoRps      Próximo número de RPS a ser usado.
 * @var string $serieRps        Série do RPS (default '1').
 * @var string $aliquotaDefault Alíquota default (ex: '0.03').
 * @var string $servicoDefault  Código de serviço default (LC 116).
 * @var string $ambiente        HOMOLOGACAO | PRODUCAO.
 * @var array  $old             Valores para repopulação após erro.
 */
use App\Core\Config;

$municipios      = $municipios      ?? [];
$contas          = $contas          ?? [];
$clientes        = $clientes        ?? [];
$proximoRps      = $proximoRps      ?? 1;
$serieRps        = $serieRps        ?? '1';
$aliquotaDefault = $aliquotaDefault ?? '0.03';
$servicoDefault  = $servicoDefault  ?? '';
$ambiente        = $ambiente        ?? 'HOMOLOGACAO';
$old             = $old             ?? [];

// Dados do prestador (somente leitura) — MM Construtora.
$prestadorCnpj = (string) (Config::get('empresa_cnpj', '') ?? '');
$prestadorIm   = (string) (Config::get('nfse_inscricao_municipal', '') ?? '');
$prestadorNome = (string) (Config::get('empresa_razao_social', 'MM Construtora') ?? 'MM Construtora');

// Repopulação helper.
$v = static function (string $key, $default = '') use ($old) {
    return $old[$key] ?? $default;
};

// Lista de serviços LC 116 (itens mais comuns — usado no datalist).
$listaServicos = [
    '1.01'  => '1.01 — Auditoria contábil',
    '1.02'  => '1.02 — Perícia contábil',
    '1.07'  => '1.07 — Escrituração contábil',
    '2.01'  => '2.01 — Medicina',
    '4.01'  => '4.01 — Construção civil',
    '4.02'  => '4.02 — Demolição',
    '4.03'  => '4.03 — Reparos e reformas',
    '7.02'  => '7.02 — Engenharia civil',
    '7.04'  => '7.04 — Consultoria de engenharia',
    '7.05'  => '7.05 — Execução de projetos',
    '8.01'  => '8.01 — Serviços de contabilidade',
    '10.01' => '10.01 — Consultoria em tecnologia',
    '11.01' => '11.01 — Consultoria em TI',
    '17.10' => '17.10 — Manutenção de software',
    '22.01' => '22.01 — Serviços de propaganda',
    '24.01' => '24.01 — Serviços de limpeza',
    '25.02' => '25.02 — Transporte de cargas',
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-file-earmark-plus me-2 text-primary"></i><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=nfse" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>

    <?php if ($ambiente === 'HOMOLOGACAO'): ?>
        <div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-1"></i> <strong>Atenção:</strong> ambiente de <strong>Homologação</strong> ativo. NFSes emitidas aqui <em>não</em> têm validade fiscal. Para emitir notas reais, alterne para Produção em Configurar.</div>
    <?php else: ?>
        <div class="alert alert-danger py-2"><i class="bi bi-broadcast me-1"></i> <strong>Produção ativa.</strong> Cada emissão é uma nota fiscal real enviada à prefeitura.</div>
    <?php endif; ?>

    <form method="post" action="index.php?route=nfse/emitir" class="needs-validation" novalidate>
        <?= \App\Core\Csrf::field() ?>

        <!-- PRESTADOR (readonly) -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h2 class="h6 mb-0"><i class="bi bi-building me-1 text-primary"></i>Prestador</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small">Razão Social</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= htmlspecialchars($prestadorNome, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">CNPJ</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= htmlspecialchars($prestadorCnpj !== '' ? $prestadorCnpj : 'Não configurado', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Inscrição Municipal</label>
                        <input type="text" class="form-control form-control-sm" readonly
                               value="<?= htmlspecialchars($prestadorIm !== '' ? $prestadorIm : 'Não configurado', ENT_QUOTES, 'UTF-8') ?>">
                        <?php if ($prestadorIm === ''): ?>
                            <small class="text-danger">Configure a IM em /nfse/configurar antes de emitir.</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOMADOR -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h2 class="h6 mb-0"><i class="bi bi-person me-1 text-primary"></i>Tomador</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tipo de Pessoa <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tp_fisica" value="FISICA" required
                                   <?= ($v('tipo_pessoa', 'FISICA') === 'FISICA') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tp_fisica">Pessoa Física</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tp_juridica" value="JURIDICA" required
                                   <?= ($v('tipo_pessoa') === 'JURIDICA') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tp_juridica">Pessoa Jurídica</label>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="tomador_nome" class="form-label">
                            <span data-label-nome>Nome / Razão Social</span> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="tomador_nome" name="tomador_nome" required maxlength="200"
                               list="listaClientes"
                               value="<?= htmlspecialchars($v('tomador_nome'), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Selecione um cliente cadastrado ou digite">
                        <datalist id="listaClientes">
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?= htmlspecialchars($cli['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-text text-muted">Use autocomplete para preencher CPF/CNPJ automaticamente.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="tomador_cnpj_cpf" class="form-label">CPF / CNPJ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tomador_cnpj_cpf" name="tomador_cnpj_cpf" data-mask="cpf-cnpj" required
                               value="<?= htmlspecialchars($v('tomador_cnpj_cpf'), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="000.000.000-00">
                    </div>

                    <div class="col-md-6">
                        <label for="tomador_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="tomador_email" name="tomador_email" maxlength="150"
                               value="<?= htmlspecialchars($v('tomador_email'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="tomador_cep" class="form-label">CEP</label>
                        <input type="text" class="form-control" id="cep" name="tomador_cep" data-mask="cep" maxlength="9"
                               value="<?= htmlspecialchars($v('tomador_cep'), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="00000-000">
                        <small class="form-text text-muted">Digite o CEP e saia do campo para buscar.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="tomador_endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="tomador_endereco" maxlength="200"
                               value="<?= htmlspecialchars($v('tomador_endereco'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="tomador_numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="tomador_numero" maxlength="20"
                               value="<?= htmlspecialchars($v('tomador_numero'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="tomador_bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="tomador_bairro" maxlength="100"
                               value="<?= htmlspecialchars($v('tomador_bairro'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="tomador_cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="tomador_cidade" maxlength="100"
                               value="<?= htmlspecialchars($v('tomador_cidade'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="tomador_uf" class="form-label">UF</label>
                        <select class="form-select" id="uf" name="tomador_uf">
                            <option value="">—</option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf): ?>
                                <option value="<?= $uf ?>" <?= ($v('tomador_uf') === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- SERVIÇO -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h2 class="h6 mb-0"><i class="bi bi-briefcase me-1 text-primary"></i>Serviço</h2>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="servico_codigo" class="form-label">Código LC 116 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="servico_codigo" name="servico_codigo" required maxlength="10"
                               list="listaServicos"
                               value="<?= htmlspecialchars($v('servico_codigo', $servicoDefault), ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Ex: 7.02">
                        <datalist id="listaServicos">
                            <?php foreach ($listaServicos as $cod => $desc): ?>
                                <option value="<?= htmlspecialchars($cod, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-text text-muted">Lista de Serviços LC 116/2003.</small>
                    </div>

                    <div class="col-md-9">
                        <label for="discriminacao" class="form-label">Discriminação do Serviço <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="discriminacao" name="discriminacao" rows="2" required maxlength="2000"
                                  placeholder="Descrição detalhada do serviço prestado..."><?= htmlspecialchars($v('discriminacao'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-md-3">
                        <label for="valor_servicos" class="form-label">Valor dos Serviços (R$) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor_servicos" name="valor_servicos" data-mask="money" required
                                   value="<?= htmlspecialchars($v('valor_servicos', '0,00'), ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="valor_deducoes" class="form-label">Deduções (R$)</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor_deducoes" name="valor_deducoes" data-mask="money"
                                   value="<?= htmlspecialchars($v('valor_deducoes', '0,00'), ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <small class="form-text text-muted">Default: 0,00.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="aliquota" class="form-label">Alíquota ISS <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">%</span>
                            <input type="text" class="form-control text-end" id="aliquota" name="aliquota" required
                                   value="<?= htmlspecialchars($v('aliquota', $aliquotaDefault), ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal"
                                   placeholder="0.0300 = 3%">
                        </div>
                        <small class="form-text text-muted">Formato decimal: 0.0300 = 3%.</small>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="iss_retido" name="iss_retido" value="1"
                                   <?= ($v('iss_retido') === 1 || $v('iss_retido') === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="iss_retido">ISS Retido pelo Tomador</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- VÍNCULO -->
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <h2 class="h6 mb-0"><i class="bi bi-link-45deg me-1 text-primary"></i>Vínculo Financeiro</h2>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="conta_id" class="form-label">Conta para Crédito <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?= (int) ($c['id'] ?? 0) ?>" <?= ((int) $v('conta_id') === (int) ($c['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">Um lançamento de receita será criado automaticamente ao autorizar.</small>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label text-muted small">Município da Prestação</label>
                        <select class="form-select form-select-sm" name="municipio_ibge" required>
                            <option value="">Selecione o município…</option>
                            <?php foreach ($municipios as $m): ?>
                                <option value="<?= htmlspecialchars($m['codigo_ibge'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ($v('municipio_ibge') === ($m['codigo_ibge'] ?? '')) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($m['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?> — <?= htmlspecialchars($m['provedor'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-muted small">Próximo RPS</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Série <?= htmlspecialchars($serieRps, ENT_QUOTES, 'UTF-8') ?></span>
                            <input type="text" class="form-control" readonly value="<?= (int) $proximoRps ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mb-4">
            <a href="index.php?route=nfse" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-send me-1"></i> Emitir NFSe
            </button>
        </div>
    </form>
</div>

<script src="public/js/viacep.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tipo de pessoa — alterna máscara e placeholder do CPF/CNPJ.
    const radios = document.querySelectorAll('input[name="tipo_pessoa"]');
    const cpfCnpj = document.getElementById('tomador_cnpj_cpf');
    const labelNome = document.querySelector('[data-label-nome]');
    function aplicarTipoPessoa() {
        const fisica = document.querySelector('input[name="tipo_pessoa"]:checked')?.value === 'FISICA';
        if (cpfCnpj) {
            cpfCnpj.placeholder = fisica ? '000.000.000-00' : '00.000.000/0000-00';
        }
        if (labelNome) labelNome.textContent = fisica ? 'Nome Completo' : 'Razão Social';
    }
    radios.forEach(function(r) { r.addEventListener('change', aplicarTipoPessoa); });
    aplicarTipoPessoa();

    // Autocomplete de cliente: ao escolher do datalist, busca CPF/CNPJ e preenche.
    const clientes = <?= json_encode(array_values(array_map(static fn($c) => [
        'nome' => (string)($c['nome_razao_social'] ?? ''),
        'doc'  => (string)($c['cpf_cnpj'] ?? ''),
        'email'=> (string)($c['email'] ?? ''),
        'cep'  => (string)($c['cep'] ?? ''),
        'ender'=> (string)($c['endereco'] ?? ''),
        'num'  => (string)($c['numero'] ?? ''),
        'bairro'=>(string)($c['bairro'] ?? ''),
        'cidade'=>(string)($c['cidade'] ?? ''),
        'uf'   => (string)($c['uf'] ?? ''),
    ], $clientes)), JSON_UNESCAPED_UNICODE) ?>;

    const nomeInput = document.getElementById('tomador_nome');
    if (nomeInput) {
        nomeInput.addEventListener('change', function() {
            const sel = clientes.find(c => c.nome === this.value);
            if (!sel) return;
            if (cpfCnpj && sel.doc) cpfCnpj.value = sel.doc;
            const set = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
            set('tomador_email', sel.email);
            set('cep', sel.cep);
            set('endereco', sel.ender);
            set('numero', sel.num);
            set('bairro', sel.bairro);
            set('cidade', sel.cidade);
            if (sel.uf) {
                const ufSel = document.getElementById('uf');
                if (ufSel) ufSel.value = sel.uf;
            }
        });
    }
});
</script>
