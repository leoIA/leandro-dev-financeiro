<?php
/**
 * @file App.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Bootstrap da aplicação.
 *
 * Responsabilidades:
 *  - Registrar o autoloader PSR-4 customizado (App\ -> /app/).
 *  - Carregar /config.php (define DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
 *    APP_NAME, APP_ENV, APP_TIMEZONE, APP_URL, SESSION_LIFETIME, FORCE_HTTPS, etc.).
 *  - Configurar timezone, encoding e exibição de erros.
 *  - Instalar error_handler e exception_handler que logam em /storage/logs.
 *  - Iniciar a sessão PHP de forma idempotente.
 */
final class App
{
    private static bool $booted = false;

    /**
     * Inicializa o bootstrap da aplicação. Idempotente.
     */
    public static function boot(): void
    {
        if (self::$booted === true) {
            return;
        }
        self::$booted = true;

        // 1) Autoloader PSR-4 custom (sem composer).
        self::registerAutoloader();

        // 2) Carregar config.php (deve definir as constantes globais).
        $configPath = dirname(__DIR__, 2) . '/config.php';
        if (is_file($configPath)) {
            require_once $configPath;
        }

        // 3) Configuração de ambiente.
        if (defined('APP_TIMEZONE')) {
            date_default_timezone_set(APP_TIMEZONE);
        } else {
            date_default_timezone_set('America/Sao_Paulo');
        }

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        $isProd = (defined('APP_ENV') && APP_ENV === 'production');
        error_reporting(E_ALL);
        ini_set('display_errors', $isProd ? '0' : '1');
        ini_set('display_startup_errors', $isProd ? '0' : '1');
        ini_set('log_errors', '1');

        // 4) Handlers de erro e exceção.
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);

        // 5) Garante diretórios de storage.
        $storageLogs = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($storageLogs)) {
            @mkdir($storageLogs, 0775, true);
        }

        // 6) Sessão (cookie seguro, httponly, samesite=Strict).
        Session::start();
    }

    /**
     * Autoloader PSR-4 para o prefixo App\.
     */
    private static function registerAutoloader(): void
    {
        spl_autoload_register(static function (string $class): void {
            $prefix = 'App\\';
            $baseDir = dirname(__DIR__) . '/'; // .../app/

            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }

    /**
     * Error handler — converte erros fatais/error em exceções/loga.
     *
     * @param int    $errno   Nível do erro.
     * @param string $errstr  Mensagem.
     * @param string $errfile Arquivo.
     * @param int    $errline Linha.
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $levels = [
            E_ERROR             => 'ERROR',
            E_WARNING           => 'WARNING',
            E_PARSE             => 'PARSE',
            E_NOTICE            => 'NOTICE',
            E_CORE_ERROR        => 'CORE_ERROR',
            E_CORE_WARNING      => 'CORE_WARNING',
            E_COMPILE_ERROR     => 'COMPILE_ERROR',
            E_COMPILE_WARNING   => 'COMPILE_WARNING',
            E_USER_ERROR        => 'USER_ERROR',
            E_USER_WARNING      => 'USER_WARNING',
            E_USER_NOTICE       => 'USER_NOTICE',
            E_STRICT            => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE_ERROR',
            E_DEPRECATED        => 'DEPRECATED',
            E_USER_DEPRECATED   => 'USER_DEPRECATED',
        ];
        $level = $levels[$errno] ?? 'UNKNOWN';

        $message = sprintf(
            '%s: %s in %s on line %d',
            $level,
            $errstr,
            $errfile,
            $errline
        );

        Logger::error($message, ['errno' => $errno, 'file' => $errfile, 'line' => $errline]);

        if (in_array($errno, [E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return true;
    }

    /**
     * Exception handler — loga e exibe erro 500 amigável.
     */
    public static function handleException(Throwable $exception): void
    {
        Logger::error(
            'Uncaught ' . get_class($exception) . ': ' . $exception->getMessage(),
            [
                'file'  => $exception->getFile(),
                'line'  => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]
        );

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (defined('APP_ENV') && APP_ENV !== 'production') {
            echo '<pre style="padding:1rem;background:#fff3f3;border:1px solid #e33;color:#900;">'
                . htmlspecialchars(
                    'Erro: ' . $exception->getMessage()
                    . "\n" . $exception->getFile() . ':' . $exception->getLine()
                    . "\n\n" . $exception->getTraceAsString(),
                    ENT_QUOTES,
                    'UTF-8'
                )
                . '</pre>';
        } else {
            echo '<h1>Erro interno do servidor</h1><p>Tente novamente em instantes.</p>';
        }
    }
}
