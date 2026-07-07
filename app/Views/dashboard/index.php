<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $contas              Lista de contas ativas com saldoAtual.
 * @var float  $saldoGeral          Soma dos saldos de todas as contas.
 * @var array  $totaisByMes         Últimos 6 meses [{mes, receitas, despesas}].
 * @var array  $proximasVencimentos Próximas contas programadas (<= 30 dias).
 * @var array  $ultimosLancamentos  10 lançamentos mais recentes.
 */
use App\Core\Auth;
use App\Helpers\Format;

$user = Auth::user();
$contas              = $contas              ?? [];
$saldoGeral          = $saldoGeral          ?? 0.0;
$totaisByMes         = $totaisByMes         ?? [];
$proximasVencimentos = $proximasVencimentos ?? [];
$ultimosLancamentos  = $ultimosLancamentos  ?? [];

$hoje = date('d/m/Y');
$qtVenc = count($proximasVencimentos);

$tipoContaBadge = [
    'BANCO'    => '<span class="badge bg-primary"><i class="bi bi-bank me-1"></i>Banco</span>',
    'CAIXA'    => '<span class="badge bg-success"><i class="bi bi-cash-coin me-1"></i>Caixa</span>',
    'ASAAS'    => '<span class="badge bg-info text-dark"><i class="bi bi-credit-card me-1"></i>Asaas</span>',
    'CARTEIRA' => '<span class="badge bg-warning text-dark"><i class="bi bi-wallet2 me-1"></i>Carteira</span>',
    'OUTRO'    => '<span class="badge bg-secondary"><i class="bi bi-wallet me-1"></i>Outro</span>',
];

