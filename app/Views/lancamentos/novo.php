<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $contas               Contas ativas (para select).
 * @var array  $planos               Plano de contas flat com nivel (para select tree).
 * @var array  $clientesFornecedores Clientes/Fornecedores ativos (autocomplete).
 * @var array  $old                  Valores para repopular após erro.
 */
use App\Helpers\Format;

$contas               = $contas               ?? [];
$planos               = $planos               ?? [];
$clientesFornecedores = $clientesFornecedores ?? [];
$old                  = $old                  ?? [];

$formasPagamento = ['PIX' => 'PIX', 'BOLETO' => 'Boleto', 'CARTAO' => 'Cartão', 'DINHEIRO' => 'Dinheiro', 'TRANSFERENCIA' => 'Transferência', 'DEBITO' => 'Débito Automático'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-receipt me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Novo Lançamento', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=lancamentos" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=lancamentos" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <!-- Tipo -->
                    <div class="col-12">
                        <label class="form-label d-block">Tipo <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_receita" value="RECEITA" required
                                   <?= (($old['tipo'] ?? '') === 'RECEITA') ? 'checked' : '' ?>>
                            <label class="form-check-label text-success fw-semibold" for="tipo_receita"><i class="bi bi-arrow-down-circle me-1"></i>Receita</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_despesa" value="DESPESA" required
                                   <?= (($old['tipo'] ?? '') === 'DESPESA') ? 'checked' : '' ?>>
                            <label class="form-check-label text-danger fw-semibold" for="tipo_despesa"><i class="bi bi-arrow-up-circle me-1"></i>Despesa</label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="conta_id" class="form-label">Conta <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)($old['conta_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione a conta.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="plano_conta_id" class="form-label">Plano de Contas <span class="text-danger">*</span></label>
                        <select class="form-select" id="plano_conta_id" name="plano_conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach ($planos as $pc): ?>
                                <option value="<?= (int)$pc['id'] ?>" <?= ((int)($old['plano_conta_id'] ?? 0) === (int)$pc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(str_repeat('— ', (int)($pc['nivel'] ?? 0)) . ($pc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione o plano de contas.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="cliente_fornecedor_id" class="form-label">Cliente / Fornecedor</label>
                        <input type="text" class="form-control" id="cliente_fornecedor_nome" name="cliente_fornecedor_nome" list="dlClientesFornecedores"
                               value="<?= htmlspecialchars($old['cliente_fornecedor_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Busca por nome">
                        <input type="hidden" id="cliente_fornecedor_id" name="cliente_fornecedor_id" value="<?= htmlspecialchars($old['cliente_fornecedor_id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <datalist id="dlClientesFornecedores">
                            <?php foreach ($clientesFornecedores as $cf): ?>
                                <option value="<?= htmlspecialchars($cf['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-id="<?= (int)($cf['id'] ?? 0) ?>">
                            <?php endforeach; ?>
                        </datalist>
                        <small class="form-text text-muted">Opcional.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor" name="valor" required data-mask="money"
                                   value="<?= htmlspecialchars($old['valor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <div class="invalid-feedback">Informe um valor válido.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_lancamento" class="form-label">Data Lançamento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_lancamento" name="data_lancamento" required
                               value="<?= htmlspecialchars($old['data_lancamento'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a data.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_vencimento" class="form-label">Data Vencimento</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento"
                               value="<?= htmlspecialchars($old['data_vencimento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Opcional.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                            <option value="">—</option>
                            <?php foreach ($formasPagamento as $k => $v): ?>
                                <option value="<?= $k ?>" <?= (($old['forma_pagamento'] ?? '') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label d-block">Status <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="status_pendente" value="PENDENTE" required
                                   <?= (($old['status'] ?? 'PENDENTE') === 'PENDENTE') ? 'checked' : '' ?>
                                   data-status-toggle>
                            <label class="form-check-label text-warning" for="status_pendente">Pendente</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="status" id="status_pago" value="PAGO" required
                                   <?= (($old['status'] ?? 'PENDENTE') === 'PAGO') ? 'checked' : '' ?>
                                   data-status-toggle>
                            <label class="form-check-label text-success" for="status_pago">Pago</label>
                        </div>
                        <input type="hidden" name="data_pagamento" id="data_pagamento" value="<?= htmlspecialchars($old['data_pagamento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="documento" class="form-label">Documento</label>
                        <input type="text" class="form-control" id="documento" name="documento" maxlength="50"
                               value="<?= htmlspecialchars($old['documento'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Nº doc / NF">
                    </div>

                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2" required maxlength="500"><?= htmlspecialchars($old['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="invalid-feedback">Informe a descrição.</div>
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="2" maxlength="1000"><?= htmlspecialchars($old['observacao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?route=lancamentos" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Lançamento
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sincroniza o hidden id quando o usuário seleciona um nome do datalist.
    const nomeInput = document.getElementById('cliente_fornecedor_nome');
    const idInput   = document.getElementById('cliente_fornecedor_id');
    const dl        = document.getElementById('dlClientesFornecedores');
    if (nomeInput && idInput && dl) {
        nomeInput.addEventListener('input', function() {
            const v = nomeInput.value;
            let found = '';
            dl.querySelectorAll('option').forEach(function(opt) {
                if (opt.value === v && opt.dataset.id) { found = opt.dataset.id; }
            });
            idInput.value = found;
        });
    }

    // Seta data_pagamento = hoje quando status = PAGO
    const toggles = document.querySelectorAll('[data-status-toggle]');
    toggles.forEach(function(t) {
        t.addEventListener('change', function() {
            const dp = document.getElementById('data_pagamento');
            if (!dp) { return; }
            if (t.value === 'PAGO' && t.checked) {
                dp.value = new Date().toISOString().split('T')[0];
            } else if (t.value === 'PENDENTE' && t.checked) {
                dp.value = '';
            }
        });
    });
});
</script>
