<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $filtros */
/** @var array $porConta */
/** @var array $porPlano */
use App\Helpers\Format;
$filtros = $filtros ?? ['data_inicio' => date('Y-m-01'), 'data_fim' => date('Y-m-d')];
$porConta = $porConta ?? [];
$porPlano = $porPlano ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Relatório: Saldos por Conta e Plano', ENT_QUOTES, 'UTF-8') ?></h1>
        <button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir
        </button>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body">
            <form method="get" action="index.php?route=relatorios/saldos" class="row g-2 align-items-end">
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
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-bar-chart"></i> Gerar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light"><strong>Saldos por Conta</strong></div>
        <div class="card-body">
            <?php if (empty($porConta)): ?>
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Conta</th>
                                <th class="text-end">Saldo Inicial</th>
                                <th class="text-end">Receitas</th>
                                <th class="text-end">Despesas</th>
                                <th class="text-end">Saldo Atual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($porConta as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['conta'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end"><?= Format::moneyBRL((float)($row['saldo_inicial'] ?? 0)) ?></td>
                                    <td class="text-end text-money-positive"><?= Format::moneyBRL((float)($row['receitas'] ?? 0)) ?></td>
                                    <td class="text-end text-money-negative"><?= Format::moneyBRL((float)($row['despesas'] ?? 0)) ?></td>
                                    <td class="text-end fw-bold <?= ($row['saldo_atual'] ?? 0) >= 0 ? 'text-money-positive' : 'text-money-negative' ?>">
                                        <?= Format::moneyBRL((float)($row['saldo_atual'] ?? 0)) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light"><strong>Saldos por Plano de Contas</strong></div>
        <div class="card-body">
            <?php if (empty($porPlano)): ?>
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover">
                        <thead>
                            <tr>
                                <th>Plano</th>
                                <th class="text-end">Receitas</th>
                                <th class="text-end">Despesas</th>
                                <th class="text-end">Saldo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($porPlano as $row): ?>
                                <?php $isGrupo = !empty($row['is_grupo']); ?>
                                <tr class="<?= $isGrupo ? 'fw-bold bg-light' : '' ?>">
                                    <td style="padding-left: <?= (int)($row['nivel'] ?? 0) * 20 ?>px">
                                        <?= htmlspecialchars($row['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        <?= htmlspecialchars($row['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="text-end text-money-positive"><?= Format::moneyBRL((float)($row['receitas'] ?? 0)) ?></td>
                                    <td class="text-end text-money-negative"><?= Format::moneyBRL((float)($row['despesas'] ?? 0)) ?></td>
                                    <td class="text-end <?= ($row['saldo'] ?? 0) >= 0 ? 'text-money-positive' : 'text-money-negative' ?>">
                                        <?= Format::moneyBRL((float)($row['saldo'] ?? 0)) ?>
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
