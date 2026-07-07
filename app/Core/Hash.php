<?php
/**
 * @file Hash.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Wrappers para password_hash / password_verify com bcrypt custo 12.
 */
final class Hash
{
    private const COST = 12;

    /**
     * Gera o hash de uma senha (bcrypt custo 12).
     *
     * @param string $password Senha em texto plano.
     *
     * @return string
     */
    public static function make(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }

    /**
     * Verifica a senha contra o hash.
     *
     * @param string $password Senha em texto plano.
     * @param string $hash     Hash armazenado.
     *
     * @return bool
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Indica se o hash precisa ser regenerado (ex.: custo atualizado).
     *
     * @param string $hash Hash armazenado.
     *
     * @return bool
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => self::COST]);
    }
}
