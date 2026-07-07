<?php declare(strict_types=1);
/** @var array $usuarios */
/** @var string $pageTitle */
use App\Helpers\Format;
use App\Core\Auth;
$currentUser = Auth::user();
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Usuários', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="/usuarios/novo" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Novo Usuário
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($usuarios ?? [])): ?>
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblUsuarios" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Perfil</th>
                                <th>Último Acesso</th>
                                <th>Status</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usuarios as $u): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($u['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (!empty($currentUser) && (int)($currentUser['id'] ?? 0) === (int)($u['id'] ?? 0)): ?>
                                            <span class="badge bg-info text-dark ms-1">Você</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php
                                        $perfil = $u['perfil'] ?? '';
                                        $badges = [
                                            'ADMIN' => '<span class="badge bg-danger">Administrador</span>',
                                            'OPERADOR' => '<span class="badge bg-primary">Operador</span>',
                                            'VISUALIZADOR' => '<span class="badge bg-secondary">Visualizador</span>',
                                        ];
                                        echo $badges[$perfil] ?? htmlspecialchars($perfil, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </td>
                                    <td><?= !empty($u['ultimo_acesso']) ? Format::dateBR($u['ultimo_acesso']) . ' ' . date('H:i', strtotime($u['ultimo_acesso'])) : '<span class="text-muted">Nunca</span>' ?></td>
                                    <td>
                                        <?php if (!empty($u['ativo'])): ?>
                                            <span class="badge bg-success">Ativo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="/usuarios/<?= (int)$u['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (!empty($u['ativo'])): ?>
                                            <?php if (empty($currentUser) || (int)($currentUser['id'] ?? 0) !== (int)($u['id'] ?? 0)): ?>
                                                <form method="post" action="/usuarios/<?= (int)$u['id'] ?>/desativar" class="d-inline" data-confirm="Desativar este usuário? Ele não poderá mais acessar o sistema.">
                                                    <?= App\Core\Csrf::field() ?>
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desativar">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Não é possível desativar a si mesmo">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <form method="post" action="/usuarios/<?= (int)$u['id'] ?>/ativar" class="d-inline">
                                                <?= App\Core\Csrf::field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-success" title="Reativar">
                                                    <i class="bi bi-check-circle"></i>
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
        jQuery('#tblUsuarios').DataTable({
            order: [[0, 'asc']],
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }
});
</script>
