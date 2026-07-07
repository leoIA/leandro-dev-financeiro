<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $planoTree  Árvore de plano de contas [{id, codigo, nome, tipo, nivel, ativo, children:[...]}].
 */
use App\Helpers\Format;

$planoTree = $planoTree ?? [];

$tipoBadge = [
    'RECEITA' => '<span class="badge bg-success">Receita</span>',
    'DESPESA' => '<span class="badge bg-danger">Despesa</span>',
    'NEUTRO'  => '<span class="badge bg-secondary">Neutro</span>',
];

// Renderização recursiva da árvore.
$renderNode = static function (array $node, int $level) use (&$renderNode, $tipoBadge): string {
    $pad     = ($level * 20) . 'px';
    $id      = (int)($node['id'] ?? 0);
    $codigo  = htmlspecialchars($node['codigo'] ?? '', ENT_QUOTES, 'UTF-8');
    $nome    = htmlspecialchars($node['nome'] ?? '', ENT_QUOTES, 'UTF-8');
    $tipo    = $node['tipo'] ?? 'NEUTRO';
    $tipoH   = $tipoBadge[$tipo] ?? $tipoBadge['NEUTRO'];
    $ativo   = !empty($node['ativo']);
    $status  = $ativo ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>';
    $children = $node['children'] ?? [];
    $hasFilhos = !empty($children);

    $toggle = $hasFilhos
        ? '<button type="button" class="btn btn-sm btn-link p-0 me-1 tree-toggle" data-target="node-' . $id . '" title="Expandir/Recolher"><i class="bi bi-caret-down-fill"></i></button>'
        : '<span class="btn-link p-0 me-1 d-inline-block text-muted" style="width:1.5rem;"><i class="bi bi-dot"></i></span>';

    $actions  = '<div class="btn-group btn-group-sm float-end" role="group">';
    $actions .= '<a href="index.php?route=plano-contas/novo?parent=' . $id . '" class="btn btn-outline-success" title="Adicionar filho"><i class="bi bi-plus-lg"></i></a>';
    $actions .= '<a href="index.php?route=plano-contas/' . $id . '/editar" class="btn btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>';
    if ($ativo) {
        $actions .= '<form method="post" action="index.php?route=plano-contas/' . $id . '/desativar" class="d-inline" data-confirm="Desativar esta conta do plano?">';
        $actions .= \App\Core\Csrf::field();
        $actions .= '<button type="submit" class="btn btn-outline-danger" title="Desativar"><i class="bi bi-x-circle"></i></button>';
        $actions .= '</form>';
    } else {
        $actions .= '<form method="post" action="index.php?route=plano-contas/' . $id . '/ativar" class="d-inline">';
        $actions .= \App\Core\Csrf::field();
        $actions .= '<button type="submit" class="btn btn-outline-success" title="Reativar"><i class="bi bi-check-circle"></i></button>';
        $actions .= '</form>';
    }
    $actions .= '</div>';

    $html  = '<div class="tree-item" data-node="node-' . $id . '" style="padding-left:' . $pad . ';">';
    $html .= '<div class="d-flex align-items-center justify-content-between">';
    $html .= '<div class="d-flex align-items-center flex-grow-1">' . $toggle . '<code class="me-2">' . $codigo . '</code><span class="me-2">' . $nome . '</span>' . $tipoH . ' ' . $status . '</div>';
    $html .= $actions;
    $html .= '</div>';

    if ($hasFilhos) {
        $html .= '<div class="tree-children" data-children="node-' . $id . '">';
        foreach ($children as $child) {
            $html .= $renderNode($child, $level + 1);
        }
        $html .= '</div>';
    }

    $html .= '</div>';
    return $html;
};
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Plano de Contas', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="btn-toolbar gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnExpandAll"><i class="bi bi-arrows-angle-expand"></i> Expandir</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCollapseAll"><i class="bi bi-arrows-angle-contract"></i> Recolher</button>
            <a href="index.php?route=plano-contas/novo" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg"></i> Nova Conta Raiz
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($planoTree)): ?>
                <?php $message = 'Nenhum plano de contas cadastrado. Clique em "Nova Conta Raiz".'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <?php foreach ($planoTree as $node): ?>
                    <?= $renderNode($node, 0) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function toggleChildren(btn) {
        const target = btn.dataset.target;
        const childWrap = document.querySelector('[data-children="' + target + '"]');
        if (!childWrap) { return; }
        const icon = btn.querySelector('i.bi');
        if (childWrap.style.display === 'none') {
            childWrap.style.display = '';
            if (icon) { icon.classList.remove('bi-caret-right-fill'); icon.classList.add('bi-caret-down-fill'); }
        } else {
            childWrap.style.display = 'none';
            if (icon) { icon.classList.remove('bi-caret-down-fill'); icon.classList.add('bi-caret-right-fill'); }
        }
    }

    document.querySelectorAll('.tree-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() { toggleChildren(btn); });
    });

    document.getElementById('btnExpandAll')?.addEventListener('click', function() {
        document.querySelectorAll('.tree-children').forEach(function(w) { w.style.display = ''; });
        document.querySelectorAll('.tree-toggle i.bi').forEach(function(i) {
            i.classList.remove('bi-caret-right-fill'); i.classList.add('bi-caret-down-fill');
        });
    });

    document.getElementById('btnCollapseAll')?.addEventListener('click', function() {
        document.querySelectorAll('.tree-children').forEach(function(w) { w.style.display = 'none'; });
        document.querySelectorAll('.tree-toggle i.bi').forEach(function(i) {
            i.classList.remove('bi-caret-down-fill'); i.classList.add('bi-caret-right-fill');
        });
    });
});
</script>
