<?php declare(strict_types=1);
/**
 * @file    topbar.php
 * @package App\Layouts
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Topbar com botão hamburguer (mobile), breadcrumb, relógio e
 *          dropdown do usuário. Abre a div main-content que será fechada
 *          no footer.php.
 *
 * Variáveis esperadas:
 *  - $pageTitle (string) — usado no breadcrumb.
 */
use App\Core\Auth;
use App\Helpers\Format;

$pageTitle = $pageTitle ?? 'Dashboard';
$user = Auth::user();
$userName = $user['nome'] ?? 'Visitante';
$userPerfil = $user['perfil'] ?? '';
$fotoPath = $user['foto_path'] ?? '';

$perfilBadge = [
    'ADMIN'        => '<span class="badge bg-danger">Administrador</span>',
    'OPERADOR'     => '<span class="badge bg-primary">Operador</span>',
    'VISUALIZADOR' => '<span class="badge bg-secondary">Visualizador</span>',
];
$perfilHtml = $perfilBadge[$userPerfil] ?? htmlspecialchars($userPerfil, ENT_QUOTES, 'UTF-8');

$ultima = !empty($user['ultimo_acesso']) ? Format::datetimeBR($user['ultimo_acesso']) : 'Primeiro acesso';
?>
<div class="main-content flex-grow-1 p-4">
    <nav class="topbar navbar navbar-expand-lg navbar-light bg-white rounded shadow-sm mb-4">
        <div class="container-fluid">
            <button type="button" class="btn btn-light d-lg-none me-2" id="sidebarToggle" aria-label="Abrir menu">
                <i class="bi bi-list fs-5"></i>
            </button>

            <nav aria-label="breadcrumb" class="d-none d-md-block">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php?route=dashboard">Início</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></li>
                </ol>
            </nav>

            <div class="ms-auto d-flex align-items-center gap-3">
                <span id="topbar-clock" class="text-muted small font-monospace d-none d-sm-inline" title="Hora atual">
                    <i class="bi bi-clock me-1"></i>--
                </span>

                <div class="dropdown">
                    <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (!empty($fotoPath) && is_file(__DIR__ . '/../../' . ltrim($fotoPath, '/'))): ?>
                            <img src="<?= htmlspecialchars($fotoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Foto" class="rounded-circle me-2" width="32" height="32">
                        <?php else: ?>
                            <span class="avatar-circle rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center me-2" style="width:32px;height:32px;font-weight:600;">
                                <?= htmlspecialchars(mb_substr($userName, 0, 1), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php endif; ?>
                        <span class="text-dark small d-none d-md-inline">
                            <span class="fw-semibold"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span><br>
                            <span class="text-muted" style="font-size:0.72rem;"><?= $perfilHtml ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li class="px-3 py-2 small text-muted border-bottom d-md-none">
                            <div class="fw-semibold text-dark"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></div>
                            <?= $perfilHtml ?>
                        </li>
                        <li><span class="dropdown-item-text small text-muted">Último acesso: <?= $ultima ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?route=perfil"><i class="bi bi-person me-2"></i>Meu Perfil</a></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <script>
        // Toggle sidebar em mobile
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('appSidebar');
            const toggle  = document.getElementById('sidebarToggle');
            const close   = document.getElementById('sidebarClose');
            if (toggle && sidebar) {
                toggle.addEventListener('click', function() { sidebar.classList.add('show'); });
            }
            if (close && sidebar) {
                close.addEventListener('click', function() { sidebar.classList.remove('show'); });
            }
            // Fecha sidebar ao clicar em link (mobile)
            if (sidebar) {
                sidebar.querySelectorAll('a.nav-link').forEach(function(a) {
                    a.addEventListener('click', function() {
                        if (window.matchMedia('(max-width: 991.98px)').matches) {
                            sidebar.classList.remove('show');
                        }
                    });
                });
            }
        });
    </script>
