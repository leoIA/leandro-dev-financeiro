<?php
/**
 * @file Database.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton de conexão PDO.
 *
 * Lê as constantes globais DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS,
 * definidas em /config.php pelo installer.
 */
final class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * Retorna a instância singleton do PDO.
     *
     * @return PDO Conexão ativa com charset utf8mb4.
     */
    public static function getInstance(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $name = defined('DB_NAME') ? DB_NAME : '';
        $user = defined('DB_USER') ? DB_USER : '';
        $pass = defined('DB_PASS') ? DB_PASS : '';
        $charset = 'utf8mb4';

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage(), [
                'host' => $host,
                'port' => $port,
                'name' => $name,
            ]);
            throw new PDOException('Falha ao conectar ao banco de dados: ' . $e->getMessage(), (int) $e->getCode(), $e);
        }

        return self::$instance;
    }

    /**
     * Testa a conectividade com credenciais informadas (usado pelo installer).
     *
     * @param string $host Host do MySQL.
     * @param int    $port Porta.
     * @param string $name Nome do banco.
     * @param string $user Usuário.
     * @param string $pass Senha.
     *
     * @return array{0:bool,1:string} Tupla [success, message].
     */
    public static function testConnection(string $host, int $port, string $name, string $user, string $pass): array
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);

        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

            $pdo->query('SELECT 1');

            return [true, 'Conexão estabelecida com sucesso.'];
        } catch (PDOException $e) {
            return [false, 'Falha na conexão: ' . $e->getMessage()];
        }
    }

    /**
     * Executa múltiplos statements SQL separados por ; respeitando strings.
     *
     * @param string $sqlContent Conteúdo SQL bruto.
     */
    public static function execSqlFile(string $sqlContent): void
    {
        $pdo = self::getInstance();

        $statements = self::splitSql($sqlContent);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            // Ignora comentários puros isolados.
            if (str_starts_with($statement, '--') || str_starts_with($statement, '#')) {
                continue;
            }
            $pdo->exec($statement);
        }
    }

    /**
     * Quebra um script SQL em statements individuais, respeitando aspas
     * simples, duplas, backticks, comentários de linha e de bloco.
     *
     * @param string $sql SQL bruto.
     *
     * @return list<string> Lista de statements.
     */
    private static function splitSql(string $sql): array
    {
        $statements = [];
        $current = '';
        $len = strlen($sql);
        $inSingle = false;
        $inDouble = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';
            $prev = $i > 0 ? $sql[$i - 1] : '';

            // Trata comentários.
            if (!$inSingle && !$inDouble && !$inBacktick) {
                if (!$inBlockComment && $char === '-' && $next === '-' && $prev !== '\\') {
                    $inLineComment = true;
                }
                if (!$inLineComment && $char === '#' && $prev !== '\\') {
                    $inLineComment = true;
                }
                if (!$inLineComment && $char === '/' && $next === '*') {
                    $inBlockComment = true;
                    $current .= $char . $next;
                    $i++;
                    continue;
                }
                if ($inBlockComment && $char === '*' && $next === '/') {
                    $inBlockComment = false;
                    $current .= $char . $next;
                    $i++;
                    continue;
                }
                if ($inLineComment && $char === "\n") {
                    $inLineComment = false;
                    $current .= $char;
                    continue;
                }
                if ($inLineComment || $inBlockComment) {
                    $current .= $char;
                    continue;
                }
            }

            // Aspas / backticks.
            if ($char === "'" && !$inDouble && !$inBacktick && $prev !== '\\') {
                $inSingle = !$inSingle;
            }
            if ($char === '"' && !$inSingle && !$inBacktick && $prev !== '\\') {
                $inDouble = !$inDouble;
            }
            if ($char === '`' && !$inSingle && !$inDouble) {
                $inBacktick = !$inBacktick;
            }

            // Fim de statement.
            if ($char === ';' && !$inSingle && !$inDouble && !$inBacktick) {
                $statements[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }

    /**
     * Fecha a conexão atual (útil em testes / reinstalação).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
