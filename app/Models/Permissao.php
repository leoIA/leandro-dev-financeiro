<?php
/**
 * @file Permissao.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model do catálogo de permissões (modulo/acao) e vínculo com usuários.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Permissao extends BaseModel
{
    protected string $table = 'permissoes';
    protected string $modulo = 'usuarios';

    /**
     * Catálogo completo de permissões.
     *
     * @return list<array<string,mixed>>
     */
    public function allCatalog(): array
    {
        $sql = 'SELECT * FROM permissoes ORDER BY modulo ASC, acao ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Permissões de um módulo específico.
     *
     * @param string $modulo Módulo.
     *
     * @return list<array<string,mixed>>
     */
    public function byModulo(string $modulo): array
    {
        $sql = 'SELECT * FROM permissoes WHERE modulo = ? ORDER BY acao ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$modulo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * IDs de permissões vinculadas a um usuário.
     *
     * @param int $usuarioId ID do usuário.
     *
     * @return list<int>
     */
    public function idsForUser(int $usuarioId): array
    {
        $sql = 'SELECT permissao_id FROM usuario_permissoes WHERE usuario_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$usuarioId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn ($r) => (int) $r['permissao_id'], $rows);
    }

    /**
     * Vincula permissões a um usuário (delete + insert em transação).
     *
     * @param int       $usuarioId     ID do usuário.
     * @param list<int> $permissaoIds  IDs de permissões.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function vincularAoUsuario(int $usuarioId, array $permissaoIds): void
    {
        $permissaoIds = array_values(array_filter(array_map('intval', $permissaoIds), fn ($v) => $v > 0));
        $permissaoIds = array_values(array_unique($permissaoIds));

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
            throw new RuntimeException('Falha ao vincular permissões ao usuário: ' . $e->getMessage(), 0, $e);
        }
    }
}
