<?php
/**
 * @file LogAuditoria.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de logs de auditoria (tabela logs_auditoria).
 *
 * Registrar: helper para inserir entradas com usuario_id/ip/user_agent automáticos.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Request;
use PDO;
use RuntimeException;

class LogAuditoria extends BaseModel
{
    protected string $table = 'logs_auditoria';
    protected string $primaryKey = 'id';
    protected string $modulo = 'logs_auditoria';

    /**
     * Registra uma entrada de auditoria.
     *
     * @param string                  $acao           CREATE|UPDATE|DELETE|LOGIN|LOGOUT|LOGIN_FALHA|BACKUP|RESTORE|INSTALL.
     * @param string                  $modulo         Módulo afetado.
     * @param int|null                $registroId     ID do registro.
     * @param array<string,mixed>|null $dadosAnteriores Estado anterior.
     * @param array<string,mixed>|null $dadosNovos      Estado novo.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function registrar(
        string $acao,
        string $modulo,
        ?int $registroId = null,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null
    ): void {
        $acaoUpper = strtoupper(trim($acao));
        $allowed = ['CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'LOGIN_FALHA', 'BACKUP', 'RESTORE', 'INSTALL'];
        if (!in_array($acaoUpper, $allowed, true)) {
            $acaoUpper = 'UPDATE';
        }

        try {
            $sql = 'INSERT INTO logs_auditoria
                        (usuario_id, acao, modulo, registro_id, dados_anteriores, dados_novos, ip, user_agent, criado_em)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, NOW())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                Auth::id(),
                $acaoUpper,
                $modulo,
                $registroId,
                $dadosAnteriores !== null ? json_encode($dadosAnteriores, JSON_UNESCAPED_UNICODE) : null,
                $dadosNovos !== null ? json_encode($dadosNovos, JSON_UNESCAPED_UNICODE) : null,
                Request::ip(),
                Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao registrar auditoria: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Logs de um usuário específico.
     *
     * @param int $usuarioId ID do usuário.
     * @param int $limit     Limite.
     *
     * @return list<array<string,mixed>>
     */
    public function byUsuario(int $usuarioId, int $limit = 50): array
    {
        $sql = 'SELECT la.*, u.nome AS usuario_nome
                FROM logs_auditoria la
                LEFT JOIN usuarios u ON u.id = la.usuario_id
                WHERE la.usuario_id = ?
                ORDER BY la.criado_em DESC, la.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $usuarioId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Logs de um módulo específico.
     *
     * @param string $modulo Módulo.
     * @param int    $limit  Limite.
     *
     * @return list<array<string,mixed>>
     */
    public function byModulo(string $modulo, int $limit = 50): array
    {
        $sql = 'SELECT la.*, u.nome AS usuario_nome
                FROM logs_auditoria la
                LEFT JOIN usuarios u ON u.id = la.usuario_id
                WHERE la.modulo = ?
                ORDER BY la.criado_em DESC, la.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $modulo);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Logs mais recentes (todos os módulos).
     *
     * @param int $limit Limite.
     *
     * @return list<array<string,mixed>>
     */
    public function recentes(int $limit = 100): array
    {
        $sql = 'SELECT la.*, u.nome AS usuario_nome
                FROM logs_auditoria la
                LEFT JOIN usuarios u ON u.id = la.usuario_id
                ORDER BY la.criado_em DESC, la.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
