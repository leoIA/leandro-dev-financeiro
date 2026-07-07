<?php declare(strict_types=1);
/** @var array $usuario */
/** @var string $pageTitle */
/** @var array $old */
use App\Core\Auth;
$u = $usuario ?? Auth::user() ?? [];
$old = $old ?? [];
$val = function($field, $default = '') use ($u, $old) {
    return $old[$field] ?? $u[$field] ?? $default;
};
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Meu Perfil', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card shadow-sm text-center">
                <div class="card-body">
                    <?php $foto = $val('foto_path'); ?>
                    <?php if (!empty($foto) && file_exists(__DIR__ . '/../../../storage/uploads/' . basename($foto))): ?>
                        <img src="<?= htmlspecialchars('/storage/uploads/' . basename($foto), ENT_QUOTES, 'UTF-8') ?>"
                             class="rounded-circle mb-3" style="width:120px;height:120px;object-fit:cover" alt="Foto">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3"
                             style="width:120px;height:120px;font-size:3rem">
                            <i class="bi bi-person"></i>
                        </div>
                    <?php endif; ?>
                    <h5 class="mb-1"><?= htmlspecialchars($val('nome'), ENT_QUOTES, 'UTF-8') ?></h5>
                    <p class="text-muted small mb-1"><?= htmlspecialchars($val('email'), ENT_QUOTES, 'UTF-8') ?></p>
                    <?php
                    $perfil = $val('perfil');
                    $badges = [
                        'ADMIN' => '<span class="badge bg-danger">Administrador</span>',
                        'OPERADOR' => '<span class="badge bg-primary">Operador</span>',
                        'VISUALIZADOR' => '<span class="badge bg-secondary">Visualizador</span>',
                    ];
                    echo $badges[$perfil] ?? htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8');
                    ?>
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-body">
                    <form method="post" action="/perfil" class="needs-validation" novalidate enctype="multipart/form-data">
                        <?= App\Core\Csrf::field() ?>
                        <input type="hidden" name="_method" value="PUT">

                        <div class="row g-3">
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
                                <div class="invalid-feedback">Informe um email válido.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="foto" class="form-label">Foto</label>
                                <input type="file" class="form-control" id="foto" name="foto" accept="image/png,image/jpeg,image/jpg">
                                <small class="form-text text-muted">JPG ou PNG, máximo 2MB. Será redimensionada para 200x200.</small>
                            </div>

                            <div class="col-md-6">
                                <label for="senha" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="senha" name="senha" minlength="8"
                                       autocomplete="new-password" placeholder="Deixe em branco para manter a atual">
                                <div class="progress mt-1" style="height:6px">
                                    <div id="senhaBar" class="progress-bar" role="progressbar" style="width:0%"></div>
                                </div>
                                <small id="senhaHelp" class="form-text text-muted">Opcional. Mínimo 8 caracteres.</small>
                            </div>

                            <div class="col-md-6">
                                <label for="confirmar_senha" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha" minlength="8"
                                       autocomplete="new-password">
                                <div class="invalid-feedback">As senhas não conferem.</div>
                            </div>
                        </div>

                        <hr>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="/dashboard" class="btn btn-outline-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Salvar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const senha = document.getElementById('senha');
    const bar = document.getElementById('senhaBar');
    const help = document.getElementById('senhaHelp');
    function calcularForca() {
        const v = senha.value;
        if (!v) { bar.style.width = '0%'; help.textContent = 'Opcional. Mínimo 8 caracteres.'; return; }
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
