<?php
/**
 * @file Config.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper para a tabela `configuracoes` (key-value global).
 *
 * Leitura com cache em array estático. Escrita invalida cache.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $cache = [];

    private static bool $loadedAll = false;

    /**
     * Lê uma configuração pela chave.
     *
     * @param string $key     Chave.
     * @param mixed  $default Valor padrão caso não exista.
     *
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadAll();

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        return $default;
    }

    /**
     * Define/atualiza uma configuração.
     *
     * @param string $key   Chave.
     * @param mixed  $value Valor.
     */
    public static function set(string $key, mixed $value): void
    {
        $pdo = Database::getInstance();

        $valor = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
        $tipo = self::detectType($value);

        $sql = 'INSERT INTO configuracoes (chave, valor, tipo)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), atualizado_em = NOW()';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$key, $valor, $tipo]);

        self::$cache[$key] = $value;
    }

    /**
     * Retorna todas as configurações como array key => valor_tipado.
     *
     * @return array<string,mixed>
     */
    public static function all(): array
    {
        self::loadAll();
        return self::$cache;
    }

    /**
     * Limpa o cache estático (útil após alterações externas).
     */
    public static function flushCache(): void
    {
        self::$cache = [];
        self::$loadedAll = false;
    }

    /**
     * Carrega todas as configurações do DB (uma vez).
     */
    private static function loadAll(): void
    {
        if (self::$loadedAll === true) {
            return;
        }
        self::$loadedAll = true;

        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query('SELECT chave, valor, tipo FROM configuracoes');
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // Banco ainda não configurado / tabela inexistente — falha silenciosa.
            return;
        }

        foreach ($rows as $row) {
            self::$cache[$row['chave']] = self::castValue((string) $row['valor'], $row['tipo']);
        }
    }

    /**
     * Detecta o tipo ENUM para gravação.
     *
     * @param mixed $value Valor.
     *
     * @return string
     */
    private static function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    /**
     * Converte o valor bruto para o tipo declarado.
     *
     * @param string $valor Valor bruto.
     * @param string $tipo  Tipo ENUM.
     *
     * @return mixed
     */
    private static function castValue(string $valor, string $tipo): mixed
    {
        return match ($tipo) {
            'int'      => (int) $valor,
            'bool'     => in_array(strtolower($valor), ['1', 'true', 'on', 'yes', 'sim'], true),
            'json'     => json_decode($valor, true) ?? [],
            'date'     => $valor,
            'datetime' => $valor,
            default    => $valor,
        };
    }
}
