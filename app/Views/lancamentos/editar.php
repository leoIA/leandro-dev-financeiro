<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $lancamento           Lançamento sendo editado.
 * @var array  $contas               Contas ativas.
 * @var array  $planos               Plano de contas flat com nivel.
 * @var array  $clientesFornecedores Clientes/Fornecedores ativos.
 * @var array  $old                  Valores para repopular após erro.
 */
use App\Helpers\Format;

$lancamento           = $lancamento           ?? [];
$contas               = $contas               ?? [];
$planos               = $planos               ?? [];
$clientesFornecedores = $clientesFornecedores ?? [];
$old                  = $old                  ?? [];

$val = static function (string $field, $default = '') use ($lancamento, $old) {
    return $old[$field] ?? $lancamento[$field] ?? $default;
};

$isPago = ($val('status') === 'PAGO');
$isCancelado = ($val('status') === 'CANCELADO');
$lockValorTipo = $isPago || $isCancelado;

$formasPagamento = ['PIX' => 'PIX', 'BOLETO' => 'Boleto', 'CARTAO' => 'Cartão', 'DINHEIRO' => 'Dinheiro', 'TRANSFERENCIA' => 'Transferência', 'DEBITO' => 'Débito Automático'];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-receipt me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Editar Lançamento', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=lancamentos" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($isPago): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            Este lançamento está <strong>PAGO</strong>. O <strong>Tipo</strong> e o <strong>Valor</strong> não podem ser alterados.
            Para modificá-los, utilize o botão <strong>Estornar</strong> para retornar o lançamento ao status <em>Pendente</em>.
        </div>
    <?php endif; ?>
    <?php if ($isCancelado): ?>
        <div class="alert alert-danger">
            <i class="bi bi-x-octagon"></i>
            Este lançamento está <strong>CANCELADO</strong> e não pode mais ser editado.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=lancamentos/<?= (int)($lancamento['id'] ?? 0) ?>" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_method" value="PUT">

                <?php if ($lockValorTipo): ?>
                    <input type="hidden" name="tipo"  value="<?= htmlspecialchars($val('tipo'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="valor" value="<?= htmlspecialchars((string)($val('valor') ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <?php endif; ?>

                <div class="row g-3">
                    <!-- Tipo -->
                    <div class="col-12">
                        <label class="form-label d-block">Tipo <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_receita" value="RECEITA"
                                   <?= $val('tipo') === 'RECEITA' ? 'checked' : '' ?> <?= $lockValorTipo ? 'disabled' : 'required' ?>>
                            <label class="form-check-label text-success fw-semibold" for="tipo_receita"><i class="bi bi-arrow-down-circle me-1"></i>Receita</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_despesa" value="DESPESA"
                                   <?= $val('tipo') === 'DESPESA' ? 'checked' : '' ?> <?= $lockValorTipo ? 'disabled' : 'required' ?>>
                            <label class="form-check-label text-danger fw-semibold" for="tipo_despesa"><i class="bi bi-arrow-up-circle me-1"></i>Despesa</label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="conta_id" class="form-label">Conta <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_id" name="conta_id" required <?= $lockValorTipo ? 'disabled' : '' ?>>
                            <option value="">Selecione…</option>
                            <?php foreach ($contas as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)$val('conta_id') === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="conta_id" value="<?= (int)$val('conta_id') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label for="plano_conta_id" class="form-label">Plano de Contas <span class="text-danger">*</span></label>
                        <select class="form-select" id="plano_conta_id" name="plano_conta_id" required <?= $lockValorTipo ? 'disabled' : '' ?>>
                            <option value="">Selecione…</option>
                            <?php foreach ($planos as $pc): ?>
                                <option value="<?= (int)$pc['id'] ?>" <?= ((int)$val('plano_conta_id') === (int)$pc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(str_repeat('— ', (int)($pc['nivel'] ?? 0)) . ($pc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="plano_conta_id" value="<?= (int)$val('plano_conta_id') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <label for="cliente_fornecedor_nome" class="form-label">Cliente / Fornecedor</label>
                        <input type="text" class="form-control" id="cliente_fornecedor_nome" name="cliente_fornecedor_nome" list="dlClientesFornecedores"
                               value="<?= htmlspecialchars($val('cliente_fornecedor_nome') ?? $val('nome_razao_social'), ENT_QUOTES, 'UTF-8') ?>" placeholder="Busca por nome" <?= $lockValorTipo ? 'disabled' : '' ?>>
                        <input type="hidden" id="cliente_fornecedor_id" name="cliente_fornecedor_id" value="<?= htmlspecialchars((string)$val('cliente_fornecedor_id'), ENT_QUOTES, 'UTF-8') ?>">
                        <datalist id="dlClientesFornecedores">
                            <?php foreach ($clientesFornecedores as $cf): ?>
                                <option value="<?= htmlspecialchars($cf['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>" data-id="<?= (int)($cf['id'] ?? 0) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="col-md-3">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor" name="valor_display" data-mask="money"
                                   value="<?= htmlspecialchars($val('valor') ? Format::moneyBRLPositive((float)$val('valor')) : '', ENT_QUOTES, 'UTF-8') ?>"
                                   inputmode="decimal" <?= $lockValorTipo ? 'readonly disabled' : 'required' ?>>
                        </div>
                        <div class="invalid-feedback">Informe um valor válido.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_lancamento" class="form-label">Data Lançamento <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_lancamento" name="data_lancamento" required
                               value="<?= htmlspecialchars($val('data_lancamento'), ENT_QUOTES, 'UTF-8') ?>" <?= $lockValorTipo ? 'disabled' : '' ?>>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="data_lancamento" value="<?= htmlspecialchars($val('data_lancamento'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="data_vencimento" class="form-label">Data Vencimento</label>
                        <input type="date" class="form-control" id="data_vencimento" name="data_vencimento"
                               value="<?= htmlspecialchars($val('data_vencimento'), ENT_QUOTES, 'UTF-8') ?>" <?= $lockValorTipo ? 'disabled' : '' ?>>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="data_vencimento" value="<?= htmlspecialchars($val('data_vencimento'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento" <?= $lockValorTipo ? 'disabled' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($formasPagamento as $k => $v): ?>
                                <option value="<?= $k ?>" <?= ($val('forma_pagamento') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="forma_pagamento" value="<?= htmlspecialchars($val('forma_pagamento'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="documento" class="form-label">Documento</label>
                        <input type="text" class="form-control" id="documento" name="documento" maxlength="50"
                               value="<?= htmlspecialchars($val('documento'), ENT_QUOTES, 'UTF-8') ?>" <?= $lockValorTipo ? 'disabled' : '' ?>>
                        <?php if ($lockValorTipo): ?>
                            <input type="hidden" name="documento" value="<?= htmlspecialchars($val('documento'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <input type="text" class="form-control" id="status" value="<?= htmlspecialchars($val('status'), ENT_QUOTES, 'UTF-8') ?>" readonly disabled>
                    </div>

                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2" required maxlength="500" <?= $lockValorTipo ? 'readonly' : '' ?>><?= htmlspecialchars($val('descricao'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="2" maxlength="1000"><?= htmlspecialchars($val('observacao'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <div class="btn-group">
                        <?php if ($isPago): ?>
                            <form method="post" action="index.php?route=lancamentos/<?= (int)($lancamento['id'] ?? 0) ?>/estornar" class="d-inline" data-confirm="Estornar este lançamento? Ele voltará para o status PENDENTE e a data de pagamento será limpa.">
                                <?= \App\Core\Csrf::field() ?>
                                <button type="submit" class="btn btn-outline-warning">
                                    <i class="bi bi-arrow-counterclockwise"></i> Estornar
                                </button>
                            </form>
                        <?php endif; ?>
                        <?php if (!$isCancelado): ?>
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelarLancamentoModal">
                                <i class="bi bi-x-circle"></i> Cancelar Lançamento
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="index.php?route=lancamentos" class="btn btn-outline-secondary">Voltar</a>
                        <?php if (!$isCancelado): ?>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar Alterações
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cancelar Lançamento -->
<div class="modal fade" id="cancelarLancamentoModal" tabindex="-1" aria-labelledby="cancelarLancamentoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelarLancamentoForm" method="post" action="index.php?route=lancamentos/<?= (int)($lancamento['id'] ?? 0) ?>/cancelar">
                <?= \App\Core\Csrf::field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelarLancamentoLabel"><i class="bi bi-x-circle me-2"></i>Cancelar Lançamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja <strong>cancelar</strong> este lançamento?</p>
                    <p class="text-muted small">O lançamento não será excluído — apenas marcado como <strong>CANCELADO</strong>. O motivo informado será anexado às observações.</p>
                    <div class="mb-3">
                        <label for="motivo" class="form-label">Motivo do cancelamento <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="motivo" name="motivo" rows="3" required maxlength="500" placeholder="Descreva o motivo…"></textarea>
                        <div class="invalid-feedback">Informe o motivo do cancelamento.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i>Confirmar Cancelamento</button>
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
});
</script>
