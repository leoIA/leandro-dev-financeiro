<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $contas    Lista de contas (ativas + inativas) com saldo_atual.
 * @var array  $filtros   Filtros aplicados (tipo, status).
 */
use App\Helpers\Format;

$contas  = $contas  ?? [];
$filtros = $filtros ?? ['tipo' => '', 'status' => ''];

$tipoBadge = [
    'BANCO'    => '<span class="badge bg-primary">Banco</span>',
    'CAIXA'    => '<span class="badge bg-success">Caixa</span>',
    'ASAAS'    => '<span class="badge bg-info text-dark">Asaas</span>',
    'CARTEIRA' => '<span class="badge bg-warning text-dark">Carteira</span>',
    'OUTRO'    => '<span class="badge bg-secondary">Outro</span>',
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-bank me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Contas', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=contas/novo" class="btn btn-success btn-sm">
            <i class="bi bi-plus-lg"></i> Nova Conta
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="index.php?route=contas" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="f_tipo" class="form-label">Tipo</label>
                    <select class="form-select form-select-sm" id="f_tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="BANCO"    <?= ($filtros['tipo'] ?? '') === 'BANCO'    ? 'selected' : '' ?>>Banco</option>
                        <option value="CAIXA"    <?= ($filtros['tipo'] ?? '') === 'CAIXA'    ? 'selected' : '' ?>>Caixa</option>
                        <option value="ASAAS"    <?= ($filtros['tipo'] ?? '') === 'ASAAS'    ? 'selected' : '' ?>>Asaas</option>
                        <option value="CARTEIRA" <?= ($filtros['tipo'] ?? '') === 'CARTEIRA' ? 'selected' : '' ?>>Carteira</option>
                        <option value="OUTRO"    <?= ($filtros['tipo'] ?? '') === 'OUTRO'    ? 'selected' : '' ?>>Outro</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="f_status" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="f_status" name="status">
                        <option value="">Todos</option>
                        <option value="ativo"   <?= ($filtros['status'] ?? '') === 'ativo'   ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($filtros['status'] ?? '') === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="index.php?route=contas" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($contas)): ?>
                <?php $message = 'Nenhuma conta cadastrada. Clique em "Nova Conta".'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblContas" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Instituição</th>
                                <th class="text-end">Saldo Atual</th>
                                <th>Status</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contas as $c):
                                $saldo = (float)($c['saldoAtual'] ?? $c['saldo_atual'] ?? 0);
                                $saldoClass = $saldo > 0 ? 'text-money-positive' : ($saldo < 0 ? 'text-money-negative' : 'text-muted');
                            ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($c['agencia']) || !empty($c['conta_numero'])): ?>
                                            <div class="small text-muted">
                                                <?= !empty($c['agencia']) ? 'Ag: ' . htmlspecialchars($c['agencia'], ENT_QUOTES, 'UTF-8') : '' ?>
                                                <?= !empty($c['conta_numero']) ? 'Cc: ' . htmlspecialchars($c['conta_numero'], ENT_QUOTES, 'UTF-8') : '' ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $tipoBadge[$c['tipo'] ?? 'OUTRO'] ?? $tipoBadge['OUTRO'] ?></td>
                                    <td><?= htmlspecialchars($c['instituicao'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end fw-semibold <?= $saldoClass ?>"><?= Format::moneyBRL($saldo) ?></td>
                                    <td>
                                        <?php if (!empty($c['ativo'])): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="index.php?route=contas/<?= (int)$c['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="index.php?route=contas/<?= (int)$c['id'] ?>/extrato" class="btn btn-sm btn-outline-secondary" title="Ver extrato">
                                            <i class="bi bi-list-ul"></i>
                                        </a>
                                        <?php if (!empty($c['ativo'])): ?>
                                            <form method="post" action="index.php?route=contas/<?= (int)$c['id'] ?>/desativar" class="d-inline" data-confirm="Desativar esta conta? Lançamentos existentes permanecerão.">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Desativar">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="index.php?route=contas/<?= (int)$c['id'] ?>/ativar" class="d-inline">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Reativar">
                                                    <i class="bi bi-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblContas').DataTable({
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }
});
</script>
