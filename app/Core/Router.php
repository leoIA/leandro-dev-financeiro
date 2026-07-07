<?php
/**
 * @file    Router.php
 * @package App\Core
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Router minimalista baseado em tabela estática.
 *
 * Uso (em index.php):
 *   Router::add('contas', ContaController::class);             // default method: index
 *   Router::add('perfil', UsuarioController::class, 'perfil'); // default method explícito
 *   Router::dispatch($uri);
 *
 * Convenções de URL suportadas (alinhadas com as views T06):
 *   /contas                              -> ContaController::index()
 *   /contas/novo                         -> ContaController::novo()
 *   /contas/5/editar                     -> ContaController::editar('5')
 *   /contas/5/desativar                  -> ContaController::desativar('5')
 *   /contas/gerar-pendentes              -> ContaController::gerarPendentes()  (kebab→camel)
 *   /relatorios/fluxo-caixa              -> RelatorioController::fluxoCaixa()  (kebab→camel)
 *   /backups/backup_20260101.sql/download -> BackupController::download('backup_20260101.sql')
 *   /perfil                              -> UsuarioController::perfil()       (default method)
 *
 * Regra: o 2º segmento que corresponda a um método do controller é usado como
 * método; caso contrário, tenta o 3º segmento (padrão /route/{param}/{action}).
 * Kebab-case é convertido para camelCase na resolução de método.
 */
final class Router
{
    /** @var array<string,array{controller:class-string,method:string}> */
    private static array $routes = [];

    /**
     * Registra uma rota.
     *
     * @param string $route            Rota sem prefixo de domínio (ex.: "contas" ou "contas-programadas").
     * @param string $controllerClass  FQN do controller.
     * @param string $method           Método padrão do controller (default "index").
     */
    public static function add(string $route, string $controllerClass, string $method = 'index'): void
    {
        self::$routes[trim($route, '/')] = ['controller' => $controllerClass, 'method' => $method];
    }

    /**
     * Despacha a URI informada para o controller/method registrado.
     *
     * @param string $uri URI (apenas path).
     */
    public static function dispatch(string $uri): void
    {
        $path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);

        $routeKey = $segments[0] ?? '';

        // Rota raiz: encaminha para dashboard se autenticado, senão login.
        if ($routeKey === '' || $routeKey === 'index.php') {
            if (Auth::check()) {
                Response::redirect('/dashboard');
            } else {
                Response::redirect('/login.php');
            }
            return;
        }

        if (!isset(self::$routes[$routeKey])) {
            Response::abort(404, 'Rota não encontrada: /' . $routeKey);
            return;
        }

        $controllerClass = self::$routes[$routeKey]['controller'];
        $defaultMethod = self::$routes[$routeKey]['method'];
        $controller = new $controllerClass();

        $seg1 = $segments[1] ?? null;
        $seg2 = $segments[2] ?? null;

        // Sem 2º segmento: chama método default.
        if ($seg1 === null || $seg1 === '') {
            self::invoke($controller, $defaultMethod, null, $controllerClass);
            return;
        }

        // Tenta seg1 como nome de método (kebab-case -> camelCase).
        $seg1Method = self::kebabToCamel($seg1);
        if (method_exists($controller, $seg1Method)) {
            self::invoke($controller, $seg1Method, $seg2, $controllerClass);
            return;
        }

        // seg1 não é método. Tenta padrão /route/{param}/{action}.
        if ($seg2 !== null && $seg2 !== '') {
            $seg2Method = self::kebabToCamel($seg2);
            if (method_exists($controller, $seg2Method)) {
                self::invoke($controller, $seg2Method, $seg1, $controllerClass);
                return;
            }
        }

        // Fallback: método default com seg1 como parâmetro (legacy behavior).
        self::invoke($controller, $defaultMethod, $seg1, $controllerClass);
    }

    /**
     * Invoca o método do controller com 0 ou 1 parâmetro posicional.
     *
     * @param object  $controller       Instância do controller.
     * @param string  $method           Nome do método.
     * @param string|null $param        Parâmetro posicional (ou null).
     * @param string  $controllerClass  FQN (para mensagem de erro).
     */
    private static function invoke(object $controller, string $method, ?string $param, string $controllerClass): void
    {
        if (!method_exists($controller, $method)) {
            Response::abort(404, 'Método não encontrado: ' . $controllerClass . '::' . $method);
            return;
        }

        if ($param !== null && $param !== '') {
            $controller->$method($param);
        } else {
            $controller->$method();
        }
    }

    /**
     * Converte kebab-case para camelCase (preserva palavras únicas).
     * Ex.: "fluxo-caixa" -> "fluxoCaixa"; "novo" -> "novo".
     *
     * @param string $s Slug em kebab-case.
     *
     * @return string Método em camelCase.
     */
    private static function kebabToCamel(string $s): string
    {
        if (strpos($s, '-') === false) {
            return $s;
        }
        $parts = explode('-', $s);
        $first = array_shift($parts);
        return $first . implode('', array_map('ucfirst', $parts));
    }

    /**
     * Limpa as rotas registradas (útil em testes).
     */
    public static function reset(): void
    {
        self::$routes = [];
    }

    /**
     * Retorna todas as rotas registradas.
     *
     * @return array<string,array{controller:class-string,method:string}>
     */
    public static function routes(): array
    {
        return self::$routes;
    }
}
