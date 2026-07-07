<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $lancamentos          Lista de lançamentos com joins.
 * @var array  $contas               Contas ativas (para select filtro).
 * @var array  $planos               Plano de contas flat com nivel (para select tree).
 * @var array  $clientesFornecedores Clientes/Fornecedores ativos (para autocomplete).
 * @var array  $filtros              conta_id, plano_conta_id, tipo, status, favorecido, data_inicio, data_fim.
 */
use App\Helpers\Format;

$lancamentos          = $lancamentos          ?? [];
$contas               = $contas               ?? [];
$planos               = $planos               ?? [];
$clientesFornecedores = $clientesFornecedores ?? [];
$filtros              = $filtros              ?? [
    'conta_id' => '', 'plano_conta_id' => '', 'tipo' => '', 'status' => '',
    'favorecido' => '', 'data_inicio' => '', 'data_fim' => ''
];

$tipoBadge = [
    'RECEITA'       => '<span class="badge bg-success">Receita</span>',
    'DESPESA'       => '<span class="badge bg-danger">Despesa</span>',
    'TRANSFERENCIA' => '<span class="badge bg-info text-dark">Transferência</span>',
];

$statusBadge = [
    'PENDENTE'  => '<span class="badge bg-warning text-dark">Pendente</span>',
    'PAGO'      => '<span class="badge bg-success">Pago</span>',
    'CANCELADO' => '<span class="badge bg-danger">Cancelado</span>',
];

