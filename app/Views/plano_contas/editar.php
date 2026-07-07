<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $conta      Plano de conta sendo editado.
 * @var array  $planoTree  Árvore de plano de contas para o select parent_id.
 * @var bool   $hasFilhos  True se a conta tem filhos (bloqueia trocar pai).
 * @var array  $old        Valores para repopular após erro.
 */
$conta     = $conta     ?? [];
$planoTree = $planoTree ?? [];
$hasFilhos = $hasFilhos ?? false;
$old       = $old       ?? [];

$val = static function (string $field, $default = '') use ($conta, $old) {
    return $old[$field] ?? $conta[$field] ?? $default;
};

$renderOptions = static function (array $nodes, int $level = 0, ?int $selected = null, int $excludeId = 0) use (&$renderOptions): string {
    $html = '';
    foreach ($nodes as $node) {
        $id = (int)($node['id'] ?? 0);
        if ($id === $excludeId) {
            // Não permite selecionar a si mesmo nem seus descendentes como pai.
            if (!empty($node['children'])) {
                $html .= $renderOptions($node['children'], $level + 1, $selected, $excludeId);
            }
            continue;
        }
        $indent = str_repeat('— ', $level);
        $sel = ($selected !== null && $selected === $id) ? ' selected' : '';
        $html .= '<option value="' . $id . '"' . $sel . '>'
               . htmlspecialchars($indent . ($node['codigo'] ?? '') . ' — ' . ($node['nome'] ?? ''), ENT_QUOTES, 'UTF-8')
               . '</option>';
        if (!empty($node['children'])) {
            $html .= $renderOptions($node['children'], $level + 1, $selected, $excludeId);
        }
    }
    return $html;
};

$currentParentId = $val('parent_id') !== '' && $val('parent_id') !== null ? (int)$val('parent_id') : null;
$lockParent = $hasFilhos;
$lockTipo   = $currentParentId !== null;

$tipoBadge = [
    'RECEITA' => '<span class="badge bg-success">Receita</span>',
    'DESPESA' => '<span class="badge bg-danger">Despesa</span>',
    'NEUTRO'  => '<span class="badge bg-secondary">Neutro</span>',
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0">
            <i class="bi bi-diagram-3 me-2 text-primary"></i>
            <?= htmlspecialchars($pageTitle ?? 'Editar Conta', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        <a href="/plano-contas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($lockParent): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    Esta conta possui <strong>sub-contas</strong> vinculadas, portanto o <strong>Conta Pai</strong> não pode ser alterado.
                </div>
            <?php endif; ?>

            <form method="post" action="/plano-contas/<?= (int)($conta['id'] ?? 0) ?>" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_method" value="PUT">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="parent_id" class="form-label">Conta Pai</label>
                        <select class="form-select" id="parent_id" name="parent_id" <?= $lockParent ? 'disabled' : '' ?>>
                            <option value="">(Raiz — sem pai)</option>
                            <?= $renderOptions($planoTree, 0, $currentParentId, (int)($conta['id'] ?? 0)) ?>
                        </select>
                        <?php if ($lockParent): ?>
                            <input type="hidden" name="parent_id" value="<?= htmlspecialchars((string)($currentParentId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <small class="form-text text-muted">Bloqueado porque há sub-contas.</small>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="20"
                               value="<?= htmlspecialchars($val('codigo'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o código.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required <?= $lockTipo ? 'disabled' : '' ?>>
                            <option value="RECEITA" <?= $val('tipo') === 'RECEITA' ? 'selected' : '' ?>>Receita</option>
                            <option value="DESPESA" <?= $val('tipo') === 'DESPESA' ? 'selected' : '' ?>>Despesa</option>
                            <option value="NEUTRO"  <?= $val('tipo') === 'NEUTRO'  ? 'selected' : '' ?>>Neutro</option>
                        </select>
                        <?php if ($lockTipo): ?>
                            <input type="hidden" name="tipo" value="<?= htmlspecialchars($val('tipo'), ENT_QUOTES, 'UTF-8') ?>">
                            <small class="form-text text-muted">Bloqueado porque tem pai (herda).</small>
                        <?php endif; ?>
                        <div class="invalid-feedback">Selecione o tipo.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="100"
                               value="<?= htmlspecialchars($val('nome'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o nome.</div>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1"
                                   <?= ($val('ativo', '1') === '1' || $val('ativo', 1) == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="/plano-contas" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
