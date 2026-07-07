<?php declare(strict_types=1);
/** @var array $contas */
/** @var string $pageTitle */
/** @var array $old */
$old = $old ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Nova Transferência', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=lancamentos" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="alert alert-info d-flex align-items-start gap-2">
        <i class="bi bi-info-circle-fill fs-5"></i>
        <div>
            Esta operação criará <strong>2 lançamentos automáticos</strong>:
            <span class="badge bg-danger">débito na conta de origem</span>
            e
            <span class="badge bg-success">crédito na conta de destino</span>.
            Ambos serão registrados como <strong>PAGOS</strong> na data informada.
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=transferencias" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="conta_origem_id" class="form-label">Conta de Origem <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_origem_id" name="conta_origem_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach (($contas ?? []) as $c): ?>
                                <?php if (empty($c['ativo'])) continue; ?>
                                <option value="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ((int)($old['conta_origem_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione a conta de origem.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="conta_destino_id" class="form-label">Conta de Destino <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_destino_id" name="conta_destino_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach (($contas ?? []) as $c): ?>
                                <?php if (empty($c['ativo'])) continue; ?>
                                <option value="<?= (int)$c['id'] ?>" data-nome="<?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    <?= ((int)($old['conta_destino_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione a conta de destino.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor" name="valor" required data-mask="money"
                                   value="<?= htmlspecialchars($old['valor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <div class="invalid-feedback">Informe um valor maior que zero.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="data_transferencia" class="form-label">Data da Transferência <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_transferencia" name="data_transferencia" required
                               value="<?= htmlspecialchars($old['data_transferencia'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a data da transferência.</div>
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="3" maxlength="500"
                                  placeholder="Detalhes adicionais (opcional)"><?= htmlspecialchars($old['observacao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?route=lancamentos" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-arrow-left-right"></i> Realizar Transferência
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const origem = document.getElementById('conta_origem_id');
    const destino = document.getElementById('conta_destino_id');

    function validarContas() {
        if (origem.value && destino.value && origem.value === destino.value) {
            destino.setCustomValidity('A conta de destino deve ser diferente da conta de origem.');
        } else {
            destino.setCustomValidity('');
        }
    }
    origem.addEventListener('change', validarContas);
    destino.addEventListener('change', validarContas);

    // Desabilita opção já selecionada na outra ponta
    function sincronizar() {
        const oVal = origem.value;
        const dVal = destino.value;
        destino.querySelectorAll('option').forEach(function(opt) {
            opt.disabled = (opt.value && opt.value === oVal);
        });
        origem.querySelectorAll('option').forEach(function(opt) {
            opt.disabled = (opt.value && opt.value === dVal);
        });
    }
    origem.addEventListener('change', sincronizar);
    destino.addEventListener('change', sincronizar);
    sincronizar();
});
</script>
