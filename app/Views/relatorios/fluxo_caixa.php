<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $filtros */
/** @var array $contas */
/** @var array $planos */
/** @var array $dados */
/** @var array $totais */
use App\Helpers\Format;
$filtros = $filtros ?? ['data_inicio' => date('Y-m-01'), 'data_fim' => date('Y-m-d'), 'conta_id' => '', 'plano_conta_id' => ''];
$dados = $dados ?? [];
$totais = $totais ?? ['receitas' => 0, 'despesas' => 0, 'saldo_dia' => 0, 'saldo_acumulado' => 0];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Relatório: Fluxo de Caixa', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="btn-toolbar gap-2 no-print">
            <button type="button" class="btn btn-outline-success btn-sm" id="btnCsv">
                <i class="bi bi-filetype-csv"></i> Exportar CSV
            </button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnPrint" onclick="window.print()">
                <i class="bi bi-printer"></i> Imprimir
            </button>
        </div>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body">
            <form method="get" action="index.php?route=relatorios/fluxo-caixa" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control form-control-sm" id="data_inicio" name="data_inicio"
                           value="<?= htmlspecialchars($filtros['data_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control form-control-sm" id="data_fim" name="data_fim"
                           value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="conta_id" class="form-label">Conta</label>
                    <select class="form-select form-select-sm" id="conta_id" name="conta_id">
                        <option value="">Todas</option>
                        <?php foreach (($contas ?? []) as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= ((int)($filtros['conta_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="plano_conta_id" class="form-label">Plano de Contas</label>
                    <select class="form-select form-select-sm" id="plano_conta_id" name="plano_conta_id">
                        <option value="">Todos</option>
                        <?php foreach (($planos ?? []) as $pc): ?>
                                <option value="<?= (int)$pc['id'] ?>" <?= ((int)($filtros['plano_conta_id'] ?? 0) === (int)$pc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(str_repeat('— ', (int)($pc['nivel'] ?? 0)) . ($pc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-bar-chart"></i> Gerar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($dados)): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-light">
                <strong>Detalhamento Diário</strong>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover" id="tblFluxo">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th class="text-end">Receitas</th>
                                <th class="text-end">Despesas</th>
                                <th class="text-end">Saldo do Dia</th>
                                <th class="text-end">Saldo Acumulado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dados as $row): ?>
                                <tr>
                                    <td><?= Format::dateBR($row['data'] ?? '') ?></td>
                                    <td class="text-end text-money-positive"><?= Format::moneyBRL((float)($row['receitas'] ?? 0)) ?></td>
                                    <td class="text-end text-money-negative"><?= Format::moneyBRL((float)($row['despesas'] ?? 0)) ?></td>
                                    <td class="text-end <?= ($row['saldo_dia'] ?? 0) >= 0 ? 'text-money-positive' : 'text-money-negative' ?>">
                                        <?= Format::moneyBRL((float)($row['saldo_dia'] ?? 0)) ?>
                                    </td>
                                    <td class="text-end fw-bold <?= ($row['saldo_acumulado'] ?? 0) >= 0 ? 'text-money-positive' : 'text-money-negative' ?>">
                                        <?= Format::moneyBRL((float)($row['saldo_acumulado'] ?? 0)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-dark fw-bold">
                                <td>TOTAIS</td>
                                <td class="text-end text-money-positive"><?= Format::moneyBRL((float)($totais['receitas'] ?? 0)) ?></td>
                                <td class="text-end text-money-negative"><?= Format::moneyBRL((float)($totais['despesas'] ?? 0)) ?></td>
                                <td class="text-end"><?= Format::moneyBRL((float)($totais['saldo_dia'] ?? 0)) ?></td>
                                <td class="text-end"><?= Format::moneyBRL((float)($totais['saldo_acumulado'] ?? 0)) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><strong>Saldo Acumulado</strong></div>
                    <div class="card-body">
                        <canvas id="chartSaldo" height="220"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-light"><strong>Receitas x Despesas</strong></div>
                    <div class="card-body">
                        <canvas id="chartRecDesp" height="220"></canvas>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($dados)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dados = <?= json_encode($dados, JSON_UNESCAPED_UNICODE) ?>;

    const labels = dados.map(function(r) { return r.data_br || r.data; });
    const saldos = dados.map(function(r) { return parseFloat(r.saldo_acumulado || 0); });
    const receitas = dados.map(function(r) { return parseFloat(r.receitas || 0); });
    const despesas = dados.map(function(r) { return parseFloat(r.despesas || 0); });

    if (window.Chart) {
        new Chart(document.getElementById('chartSaldo').getContext('2d'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Saldo Acumulado (R$)',
                    data: saldos,
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13,110,253,0.15)',
                    fill: true,
                    tension: 0.25
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: false } }
            }
        });

        new Chart(document.getElementById('chartRecDesp').getContext('2d'), {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Receitas', data: receitas, backgroundColor: '#198754' },
                    { label: 'Despesas', data: despesas, backgroundColor: '#dc3545' }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // Exportar CSV
    document.getElementById('btnCsv').addEventListener('click', function() {
        let csv = 'Data;Receitas;Despesas;Saldo do Dia;Saldo Acumulado\n';
        dados.forEach(function(r) {
            csv += [
                r.data_br || r.data,
                (r.receitas || 0).toFixed(2).replace('.', ','),
                (r.despesas || 0).toFixed(2).replace('.', ','),
                (r.saldo_dia || 0).toFixed(2).replace('.', ','),
                (r.saldo_acumulado || 0).toFixed(2).replace('.', ',')
            ].join(';') + '\n';
        });
        const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'fluxo_caixa_<?= date('Ymd_His') ?>.csv';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });
});
</script>
<?php endif; ?>
