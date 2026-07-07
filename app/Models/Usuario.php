<?php
/**
 * @file Usuario.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de usuários — autenticação, tentativas de login, permissões.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Usuario extends BaseModel
{
    protected string $table = 'usuarios';
    protected string $modulo = 'usuarios';

    /**
     * Busca usuário por email.
     *
     * @param string $email Email.
     *
     * @return array<string,mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM usuarios WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Incrementa tentativas_login e bloqueia se exceder limite (segurança defensiva;
     * o controle fino é feito no AuthController).
     *
     * @param string $email Email.
     */
    public function registrarTentativaFalha(string $email): void
    {
        $sql = 'UPDATE usuarios SET tentativas_login = tentativas_login + 1 WHERE email = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$email]);
    }

    /**
     * Zera tentativas_login do usuário.
     *
     * @param int $id ID.
     */
    public function resetTentativas(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Alias (compatibilidade com AuthController).
     *
     * @param int $id ID.
     */
    public function resetarTentativas(int $id): void
    {
        $this->resetTentativas($id);
    }

    /**
     * Bloqueia o usuário por N minutos.
     *
     * @param int|string $id      ID (aceita string vinda direto do fetch PDO).
     * @param int        $minutos Minutos de bloqueio.
     */
    public function bloquear(int|string $id, int $minutos): void
    {
        $id = (int) $id;
        $sql = 'UPDATE usuarios SET bloqueado_ate = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$minutos, $id]);
    }

    /**
     * Atualiza ultimo_acesso = NOW().
     *
     * @param int $id ID.
     */
    public function updateUltimoAcesso(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Alias (compatibilidade com AuthController).
     *
     * @param int $id ID.
     */
    public function atualizarUltimoAcesso(int $id): void
    {
        $this->updateUltimoAcesso($id);
    }

    /**
     * Retorna permissões do usuário (modulo/acao).
     *
     * ADMIN retorna [] mas implicitamente tem todas. Esse método lista apenas
     * as permissões vinculadas explicitamente.
     *
     * @param int $usuarioId ID do usuário.
     *
     * @return list<array{id:int, modulo:string, acao:string, descricao:?string}>
     */
    public function permissoes(int $usuarioId): array
    {
        $sql = 'SELECT p.id, p.modulo, p.acao, p.descricao
                FROM usuario_permissoes up
                INNER JOIN permissoes p ON p.id = up.permissao_id
                WHERE up.usuario_id = ?
                ORDER BY p.modulo ASC, p.acao ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica permissão de um usuário específico (não necessariamente o logado).
     * ADMIN tem todas implicitamente.
     *
     * @param int    $usuarioId ID do usuário.
     * @param string $modulo    Módulo.
     * @param string $acao      Ação.
     *
     * @return bool
     */
    public function hasPermission(int $usuarioId, string $modulo, string $acao): bool
    {
        $stmt = $this->pdo->prepare('SELECT perfil FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }
        if (($row['perfil'] ?? '') === 'ADMIN') {
            return true;
        }

        $sql = 'SELECT COUNT(*) AS total
                FROM usuario_permissoes up
                INNER JOIN permissoes p ON p.id = up.permissao_id
                WHERE up.usuario_id = ? AND p.modulo = ? AND p.acao = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId, $modulo, $acao]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    /**
     * Vincula permissões a um usuário (delete + insert em transação).
     *
     * @param int         $usuarioId      ID do usuário.
     * @param list<int>   $permissaoIds   IDs de permissões.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function vincularPermissoes(int $usuarioId, array $permissaoIds): void
    {
        $permissaoIds = array_values(array_filter(array_map('intval', $permissaoIds), fn ($v) => $v > 0));
        sort($permissaoIds);

        try {
            $this->pdo->beginTransaction();

            $del = $this->pdo->prepare('DELETE FROM usuario_permissoes WHERE usuario_id = ?');
            $del->execute([$usuarioId]);

            if (!empty($permissaoIds)) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)'
                );
                foreach ($permissaoIds as $pid) {
                    $ins->execute([$usuarioId, $pid]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Falha ao vincular permissões: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Cria usuário com permissões vinculadas em transação.
     *
     * @param array<string,mixed> $data        Dados do usuário (sem senha_hash — caller deve hashear).
     * @param list<int>           $permissoes  IDs de permissões.
     *
     * @return int ID do usuário criado.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function createWithPermissoes(array $data, array $permissoes): int
    {
        try {
            $this->pdo->beginTransaction();

            $columns = array_keys($data);
            $colList = implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            $stmt = $this->pdo->prepare("INSERT INTO usuarios ({$colList}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
            $usuarioId = (int) $this->pdo->lastInsertId();

            $permissoes = array_values(array_filter(array_map('intval', $permissoes), fn ($v) => $v > 0));
            if (!empty($permissoes)) {
                $ins = $this->pdo->prepare(
                    'INSERT INTO usuario_permissoes (usuario_id, permissao_id) VALUES (?, ?)'
                );
                foreach ($permissoes as $pid) {
                    $ins->execute([$usuarioId, $pid]);
                }
            }

            $this->pdo->commit();

            $this->audit('CREATE', $usuarioId, null, $data);
            return $usuarioId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Falha ao criar usuário com permissões: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Override do create para registrar auditoria automática.
     *
     * @param array<string,mixed> $data Dados.
     *
     * @return int
     */
    public function create(array $data): int
    {
        $id = parent::create($data);
        $this->audit('CREATE', $id, null, $data);
        return $id;
    }

    /**
     * Override do update para registrar auditoria com estado anterior.
     *
     * @param int                   $id   ID.
     * @param array<string,mixed>   $data Dados.
     *
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $antes = $this->find($id);
        $ok = parent::update($id, $data);
        if ($ok) {
            $this->audit('UPDATE', $id, $antes, $data);
        }
        return $ok;
    }

    /**
     * Override do softDelete para auditoria.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function softDelete(int $id): bool
    {
        $antes = $this->find($id);
        $ok = parent::softDelete($id);
        if ($ok) {
            $this->audit('DELETE', $id, $antes, ['ativo' => 0]);
        }
        return $ok;
    }
}
