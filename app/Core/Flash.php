<?php
/**
 * @file Flash.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Mensagens flash (toast) via sessão.
 *
 * Tipos: success, error, warning, info.
 */
final class Flash
{
    private const SESSION_KEY = '_flash_messages';

    public static function success(string $message): void
    {
        self::push('success', $message);
    }

    public static function error(string $message): void
    {
        self::push('error', $message);
    }

    public static function warning(string $message): void
    {
        self::push('warning', $message);
    }

    public static function info(string $message): void
    {
        self::push('info', $message);
    }

    /**
     * Indica existem mensagens flash pendentes.
     *
     * @return bool
     */
    public static function has(): bool
    {
        $messages = Session::get(self::SESSION_KEY, []);
        return is_array($messages) && count($messages) > 0;
    }

    /**
     * Retorna todas as mensagens flash e as limpa da sessão.
     *
     * @return array<int,array{type:string,message:string}>
     */
    public static function get(): array
    {
        $messages = Session::get(self::SESSION_KEY, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        Session::forget(self::SESSION_KEY);
        return $messages;
    }

    /**
     * Empilha uma mensagem flash.
     *
     * @param string $type    Tipo (success|error|warning|info).
     * @param string $message Texto.
     */
    private static function push(string $type, string $message): void
    {
        $messages = Session::get(self::SESSION_KEY, []);
        if (!is_array($messages)) {
            $messages = [];
        }
        $messages[] = ['type' => $type, 'message' => $message];
        Session::set(self::SESSION_KEY, $messages);
    }
}
