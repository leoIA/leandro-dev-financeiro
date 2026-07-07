<?php declare(strict_types=1);
/** @var array $usuario */
/** @var string $pageTitle */
/** @var array $old */
/** @var array $modulosPermissoes */
/** @var array $permissoesUsuario */
use App\Core\Auth;
$u = $usuario ?? [];
$old = $old ?? [];
$permissoesUsuario = $permissoesUsuario ?? [];
$currentUser = Auth::user();
$ehMesmoUsuario = !empty($currentUser) && (int)($currentUser['id'] ?? 0) === (int)($u['id'] ?? 0);
$val = function($field, $default = '') use ($u, $old) {
    return $old[$field] ?? $u[$field] ?? $default;
};

if (empty($modulosPermissoes)) {
    $modulosPermissoes = [
        'dashboard'           => ['label' => 'Dashboard',          'acoes' => ['read']],
        'contas'              => ['label' => 'Contas',             'acoes' => ['read','create','update','delete']],
        'plano_contas'        => ['label' => 'Plano de Contas',    'acoes' => ['read','create','update','delete']],
        'lancamentos'         => ['label' => 'Lançamentos',        'acoes' => ['read','create','update','delete']],
        'contas_programadas'  => ['label' => 'Contas Programadas', 'acoes' => ['read','create','update','delete']],
        'transferencias'      => ['label' => 'Transferências',     'acoes' => ['read','create']],
        'clientes_fornecedores' => ['label' => 'Clientes / Fornecedores', 'acoes' => ['read','create','update','delete']],
        'relatorios'          => ['label' => 'Relatórios',         'acoes' => ['read']],
        'usuarios'            => ['label' => 'Usuários',           'acoes' => ['read','create','update','delete']],
        'configuracoes'       => ['label' => 'Configurações',      'acoes' => ['read','update']],
        'backups'             => ['label' => 'Backup / Restore',   'acoes' => ['read','create','delete']],
    ];
}
$acaoLabels = [
    'read'   => 'Visualizar',
    'create' => 'Criar',
    'update' => 'Editar',
    'delete' => 'Excluir / Desativar',
];
$perms = !empty($old['permissoes']) ? $old['permissoes'] : $permissoesUsuario;
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Editar Usuário', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="/usuarios" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <?php if ($ehMesmoUsuario): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i> Você está editando seu próprio usuário. Não é possível alterar seu perfil ou status de ativo.
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/usuarios/<?= (int)($u['id'] ?? 0) ?>" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_method" value="PUT">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="200"
                               value="<?= htmlspecialchars($val('nome'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o nome.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= htmlspecialchars($val('email'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe um email único e válido.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" minlength="8"
                               autocomplete="new-password" placeholder="Deixe em branco para manter a atual">
                        <div class="progress mt-1" style="height:6px">
                            <div id="senhaBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                        </div>
                        <small id="senhaHelp" class="form-text text-muted">Opcional. Preencha apenas para alterar.</small>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmar_senha" class="form-label">Confirmar Senha</label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" minlength="8"
                               autocomplete="new-password" placeholder="Repita a nova senha">
                        <div class="invalid-feedback">As senhas não conferem.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                        <select class="form-select" id="perfil" name="perfil" required <?= $ehMesmoUsuario ? 'disabled' : '' ?>>
                            <option value="">Selecione…</option>
                            <option value="ADMIN" <?= ($val('perfil') === 'ADMIN') ? 'selected' : '' ?>>Administrador</option>
                            <option value="OPERADOR" <?= ($val('perfil') === 'OPERADOR') ? 'selected' : '' ?>>Operador</option>
                            <option value="VISUALIZADOR" <?= ($val('perfil') === 'VISUALIZADOR') ? 'selected' : '' ?>>Visualizador</option>
                        </select>
                        <?php if ($ehMesmoUsuario): ?>
                            <input type="hidden" name="perfil" value="<?= htmlspecialchars($val('perfil'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?= ($val('ativo', '1') === '1' || $val('ativo', 1) == 1) ? 'checked' : '' ?>
                                   <?= $ehMesmoUsuario ? 'disabled' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                            <?php if ($ehMesmoUsuario): ?>
                                <input type="hidden" name="ativo" value="1">
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="permissoesWrapper" class="mb-3" style="display:none">
                    <hr>
                    <h5 class="h6">Permissões por Módulo</h5>
                    <p class="text-muted small">Marque as permissões individuais. Administradores possuem acesso total automaticamente.</p>

                    <div class="row g-3">
                        <?php foreach ($modulosPermissoes as $modulo => $cfg): ?>
                            <div class="col-md-6">
                                <div class="card border-light">
                                    <div class="card-header bg-light py-2">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input permissao-todas"
                                                   id="all_<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
                                                   data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>">
                                            <label class="form-check-label fw-bold" for="all_<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($cfg['label'] ?? $modulo, ENT_QUOTES, 'UTF-8') ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body py-2">
                                        <?php foreach ($cfg['acoes'] as $acao): ?>
                                            <div class="form-check form-check-inline">
                                                <input type="checkbox" class="form-check-input permissao-item"
                                                       name="permissoes[<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>.<?= htmlspecialchars($acao, ENT_QUOTES, 'UTF-8') ?>]"
                                                       id="p_<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>_<?= htmlspecialchars($acao, ENT_QUOTES, 'UTF-8') ?>"
                                                       data-modulo="<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>"
                                                       value="1"
                                                       <?= in_array($modulo . '.' . $acao, $perms, true) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="p_<?= htmlspecialchars($modulo, ENT_QUOTES, 'UTF-8') ?>_<?= htmlspecialchars($acao, ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($acaoLabels[$acao] ?? $acao, ENT_QUOTES, 'UTF-8') ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="/usuarios" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const perfil = document.getElementById('perfil');
    const wrapper = document.getElementById('permissoesWrapper');

    function atualizarPermissoes() {
        const show = perfil.value && perfil.value !== 'ADMIN';
        wrapper.style.display = show ? '' : 'none';
    }
    perfil.addEventListener('change', atualizarPermissoes);
    atualizarPermissoes();

    document.querySelectorAll('.permissao-todas').forEach(function(master) {
        master.addEventListener('change', function() {
            const modulo = this.dataset.modulo;
            document.querySelectorAll('.permissao-item[data-modulo="' + modulo + '"]').forEach(function(item) {
                item.checked = master.checked;
            });
        });
    });
    document.querySelectorAll('.permissao-item').forEach(function(item) {
        item.addEventListener('change', function() {
            const modulo = this.dataset.modulo;
            const todos = document.querySelectorAll('.permissao-item[data-modulo="' + modulo + '"]');
            const marcados = Array.from(todos).filter(function(t) { return t.checked; });
            const master = document.getElementById('all_' + modulo);
            if (master) {
                master.checked = marcados.length === todos.length;
                master.indeterminate = marcados.length > 0 && marcados.length < todos.length;
            }
        });
    });

    // Inicializa estado "marcar todas"
    document.querySelectorAll('.permissao-todas').forEach(function(master) {
        const modulo = master.dataset.modulo;
        const todos = document.querySelectorAll('.permissao-item[data-modulo="' + modulo + '"]');
        const marcados = Array.from(todos).filter(function(t) { return t.checked; });
        master.checked = marcados.length === todos.length && todos.length > 0;
        master.indeterminate = marcados.length > 0 && marcados.length < todos.length;
    });

    const senha = document.getElementById('senha');
    const bar = document.getElementById('senhaBar');
    const help = document.getElementById('senhaHelp');
    function calcularForca() {
        const v = senha.value;
        if (!v) { bar.style.width = '0%'; help.textContent = 'Opcional. Preencha apenas para alterar.'; return; }
        let score = 0;
        if (v.length >= 8) score++;
        if (v.length >= 12) score++;
        if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
        if (/\d/.test(v)) score++;
        if (/[^A-Za-z0-9]/.test(v)) score++;
        const pct = Math.min(100, score * 20);
        bar.style.width = pct + '%';
        bar.className = 'progress-bar';
        if (pct <= 40) { bar.classList.add('bg-danger'); help.textContent = 'Senha fraca.'; }
        else if (pct <= 80) { bar.classList.add('bg-warning'); help.textContent = 'Senha média.'; }
        else { bar.classList.add('bg-success'); help.textContent = 'Senha forte.'; }
    }
    senha.addEventListener('input', calcularForca);

    const confirm = document.getElementById('confirmar_senha');
    function validarConfirm() {
        if (confirm.value !== senha.value) {
            confirm.setCustomValidity('As senhas não conferem.');
        } else {
            confirm.setCustomValidity('');
        }
    }
    senha.addEventListener('input', validarConfirm);
    confirm.addEventListener('input', validarConfirm);
});
</script>
