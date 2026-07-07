<?php
/**
 * @file Logger.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Logger de erro e auditoria.
 *
 * - error/info escrevem em /storage/logs/error_YYYYMMDD.log
 * - audit insere na tabela logs_auditoria
 *
 * Formato de linha: [YYYY-MM-DD HH:MM:SS] LEVEL: message {json context}
 */
final class Logger
{
    /**
     * Loga uma mensagem de erro.
     *
     * @param string  $message Mensagem.
     * @param array<string,mixed> $context Contexto.
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    /**
     * Loga uma mensagem informativa.
     *
     * @param string  $message Mensagem.
     * @param array<string,mixed> $context Contexto.
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    /**
     * Registra auditoria de uma ação.
     *
     * @param string      $acao           Ação (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, LOGIN_FALHA, BACKUP, RESTORE, INSTALL).
     * @param string      $modulo         Módulo (contas, lancamentos, etc.).
     * @param int|null    $registroId     ID do registro afetado.
     * @param array<string,mixed>|null $dadosAnteriores Estado anterior.
     * @param array<string,mixed>|null $dadosNovos      Estado novo.
     */
    public static function audit(
        string $acao,
        string $modulo,
        ?int $registroId = null,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null
    ): void {
        $usuarioId = Auth::id();
        $acao = self::normalizeAcao($acao);

        try {
            $pdo = Database::getInstance();
            $sql = 'INSERT INTO logs_auditoria
                    (usuario_id, acao, modulo, registro_id, dados_anteriores, dados_novos, ip, user_agent, criado_em)
                    VALUES
                    (:usuario_id, :acao, :modulo, :registro_id, :dados_anteriores, :dados_novos, :ip, :user_agent, NOW())';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id'       => $usuarioId,
                ':acao'             => $acao,
                ':modulo'           => $modulo,
                ':registro_id'      => $registroId,
                ':dados_anteriores' => $dadosAnteriores !== null ? json_encode($dadosAnteriores, JSON_UNESCAPED_UNICODE) : null,
                ':dados_novos'      => $dadosNovos !== null ? json_encode($dadosNovos, JSON_UNESCAPED_UNICODE) : null,
                ':ip'               => Request::ip(),
                ':user_agent'       => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            // Fallback: grava no log de erros se não conseguir inserir auditoria.
            self::error('Falha ao registrar auditoria: ' . $e->getMessage(), [
                'acao'   => $acao,
                'modulo' => $modulo,
                'registro_id' => $registroId,
            ]);
        }
    }

    /**
     * Normaliza a ação para um dos valores ENUM permitidos em logs_auditoria:
     * CREATE | UPDATE | DELETE | LOGIN | LOGOUT | LOGIN_FALHA | BACKUP | RESTORE | INSTALL.
     *
     * @param string $acao Ação bruta.
     *
     * @return string
     */
    private static function normalizeAcao(string $acao): string
    {
        $allowed = ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'LOGIN_FALHA', 'BACKUP', 'RESTORE', 'INSTALL'];
        $upper = strtoupper(trim($acao));
        if (in_array($upper, $allowed, true)) {
            return $upper;
        }

        // Mapeamento heurístico para variações comuns.
        return match (true) {
            str_starts_with($upper, 'CREATE') || str_starts_with($upper, 'INSERT') || str_starts_with($upper, 'ADD')    => 'CREATE',
            str_starts_with($upper, 'UPDATE') || str_starts_with($upper, 'EDIT')   || str_starts_with($upper, 'ALTER')  => 'UPDATE',
            str_starts_with($upper, 'DELETE') || str_starts_with($upper, 'REMOVE') || str_starts_with($upper, 'CANCEL') => 'DELETE',
            str_starts_with($upper, 'LOGIN_FALHA') || str_starts_with($upper, 'FAIL')                                  => 'LOGIN_FALHA',
            str_starts_with($upper, 'LOGIN')                                                                              => 'LOGIN',
            str_starts_with($upper, 'LOGOUT') || str_starts_with($upper, 'SAIR')                                        => 'LOGOUT',
            str_starts_with($upper, 'BACKUP') || str_starts_with($upper, 'EXPORT')                                      => 'BACKUP',
            str_starts_with($upper, 'RESTORE') || str_starts_with($upper, 'IMPORT')                                     => 'RESTORE',
            str_starts_with($upper, 'INSTALL') || str_starts_with($upper, 'SETUP')                                      => 'INSTALL',
            default                                                                                                      => 'UPDATE',
        };
    }

    /**
     * Escreve uma linha no log do dia.
     *
     * @param string                $level   Nível (ERROR|INFO|WARNING|...).
     * @param string                $message Mensagem.
     * @param array<string,mixed>   $context Contexto.
     */
    private static function write(string $level, string $message, array $context = []): void
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $date = date('Y-m-d H:i:s');
        $contextJson = $context === [] ? '' : ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $line = sprintf("[%s] %s: %s%s\n", $date, $level, $message, $contextJson);

        $file = $dir . '/error_' . date('Ymd') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Caminho absoluto do diretório de logs.
     *
     * @return string
     */
    private static function logDir(): string
    {
        return dirname(__DIR__, 2) . '/storage/logs';
    }
}
