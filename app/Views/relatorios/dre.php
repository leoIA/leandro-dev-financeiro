<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $mesSelecionado */
/** @var array $meses */
/** @var array $linhas */
/** @var array $totais */
use App\Helpers\Format;
$mesSelecionado = $mesSelecionado ?? date('Y-m');
$linhas = $linhas ?? [];
$totais = $totais ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Relatório: DRE Simplificado', ENT_QUOTES, 'UTF-8') ?></h1>
        <button type="button" class="btn btn-outline-primary btn-sm no-print" onclick="window.print()">
            <i class="bi bi-printer"></i> Imprimir
        </button>
    </div>

    <div class="card shadow-sm mb-3 no-print">
        <div class="card-body">
            <form method="get" action="index.php?route=relatorios/dre" class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mês de Referência</label>
                    <select class="form-select form-select-sm" id="mes" name="mes">
                        <?php foreach (($meses ?? []) as $m): ?>
                            <option value="<?= htmlspecialchars($m['value'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                <?= ($mesSelecionado === ($m['value'] ?? '')) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-bar-chart"></i> Gerar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($linhas)): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between">
                <strong>DRE Simplificado — <?= htmlspecialchars($mesSelecionado, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="text-muted">Valores em R$</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th style="width:55%">Descrição</th>
                                <th class="text-end">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($linhas as $linha): ?>
                                <?php
                                $classe = $linha['classe'] ?? 'normal';
                                $css = '';
                                if ($classe === 'secao') $css = 'fw-bold bg-light';
                                if ($classe === 'subtotal') $css = 'fw-bold border-top';
                                if ($classe === 'resultado') $css = 'fw-bold bg-dark text-white';
                                $prefixo = $linha['prefixo'] ?? '';
                                $valor = $linha['valor'] ?? 0;
                                $valorCss = $valor < 0 ? 'text-money-negative' : ($valor > 0 ? 'text-money-positive' : '');
                                ?>
                                <tr class="<?= htmlspecialchars($css, ENT_QUOTES, 'UTF-8') ?>">
                                    <td style="padding-left: <?= (int)($linha['nivel'] ?? 0) * 20 ?>px">
                                        <?= htmlspecialchars($prefixo . ' ' . ($linha['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td class="text-end <?= htmlspecialchars($valorCss, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= Format::moneyBRL((float)$valor) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