// Query string para exportar CSV (mantém filtros).
$csvQuery = http_build_query(array_filter($filtros));
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-receipt me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Lançamentos', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="btn-toolbar gap-2">
            <a href="/lancamentos/exportar-csv?<?= htmlspecialchars($csvQuery, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-outline-success btn-sm">
                <i class="bi bi-filetype-csv"></i> Exportar CSV
            </a>
            <a href="/lancamentos/novo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Novo Lançamento
            </a>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="/lancamentos" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="f_conta" class="form-label">Conta</label>
                    <select class="form-select form-select-sm" id="f_conta" name="conta_id">
                        <option value="">Todas</option>
                        <?php foreach ($contas as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ((int)($filtros['conta_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="f_plano" class="form-label">Plano de Contas</label>
                    <select class="form-select form-select-sm" id="f_plano" name="plano_conta_id">
                        <option value="">Todos</option>
                        <?php foreach ($planos as $pc): ?>
                            <option value="<?= (int)$pc['id'] ?>" <?= ((int)($filtros['plano_conta_id'] ?? 0) === (int)$pc['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(str_repeat('— ', (int)($pc['nivel'] ?? 0)) . ($pc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="f_tipo" class="form-label">Tipo</label>
                    <select class="form-select form-select-sm" id="f_tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="RECEITA"       <?= ($filtros['tipo'] ?? '') === 'RECEITA'       ? 'selected' : '' ?>>Receita</option>
                        <option value="DESPESA"       <?= ($filtros['tipo'] ?? '') === 'DESPESA'       ? 'selected' : '' ?>>Despesa</option>
                        <option value="TRANSFERENCIA" <?= ($filtros['tipo'] ?? '') === 'TRANSFERENCIA' ? 'selected' : '' ?>>Transferência</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="f_status" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="f_status" name="status">
                        <option value="">Todos</option>
                        <option value="PENDENTE"  <?= ($filtros['status'] ?? '') === 'PENDENTE'  ? 'selected' : '' ?>>Pendente</option>
                        <option value="PAGO"      <?= ($filtros['status'] ?? '') === 'PAGO'      ? 'selected' : '' ?>>Pago</option>
                        <option value="CANCELADO" <?= ($filtros['status'] ?? '') === 'CANCELADO' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="f_favorecido" class="form-label">Favorecido</label>
                    <input type="text" class="form-control form-control-sm" id="f_favorecido" name="favorecido" list="dlFavorecidos"
                           value="<?= htmlspecialchars($filtros['favorecido'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Busca por nome">
                    <datalist id="dlFavorecidos">
                        <?php foreach ($clientesFornecedores as $cf): ?>
                            <option value="<?= htmlspecialchars($cf['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="col-md-2">
                    <label for="f_data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control form-control-sm" id="f_data_inicio" name="data_inicio"
                           value="<?= htmlspecialchars($filtros['data_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label for="f_data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control form-control-sm" id="f_data_fim" name="data_fim"
                           value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="/lancamentos" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($lancamentos)): ?>
                <?php $message = 'Nenhum lançamento encontrado com os filtros aplicados.'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblLancamentos" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Conta</th>
                                <th>Data</th>
                                <th>Favorecido</th>
                                <th>Plano de Conta</th>
                                <th>Tipo</th>
                                <th class="text-end">Valor</th>
                                <th>Status</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $l):
                                $tipo = $l['tipo'] ?? '';
                                $valorClass = $tipo === 'RECEITA' ? 'text-money-positive' : ($tipo === 'DESPESA' ? 'text-money-negative' : 'text-money-positive');
                                $fav = '';
                                if ($tipo === 'TRANSFERENCIA') {
                                    if (!empty($l['transferencia_destino_nome'])) {
                                        $fav = 'Para: ' . htmlspecialchars($l['transferencia_destino_nome'], ENT_QUOTES, 'UTF-8');
                                    } elseif (!empty($l['transferencia_origem_nome'])) {
                                        $fav = 'De: ' . htmlspecialchars($l['transferencia_origem_nome'], ENT_QUOTES, 'UTF-8');
                                    } else {
                                        $fav = 'Transferência';
                                    }
                                } else {
                                    $fav = htmlspecialchars($l['favorecido'] ?? $l['nome_razao_social'] ?? '—', ENT_QUOTES, 'UTF-8');
                                }
                                $isPendente = ($l['status'] ?? '') === 'PENDENTE';
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['conta_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= Format::dateBR($l['data_lancamento'] ?? null) ?></td>
                                    <td><?= $fav ?></td>
                                    <td><?= htmlspecialchars($l['plano_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $tipoBadge[$tipo] ?? htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end fw-semibold <?= $valorClass ?>"><?= Format::moneyBRL((float)($l['valor'] ?? 0)) ?></td>
                                    <td><?= $statusBadge[$l['status'] ?? 'PENDENTE'] ?? $statusBadge['PENDENTE'] ?></td>
                                    <td class="text-center no-print">
                                        <a href="/lancamentos/<?= (int)$l['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($isPendente): ?>
                                            <form method="post" action="/lancamentos/<?= (int)$l['id'] ?>/marcar-pago" class="d-inline" data-confirm="Marcar este lançamento como PAGO?">
                                                <?= \App\Core\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Marcar como pago">
                                                    <i class="bi bi-check2-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if (in_array($l['status'] ?? '', ['PENDENTE', 'PAGO'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Cancelar lançamento"
                                                    data-bs-toggle="modal" data-bs-target="#cancelarLancamentoModal"
                                                    data-action-url="/lancamentos/<?= (int)$l['id'] ?>/cancelar"
                                                    data-item-desc="<?= htmlspecialchars($l['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
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

<!-- Modal: Cancelar Lançamento -->
<div class="modal fade" id="cancelarLancamentoModal" tabindex="-1" aria-labelledby="cancelarLancamentoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="cancelarLancamentoForm" method="post" action="">
                <?= \App\Core\Csrf::field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="cancelarLancamentoLabel"><i class="bi bi-x-circle me-2"></i>Cancelar Lançamento</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja <strong>cancelar</strong> o lançamento <em data-modal-desc></em>?</p>
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
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblLancamentos').DataTable({
            order: [[1, 'desc']],
            pageLength: 25,
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    const modal = document.getElementById('cancelarLancamentoModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn) { return; }
            const url  = btn.dataset.actionUrl || '';
            const desc = btn.dataset.itemDesc || '';
            modal.querySelector('form').action = url;
            const descEl = modal.querySelector('[data-modal-desc]');
            if (descEl) { descEl.textContent = '"' + desc + '"'; }
        });
    }
});
</script>
