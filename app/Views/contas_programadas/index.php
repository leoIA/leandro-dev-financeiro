<?php declare(strict_types=1); ?>
<?php /** @var array $programacoes */
/** @var string $pageTitle */
use App\Helpers\Format;
use App\Core\Auth;
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Contas Programadas', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="btn-toolbar gap-2">
            <a href="/contas-programadas/gerar-pendentes" class="btn btn-outline-success btn-sm" data-confirm="Confirma a geração de todos os lançamentos pendentes até hoje?">
                <i class="bi bi-lightning-charge"></i> Gerar Pendentes
            </a>
            <a href="/contas-programadas/novo" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg"></i> Nova Programação
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($programadas ?? $programacoes ?? [])): ?>
                <?php $message = 'Nenhuma programação cadastrada. Clique em "Nova Programação".'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblProgramadas" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Descrição</th>
                                <th>Conta</th>
                                <th>Plano</th>
                                <th>Tipo</th>
                                <th class="text-end">Valor</th>
                                <th>Frequência</th>
                                <th>Próxima Geração</th>
                                <th>Status</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($programadas ?? $programacoes) as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['conta_nome'] ?? $p['conta_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['plano_nome'] ?? $p['plano_conta_id'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php $tipo = $p['tipo'] ?? 'DESPESA'; ?>
                                        <?php if ($tipo === 'RECEITA'): ?>
                                            <span class="badge bg-success">Receita</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Despesa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end text-money-<?= ($p['tipo'] ?? '') === 'RECEITA' ? 'positive' : 'negative' ?>">
                                        <?= Format::moneyBRL((float)($p['valor'] ?? 0)) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $freqLabels = [
                                            'DIARIO' => '<span class="badge bg-secondary">Diária</span>',
                                            'SEMANAL' => '<span class="badge bg-info text-dark">Semanal</span>',
                                            'MENSAL' => '<span class="badge bg-primary">Mensal</span>',
                                            'ANUAL' => '<span class="badge bg-dark">Anual</span>',
                                            'UNICO' => '<span class="badge bg-warning text-dark">Único</span>',
                                        ];
                                        echo $freqLabels[$p['frequencia'] ?? ''] ?? htmlspecialchars($p['frequencia'] ?? '', ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </td>
                                    <td><?= Format::dateBR($p['proxima_geracao'] ?? null) ?></td>
                                    <td>
                                        <?php if (!empty($p['ativo'])): ?>
                                            <span class="badge bg-success">Ativa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativa</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="/contas-programadas/<?= (int)$p['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (!empty($p['ativo'])): ?>
                                            <form method="post" action="/contas-programadas/<?= (int)$p['id'] ?>/desativar" class="d-inline" data-confirm="Desativar esta programação? Ela não gerará mais lançamentos.">
                                                <?= App\Core\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Desativar">
                                                    <i class="bi bi-x-circle"></i>
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
        jQuery('#tblProgramadas').DataTable({
            order: [[6, 'asc']],
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }
});
</script>
