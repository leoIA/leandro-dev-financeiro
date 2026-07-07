<?php
/**
 * @file Menu.php
 * @package App\Helpers
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Auth;

/**
 * Geração do menu lateral da aplicação.
 *
 * items() retorna a definição dos itens (com permissão).
 * render() monta o HTML Bootstrap 5 da sidebar.
 * isActive() marca o item ativo com base na rota atual.
 */
final class Menu
{
    /**
     * Definição dos itens do menu.
     *
     * Cada item: ['route'=>string, 'label'=>string, 'icon'=>string, 'permModule'=>?string, 'permAction'=>'read', 'adminOnly'=>bool]
     *
     * @return list<array{route:string,label:string,icon:string,permModule:?string,permAction:string,adminOnly:bool}>
     */
    public static function items(): array
    {
        return [
            ['route' => 'dashboard',           'label' => 'Dashboard',           'icon' => 'bi-speedometer2',    'permModule' => null,           'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'contas',              'label' => 'Contas',              'icon' => 'bi-bank',            'permModule' => 'contas',       'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'plano-contas',        'label' => 'Plano de Contas',     'icon' => 'bi-diagram-3',       'permModule' => 'plano_contas', 'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'lancamentos',         'label' => 'Lançamentos',         'icon' => 'bi-receipt',         'permModule' => 'lancamentos',  'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'contas-programadas',  'label' => 'Contas Programadas',  'icon' => 'bi-calendar-event',  'permModule' => 'contas_programadas', 'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'transferencias',      'label' => 'Transferências',      'icon' => 'bi-arrow-left-right','permModule' => 'transferencias', 'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'clientes-fornecedores','label' => 'Clientes / Fornecedores', 'icon' => 'bi-people',     'permModule' => 'clientes_fornecedores', 'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'relatorios',          'label' => 'Relatórios',          'icon' => 'bi-bar-chart-line',  'permModule' => 'relatorios',   'permAction' => 'read', 'adminOnly' => false],
            ['route' => 'usuarios',            'label' => 'Usuários',            'icon' => 'bi-person-gear',     'permModule' => 'usuarios',     'permAction' => 'read', 'adminOnly' => true],
            ['route' => 'configuracoes',       'label' => 'Configurações',       'icon' => 'bi-gear',            'permModule' => 'configuracoes','permAction' => 'read', 'adminOnly' => true],
            ['route' => 'backups',             'label' => 'Backup / Restore',    'icon' => 'bi-database-check',  'permModule' => 'backups',      'permAction' => 'read', 'adminOnly' => true],
        ];
    }

    /**
     * Renderiza o HTML do menu lateral (lista de links).
     *
     * @return string
     */
    public static function render(): string
    {
        $items = self::items();
        $user = Auth::user();
        $isAdmin = ($user['perfil'] ?? null) === 'ADMIN';

        $html = '<ul class="nav nav-pills flex-column mb-auto">' . "\n";

        foreach ($items as $item) {
            $allowed = $item['adminOnly']
                ? $isAdmin
                : ($item['permModule'] === null || Auth::hasPermission($item['permModule'], $item['permAction']));

            if (!$allowed) {
                continue;
            }

            $active = self::isActive($item['route']) ? ' active' : '';
            $url = self::urlFor($item['route']);

            $html .= sprintf(
                '  <li class="nav-item"><a href="%s" class="nav-link%s text-white"><i class="bi %s me-2"></i>%s</a></li>' . "\n",
                htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
                $active,
                htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8')
            );
        }

        $html .= "</ul>\n";
        return $html;
    }

    /**
     * Verifica se a rota informada é a rota atual.
     *
     * @param string $route Rota do item (ex.: "contas").
     *
     * @return bool
     */
    public static function isActive(string $route): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        $current = $segments[0] ?? '';
        if ($current === 'index.php') {
            $current = '';
        }

        // Normaliza hífens para underline só para comparação.
        $normalizedCurrent = str_replace('-', '_', $current);
        $normalizedRoute = str_replace('-', '_', $route);

        return $normalizedCurrent === $normalizedRoute;
    }

    /**
     * Monta a URL absoluta do item (suporta subpasta via APP_URL).
     *
     * @param string $route Rota do item.
     *
     * @return string
     */
    private static function urlFor(string $route): string
    {
        $base = '';
        if (defined('APP_URL')) {
            $parsed = parse_url((string) APP_URL);
            if (is_array($parsed) && isset($parsed['path'])) {
                $base = rtrim($parsed['path'], '/');
            }
        }
        return $base . '/' . $route;
    }
}
