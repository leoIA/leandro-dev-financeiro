<?php
/**
 * @file Csrf.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF por sessão (hash sha256 de random_bytes(32)).
 *
 * Uso:
 *   - Em forms: <?= Csrf::field() ?>
 *   - Em controllers POST: if (!Csrf::verify(Request::post('_csrf', ''))) { ... }
 *   - Em AJAX: header X-CSRF-Token com Csrf::token().
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Retorna o token CSRF atual, criando se necessário.
     *
     * @return string
     */
    public static function token(): string
    {
        Session::start();

        $token = Session::get(self::SESSION_KEY);
        if (!is_string($token) || $token === '') {
            $token = hash('sha256', random_bytes(32));
            Session::set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    /**
     * Verifica o token CSRF informado contra o da sessão.
     *
     * @param string|null $token Token recebido.
     *
     * @return bool
     */
    public static function verify(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        $current = Session::get(self::SESSION_KEY);
        if (!is_string($current) || $current === '') {
            return false;
        }

        return hash_equals($current, $token);
    }

    /**
     * Retorna o HTML do campo hidden para incluir em forms.
     *
     * @return string
     */
    public static function field(): string
    {
        $token = self::token();
        return sprintf(
            '<input type="hidden" name="_csrf" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }
}
