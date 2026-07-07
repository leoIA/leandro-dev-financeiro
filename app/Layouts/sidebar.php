<?php declare(strict_types=1);
/**
 * @file    sidebar.php
 * @package App\Layouts
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Sidebar com logo, menu de navegação e versão.
 *          Items admin-only (Usuários, Configurações, Backup) aparecem
 *          apenas quando o usuário autenticado tem perfil ADMIN.
 *
 * CORREÇÃO BUG #7: URLs relativas (index.php?route=...) para funcionar em subdiretório
 * CORREÇÃO BUG #9: active state baseado em $_GET['route'] (não em parse_url path)
 */
use App\Core\Auth;

$user = Auth::user();
$isAdmin = ($user['perfil'] ?? null) === 'ADMIN';

// CORREÇÃO BUG #9: Detectar rota ativa via $_GET['route'] (com fallback para path)
$currentRoute = $_GET['route'] ?? '';
if ($currentRoute === '') {
    $uriPath = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
    $currentRoute = $uriPath;
}
// Pegar apenas o primeiro segmento da rota para comparação
$firstSeg = $currentRoute === '' ? '' : explode('/', $currentRoute)[0];
if ($firstSeg === 'index.php') {
    $firstSeg = '';
}
$isActive = static function (string $route) use ($firstSeg): bool {
    return str_replace('-', '_', $firstSeg) === str_replace('-', '_', $route);
};

// Helper para gerar URL relativa
$Url = static function (string $route): string {
    return 'index.php?route=' . $route;
};

// Catálogo de itens: route, label, icon, url (relativa), adminOnly, permModule
$menuItems = [
    ['route' => 'dashboard',            'label' => 'Dashboard',              'icon' => 'bi-speedometer2',     'url' => $Url('dashboard'),              'adminOnly' => false, 'perm' => null],
    ['route' => 'contas',               'label' => 'Contas',                 'icon' => 'bi-bank',             'url' => $Url('contas'),                 'adminOnly' => false, 'perm' => ['contas', 'read']],
    ['route' => 'plano-contas',         'label' => 'Plano de Contas',        'icon' => 'bi-diagram-3',        'url' => $Url('plano-contas'),           'adminOnly' => false, 'perm' => ['plano_contas', 'read']],
    ['route' => 'lancamentos',          'label' => 'Lançamentos',            'icon' => 'bi-receipt',          'url' => $Url('lancamentos'),            'adminOnly' => false, 'perm' => ['lancamentos', 'read']],
    ['route' => 'contas-programadas',   'label' => 'Contas Programadas',     'icon' => 'bi-calendar-event',   'url' => $Url('contas-programadas'),     'adminOnly' => false, 'perm' => ['contas_programadas', 'read']],
    ['route' => 'transferencias',       'label' => 'Transferências',         'icon' => 'bi-arrow-left-right', 'url' => $Url('transferencias/novo'),    'adminOnly' => false, 'perm' => ['transferencias', 'read']],
    ['route' => 'clientes-fornecedores','label' => 'Clientes / Fornecedores','icon' => 'bi-people',          'url' => $Url('clientes-fornecedores'),  'adminOnly' => false, 'perm' => ['clientes_fornecedores', 'read']],
    ['route' => 'relatorios',           'label' => 'Relatórios',             'icon' => 'bi-bar-chart-line',   'url' => $Url('relatorios/fluxo-caixa'), 'adminOnly' => false, 'perm' => ['relatorios', 'read']],
    ['route' => 'sobre',                'label' => 'Sobre / Contato',        'icon' => 'bi-info-circle',      'url' => $Url('sobre'),                  'adminOnly' => false, 'perm' => null],
    ['route' => 'nfse',                 'label' => 'NFSe',                   'icon' => 'bi-receipt-cutoff',   'url' => $Url('nfse'),                   'adminOnly' => false, 'perm' => ['nfse', 'read']],
    ['route' => 'usuarios',             'label' => 'Usuários',               'icon' => 'bi-person-gear',      'url' => $Url('usuarios'),               'adminOnly' => true,  'perm' => null],
    ['route' => 'configuracoes',        'label' => 'Configurações',          'icon' => 'bi-gear',             'url' => $Url('configuracoes'),          'adminOnly' => true,  'perm' => null],
    ['route' => 'backups',              'label' => 'Backup',                 'icon' => 'bi-database-check',   'url' => $Url('backups'),                'adminOnly' => true,  'perm' => null],
];
?>
<aside class="sidebar" id="appSidebar">
    <div class="d-flex justify-content-between align-items-start mb-2">
        <div>
            <div class="logo">Leandro DEV</div>
            <div class="subtitle">MM Construtora</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-light d-lg-none" id="sidebarClose" aria-label="Fechar menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    <ul class="nav nav-pills flex-column mb-auto">
        <?php foreach ($menuItems as $item):
            $allowed = $item['adminOnly']
                ? $isAdmin
                : ($item['perm'] === null || Auth::hasPermission($item['perm'][0], $item['perm'][1]));
            if (!$allowed) {
                continue;
            }
            $active = $isActive($item['route']) ? ' active' : '';
        ?>
            <li class="nav-item">
                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" class="nav-link<?= $active ?> text-white">
                    <i class="bi <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="version mt-3">v1.0.0</div>
</aside>
