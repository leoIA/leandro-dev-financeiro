<?php
/**
 * @file Request.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper imutável sobre $_GET, $_POST, $_SERVER.
 */
final class Request
{
    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function isAjax(): bool
    {
        $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower((string) $header) === 'xmlhttprequest';
    }

    /**
     * Lê valor de $_GET.
     *
     * @param string $key     Chave.
     * @param mixed  $default Valor padrão.
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Lê valor de $_POST.
     *
     * @param string $key     Chave.
     * @param mixed  $default Valor padrão.
     *
     * @return mixed
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Retorna todo o payload da requisição (merge GET + POST, POST vence).
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /**
     * Retorna apenas as chaves informadas.
     *
     * @param list<string> $keys Chaves desejadas.
     *
     * @return array<string,mixed>
     */
    public static function only(array $keys): array
    {
        $source = self::all();
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $result[$key] = $source[$key];
            }
        }
        return $result;
    }

    /**
     * Retorna todas as chaves exceto as informadas.
     *
     * @param list<string> $keys Chaves a ignorar.
     *
     * @return array<string,mixed>
     */
    public static function except(array $keys): array
    {
        $source = self::all();
        foreach ($keys as $key) {
            unset($source[$key]);
        }
        return $source;
    }

    /**
     * IP do cliente (respeita proxy reverso via X-Forwarded-For se confiável).
     *
     * @return string
     */
    public static function ip(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $list = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $first = trim($list[0]);
            if ($first !== '') {
                return $first;
            }
        }
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /**
     * User-Agent do cliente.
     *
     * @return string
     */
    public static function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * Método HTTP (GET, POST, ...).
     *
     * @return string
     */
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /**
     * URI da requisição (path + query).
     *
     * @return string
     */
    public static function uri(): string
    {
        return (string) ($_SERVER['REQUEST_URI'] ?? '/');
    }
}
