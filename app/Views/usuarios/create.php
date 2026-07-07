<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $old */
/** @var array $modulosPermissoes */
$old = $old ?? [];

// Estrutura default: [modulo => [label, [acoes => [read, create, update, delete]]]]
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
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Novo Usuário', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=usuarios" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=usuarios" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nome" name="nome" required maxlength="200"
                               value="<?= htmlspecialchars($old['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o nome.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe um email único e válido.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="senha" class="form-label">Senha <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="senha" name="senha" required minlength="8"
                               value="<?= htmlspecialchars($old['senha'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password">
                        <div class="progress mt-1" style="height:6px">
                            <div id="senhaBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                        </div>
                        <small id="senhaHelp" class="form-text text-muted">Mínimo 8 caracteres, com letras, números e símbolos.</small>
                        <div class="invalid-feedback">Senha obrigatória (mínimo 8 caracteres).</div>
                    </div>
                    <div class="col-md-6">
                        <label for="confirmar_senha" class="form-label">Confirmar Senha <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" required minlength="8"
                               value="<?= htmlspecialchars($old['confirmar_senha'] ?? '', ENT_QUOTES, 'UTF-8') ?>" autocomplete="new-password">
                        <div class="invalid-feedback">As senhas não conferem.</div>
                    </div>

                    <div class="col-md-4">
                        <label for="perfil" class="form-label">Perfil <span class="text-danger">*</span></label>
                        <select class="form-select" id="perfil" name="perfil" required>
                            <option value="">Selecione…</option>
                            <option value="ADMIN" <?= ($old['perfil'] ?? '') === 'ADMIN' ? 'selected' : '' ?>>Administrador</option>
                            <option value="OPERADOR" <?= ($old['perfil'] ?? '') === 'OPERADOR' ? 'selected' : '' ?>>Operador</option>
                            <option value="VISUALIZADOR" <?= ($old['perfil'] ?? '') === 'VISUALIZADOR' ? 'selected' : '' ?>>Visualizador</option>
                        </select>
                        <div class="invalid-feedback">Selecione o perfil.</div>
                    </div>

                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?= (!isset($old['ativo']) || $old['ativo'] === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                </div>

                <div id="permissoesWrapper" class="mb-3" style="display:none">
                    <hr>
                    <h5 class="h6">Permissões por Módulo</h5>
                    <p class="text-muted small">Marque as permissões individuais para este usuário. Administradores possuem acesso total automaticamente.</p>

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
                                                       <?= in_array($modulo . '.' . $acao, ($old['permissoes'] ?? []), true) ? 'checked' : '' ?>>
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
                    <a href="index.php?route=usuarios" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Usuário
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

    // Marcar/desmarcar todas por módulo
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

    // Medidor de força de senha
    const senha = document.getElementById('senha');
    const bar = document.getElementById('senhaBar');
    const help = document.getElementById('senhaHelp');
    function calcularForca() {
        const v = senha.value;
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

    // Confirmar senha
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
