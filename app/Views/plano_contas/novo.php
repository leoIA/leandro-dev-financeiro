<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $planoTree  Árvore de plano de contas para o select parent_id.
 * @var array  $old        Valores para repopular após erro.
 * @var int|null $parentId parent_id sugerido via query string ?parent=X.
 */
use App\Core\Request;

$planoTree = $planoTree ?? [];
$old       = $old       ?? [];
$parentId  = $parentId  ?? Request::get('parent');

// Renderiza options recursivamente (com indentação).
$renderOptions = static function (array $nodes, int $level = 0, ?int $selected = null) use (&$renderOptions): string {
    $html = '';
    foreach ($nodes as $node) {
        $indent = str_repeat('— ', $level);
        $sel = ((int)$selected === (int)($node['id'] ?? 0)) ? ' selected' : '';
        $html .= '<option value="' . (int)($node['id'] ?? 0) . '"' . $sel . '>'
               . htmlspecialchars($indent . ($node['codigo'] ?? '') . ' — ' . ($node['nome'] ?? ''), ENT_QUOTES, 'UTF-8')
               . '</option>';
        if (!empty($node['children'])) {
            $html .= $renderOptions($node['children'], $level + 1, $selected);
        }
    }
    return $html;
};

// Tipo do parent (se houver) — herda.
$parentTipo = null;
if (!empty($parentId)) {
    $findTipo = static function (array $nodes, int $target) use (&$findTipo): ?string {
        foreach ($nodes as $n) {
            if ((int)($n['id'] ?? 0) === $target) { return $n['tipo'] ?? null; }
            if (!empty($n['children'])) {
                $r = $findTipo($n['children'], $target);
                if ($r !== null) { return $r; }
            }
        }
        return null;
    };
    $parentTipo = $findTipo($planoTree, (int)$parentId);
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>
            <?= htmlspecialchars($pageTitle ?? 'Nova Conta', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <a href="index.php?route=plano-contas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=plano-contas" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">Conta Pai</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">(Raiz — sem pai)</option>
                            <?= $renderOptions($planoTree, 0, $parentId !== null && $parentId !== '' ? (int)$parentId : null) ?>
                        </select>
                        <small class="form-text text-muted">Selecione o pai para criar uma sub-conta. O tipo será herdado.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="20"
                               value="<?= htmlspecialchars($old['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex.: 1.1.01">
                        <div class="invalid-feedback">Informe o código.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required <?= $parentTipo !== null ? 'disabled' : '' ?>>
                            <option value="">Selecione…</option>
                            <option value="RECEITA" <?= (($old['tipo'] ?? $parentTipo) === 'RECEITA') ? 'selected' : '' ?>>Receita</option>
                            <option value="DESPESA" <?= (($old['tipo'] ?? $parentTipo) === 'DESPESA') ? 'selected' : '' ?>>Despesa</option>
                            <option value="NEUTRO"  <?= (($old['tipo'] ?? $parentTipo) === 'NEUTRO')  ? 'selected' : '' ?>>Neutro</option>
                        </select>
                        <?php if ($parentTipo !== null): ?>
                            <input type="hidden" name="tipo" value="<?= htmlspecialchars($parentTipo, ENT_QUOTES, 'UTF-8') ?>">
                            <small class="form-text text-muted">Tipo herdado do pai: <strong><?= htmlspecialchars($parentTipo, ENT_QUOTES, 'UTF-8') ?></strong>.</small>
                        <?php endif; ?>
                        <div class="invalid-feedback">Selecione o tipo.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="100"
                               value="<?= htmlspecialchars($old['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o nome.</div>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1"
                                   <?= (($old['ativo'] ?? '1') === '1' || !isset($old['ativo'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?route=plano-contas" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
