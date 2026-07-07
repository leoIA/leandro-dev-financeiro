<?php
/**
 * @file Session.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper idempotente para a sessão PHP.
 *
 * - Cookie httponly, samesite=Strict, secure se HTTPS.
 * - Session::start() pode ser chamado múltiplas vezes com segurança.
 * - Helper checkTimeout() respeita a constante SESSION_LIFETIME (segundos).
 */
final class Session
{
    private static bool $started = false;

    /**
     * Inicia a sessão se ainda não estiver ativa. Idempotente.
     */
    public static function start(): void
    {
        if (self::$started === true) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $isHttps = self::isHttps();

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $isHttps,
            'samesite' => 'Strict',
        ]);

        session_name('LEANDRODEVSESSID');

        if (!headers_sent()) {
            session_start();
        }

        self::$started = true;
    }

    /**
     * Retorna valor da sessão.
     *
     * @param string $key     Chave.
     * @param mixed  $default Valor padrão.
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Define valor na sessão.
     *
     * @param string $key   Chave.
     * @param mixed  $value Valor.
     */
    public static function set(string $key, mixed $value): void
    {
        self::ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * Verifica se a chave existe.
     *
     * @param string $key Chave.
     *
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::ensureStarted();
        return array_key_exists($key, $_SESSION);
    }

    /**
     * Remove uma chave da sessão.
     *
     * @param string $key Chave.
     */
    public static function forget(string $key): void
    {
        self::ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * Destroi a sessão e limpa o cookie.
     */
    public static function destroy(): void
    {
        self::ensureStarted();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'] ?? '',
                $params['secure'] ?? false,
                $params['httponly'] ?? false
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    /**
     * Regenera o ID de sessão (usar após login bem-sucedido).
     */
    public static function regenerateId(): void
    {
        self::ensureStarted();
        session_regenerate_id(true);
    }

    /**
     * Verifica timeout de inatividade e destrói sessão se excedido.
     */
    public static function checkTimeout(): void
    {
        self::ensureStarted();

        $lifetime = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME : 1800; // 30 min default.
        $lastActivity = self::get('last_activity');

        if ($lastActivity === null) {
            self::set('last_activity', time());
            return;
        }

        if ((time() - (int) $lastActivity) > $lifetime) {
            self::destroy();
            return;
        }

        self::set('last_activity', time());
    }

    /**
     * Verifica se a requisição é HTTPS.
     *
     * @return bool
     */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }
        if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        return false;
    }

    /**
     * Garante que a sessão esteja iniciada.
     */
    private static function ensureStarted(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
        self::$started = true;
    }
}