$statusBadge = [
    'PENDENTE'  => '<span class="badge bg-warning text-dark">Pendente</span>',
    'PAGO'      => '<span class="badge bg-success">Pago</span>',
    'CANCELADO' => '<span class="badge bg-danger">Cancelado</span>',
];
?>
<div class="container-fluid">

    <!-- Card de boas-vindas -->
    <div class="card bg-primary text-white mb-4 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-1">
                <i class="bi bi-hand-thumbs-up me-2"></i>
                Bem-vindo(a), <?= htmlspecialchars($user['nome'] ?? 'Usuário', ENT_QUOTES, 'UTF-8') ?>!
            </h5>
            <p class="card-text mb-1">
                Sistema financeiro da <strong>MM Construtora</strong>. Hoje é <strong><?= $hoje ?></strong>.
            </p>
            <p class="card-text mb-0">
                Você tem <strong><?= $qtVenc ?></strong> conta(s) a vencer nos próximos 7 dias.
                <?php if ($qtVenc > 0): ?>
                    <a href="#proximas" class="text-white text-decoration-underline">Ver contas programadas</a>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <!-- Section: Contas -->
    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><i class="bi bi-bank me-2 text-primary"></i>Contas</h2>
            <a href="/contas" class="btn btn-sm btn-outline-primary">Gerenciar contas</a>
        </div>
        <div class="row g-3">
            <?php if (empty($contas)): ?>
                <div class="col-12">
                    <?php $message = 'Nenhuma conta ativa cadastrada.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                </div>
            <?php else: ?>
                <?php foreach ($contas as $c):
                    $saldo = (float)($c['saldoAtual'] ?? $c['saldo_atual'] ?? 0);
                    $saldoClass = $saldo > 0 ? 'text-success' : ($saldo < 0 ? 'text-danger' : 'text-muted');
                ?>
                    <div class="col-sm-6 col-lg-4 col-xl-3">
                        <div class="card card-saldo shadow-sm h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></h6>
                                        <div class="small text-muted"><?= $tipoContaBadge[$c['tipo'] ?? 'OUTRO'] ?? $tipoContaBadge['OUTRO'] ?></div>
                                    </div>
                                    <a href="/contas/<?= (int)($c['id'] ?? 0) ?>/extrato" class="btn btn-sm btn-outline-secondary" title="Ver extrato">
                                        <i class="bi bi-list-ul"></i>
                                    </a>
                                </div>
                                <div class="fs-4 fw-bold <?= $saldoClass ?> mt-3"><?= Format::moneyBRL($saldo) ?></div>
                                <div class="small text-muted">Saldo em <?= $hoje ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- SALDO GERAL -->
                <div class="col-sm-6 col-lg-4 col-xl-3">
                    <div class="card bg-dark text-white shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="card-title mb-0">Saldo Geral</h6>
                                    <div class="small text-warning">Consolidado</div>
                                </div>
                                <i class="bi bi-wallet2 fs-4"></i>
                            </div>
                            <div class="fs-4 fw-bold mt-3 <?= $saldoGeral >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= Format::moneyBRL((float)$saldoGeral) ?>
                            </div>
                            <div class="small text-light opacity-75">Saldo em <?= $hoje ?></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section: Fluxo de Caixa (6 meses) -->
    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Fluxo de Caixa (Últimos 6 Meses)</h2>
            <a href="/relatorios/fluxo-caixa" class="btn btn-sm btn-outline-primary">Ver relatório completo</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($totaisByMes)): ?>
                    <?php $message = 'Sem dados de fluxo de caixa para exibir.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                <?php else: ?>
                    <canvas id="chartFluxo" height="80"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Section: Próximas Contas Programadas -->
    <section class="mb-4" id="proximas">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><i class="bi bi-calendar-event me-2 text-primary"></i>Próximas Contas Programadas</h2>
            <a href="/contas-programadas" class="btn btn-sm btn-outline-primary">Ver todas</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($proximasVencimentos)): ?>
                    <?php $message = 'Nenhuma conta programada prevista para os próximos 30 dias.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="tblProximas" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Vencimento</th>
                                    <th>Conta</th>
                                    <th>Cliente / Fornecedor</th>
                                    <th>Plano de Conta</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($proximasVencimentos as $p): ?>
                                    <tr>
                                        <td><?= Format::dateBR($p['proxima_geracao'] ?? $p['data_vencimento'] ?? null) ?></td>
                                        <td><?= htmlspecialchars($p['conta_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($p['favorecido'] ?? $p['nome_razao_social'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($p['plano_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end text-money-<?= ($p['tipo'] ?? 'DESPESA') === 'RECEITA' ? 'positive' : 'negative' ?>">
                                            <?= Format::moneyBRL((float)($p['valor'] ?? 0)) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Section: Últimos Lançamentos -->
    <section class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Últimos Lançamentos</h2>
            <a href="/lancamentos" class="btn btn-sm btn-outline-primary">Ver todos</a>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php if (empty($ultimosLancamentos)): ?>
                    <?php $message = 'Nenhum lançamento registrado ainda.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                <?php else: ?>
                    <div class="table-responsive">
                        <table id="tblUltimos" class="table table-striped table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Conta</th>
                                    <th>Data</th>
                                    <th>Favorecido</th>
                                    <th>Plano de Conta</th>
                                    <th class="text-end">Valor</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimosLancamentos as $l):
                                    $tipo = $l['tipo'] ?? '';
                                    $fav = '';
                                    if ($tipo === 'TRANSFERENCIA') {
                                        // Para transferência o "favorecido" indica a contraparte.
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
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($l['conta_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= Format::dateBR($l['data_lancamento'] ?? null) ?></td>
                                        <td><?= $fav ?></td>
                                        <td><?= htmlspecialchars($l['plano_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end text-money-<?= $tipo === 'RECEITA' ? 'positive' : ($tipo === 'DESPESA' ? 'negative' : 'positive') ?>">
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
    </section>
</div>

<?php if (!empty($totaisByMes)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!window.Chart) { return; }
    new Chart(document.getElementById('chartFluxo').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_map(static fn($t) => $t['mes'] ?? '', $totaisByMes), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: 'Receitas',
                    data: <?= json_encode(array_map(static fn($t) => (float)($t['receitas'] ?? 0), $totaisByMes), JSON_UNESCAPED_UNICODE) ?>,
                    borderColor: '#198754',
                    backgroundColor: 'rgba(25,135,84,0.1)',
                    fill: true,
                    tension: 0.25
                },
                {
                    label: 'Despesas',
                    data: <?= json_encode(array_map(static fn($t) => (float)($t['despesas'] ?? 0), $totaisByMes), JSON_UNESCAPED_UNICODE) ?>,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220,53,69,0.1)',
                    fill: true,
                    tension: 0.25
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return 'R$ ' + Number(v).toLocaleString('pt-BR'); } }
                }
            }
        }
    });

    // DataTables
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblProximas').DataTable({ pageLength: 5, order: [[0, 'asc']] });
        jQuery('#tblUltimos').DataTable({ pageLength: 5, order: [[1, 'desc']] });
    }
});
</script>
<?php endif; ?>
