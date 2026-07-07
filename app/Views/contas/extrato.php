<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $conta        Dados da conta (id, nome, tipo, ...).
 * @var array  $lancamentos  Lançamentos da conta no período.
 * @var array  $filtros      data_inicio, data_fim (YYYY-MM-DD).
 * @var float  $saldoAtual   Saldo atual da conta.
 */
use App\Helpers\Format;

$conta       = $conta       ?? [];
$lancamentos = $lancamentos ?? [];
$filtros     = $filtros     ?? ['data_inicio' => date('Y-m-01'), 'data_fim' => date('Y-m-d')];
$saldoAtual  = $saldoAtual  ?? 0.0;

$tipoBadge = [
    'BANCO'    => '<span class="badge bg-primary">Banco</span>',
    'CAIXA'    => '<span class="badge bg-success">Caixa</span>',
    'ASAAS'    => '<span class="badge bg-info text-dark">Asaas</span>',
    'CARTEIRA' => '<span class="badge bg-warning text-dark">Carteira</span>',
    'OUTRO'    => '<span class="badge bg-secondary">Outro</span>',
];

$statusBadge = [
    'PENDENTE'  => '<span class="badge bg-warning text-dark">Pendente</span>',
    'PAGO'      => '<span class="badge bg-success">Pago</span>',
    'CANCELADO' => '<span class="badge bg-danger">Cancelado</span>',
];

$tipoLancBadge = [
    'RECEITA'      => '<span class="badge bg-success">Receita</span>',
    'DESPESA'      => '<span class="badge bg-danger">Despesa</span>',
    'TRANSFERENCIA'=> '<span class="badge bg-info text-dark">Transferência</span>',
];

$saldoClass = $saldoAtual > 0 ? 'text-success' : ($saldoAtual < 0 ? 'text-danger' : 'text-muted');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            <i class="bi bi-list-ul me-2 text-primary"></i>
            Extrato: <?= htmlspecialchars($conta['nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
            <?= $tipoBadge[$conta['tipo'] ?? 'OUTRO'] ?? '' ?>
        </h1>
        <div class="btn-toolbar gap-2 no-print">
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <a href="/contas" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card bg-light shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="small text-muted">Saldo atual da conta</div>
                <div class="fs-3 fw-bold <?= $saldoClass ?>"><?= Format::moneyBRL((float)$saldoAtual) ?></div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Data de referência</div>
                <div class="fw-semibold"><?= date('d/m/Y') ?></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body">
            <form method="get" action="/contas/<?= (int)($conta['id'] ?? 0) ?>/extrato" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio" required
                           value="<?= htmlspecialchars($filtros['data_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim" required
                           value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1">
                        <i class="bi bi-funnel"></i> Filtrar
                    </button>
                    <a href="/contas/<?= (int)($conta['id'] ?? 0) ?>/extrato" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($lancamentos)): ?>
                <?php $message = 'Nenhum lançamento neste período.'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblExtrato" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Plano de Conta</th>
                                <th>Tipo</th>
                                <th class="text-end">Valor</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lancamentos as $l):
                                $tipo = $l['tipo'] ?? '';
                                $valorClass = ($tipo === 'RECEITA') ? 'text-money-positive' : (($tipo === 'DESPESA') ? 'text-money-negative' : 'text-money-positive');
                                $desc = $l['descricao'] ?? '';
                                if ($tipo === 'TRANSFERENCIA') {
                                    if (!empty($l['transferencia_destino_nome'])) {
                                        $desc .= ' — Para: ' . $l['transferencia_destino_nome'];
                                    } elseif (!empty($l['transferencia_origem_nome'])) {
                                        $desc .= ' — De: ' . $l['transferencia_origem_nome'];
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?= Format::dateBR($l['data_lancamento'] ?? null) ?></td>
                                    <td><?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($l['plano_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= $tipoLancBadge[$tipo] ?? htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end fw-semibold <?= $valorClass ?>">
                                        <?= Format::moneyBRL((float)($l['valor'] ?? 0)) ?>
                                    </td>
                                    <td><?= $statusBadge[$l['status'] ?? 'PENDENTE'] ?? $statusBadge['PENDENTE'] ?></td>
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
        jQuery('#tblExtrato').DataTable({
            order: [[0, 'desc']],
            pageLength: 25
        });
    }
});
</script>
