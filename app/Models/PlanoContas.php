<?php
/**
 * @file PlanoContas.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model do plano de contas (auto-relacionamento hierárquico via parent_id).
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class PlanoContas extends BaseModel
{
    protected string $table = 'plano_contas';
    protected string $modulo = 'plano_contas';

    /**
     * Caminho completo do nó (concatenado por " :: "): ex.
     * "RECEITAS OPERACIONAIS :: Venda de Produtos".
     *
     * @param int $id ID do nó.
     *
     * @return string
     */
    public function caminhoCompleto(int $id): string
    {
        $parts = [];
        $currentId = $id;
        $visited = [];
        $maxDepth = 10;

        while ($currentId > 0 && $maxDepth-- > 0) {
            if (in_array($currentId, $visited, true)) {
                break; // proteção contra ciclo.
            }
            $visited[] = $currentId;

            $stmt = $this->pdo->prepare('SELECT id, parent_id, nome FROM plano_contas WHERE id = ? LIMIT 1');
            $stmt->execute([$currentId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row === false) {
                break;
            }
            array_unshift($parts, (string) $row['nome']);
            $currentId = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
        }

        return implode(' :: ', $parts);
    }

    /**
     * Árvore completa aninhada (children recursivo).
     *
     * @return list<array<string,mixed>>
     */
    public function getTree(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plano_contas ORDER BY nivel ASC, ordem ASC, codigo ASC'
        );
        $stmt->execute();
        $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->buildTree($all, null);
    }

    /**
     * Filhos diretos de um nó.
     *
     * @param int $parentId ID do pai.
     *
     * @return list<array<string,mixed>>
     */
    public function filhos(int $parentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plano_contas WHERE parent_id = ? ORDER BY ordem ASC, codigo ASC'
        );
        $stmt->execute([$parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Nós raiz (parent_id IS NULL).
     *
     * @return list<array<string,mixed>>
     */
    public function raizes(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plano_contas WHERE parent_id IS NULL ORDER BY ordem ASC, codigo ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se o nó tem filhos.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function hasFilhos(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM plano_contas WHERE parent_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    /**
     * Verifica se o nó tem lançamentos vinculados.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function hasLancamentos(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total FROM lancamentos WHERE plano_conta_id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    /**
     * Plano de contas ativo.
     *
     * @return list<array<string,mixed>>
     */
    public function ativos(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plano_contas WHERE ativo = 1 ORDER BY nivel ASC, codigo ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Plano de contas por tipo (RECEITA|DESPESA|NEUTRO).
     *
     * @param string $tipo Tipo.
     *
     * @return list<array<string,mixed>>
     */
    public function byTipo(string $tipo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM plano_contas WHERE tipo = ? AND ativo = 1 ORDER BY nivel ASC, codigo ASC'
        );
        $stmt->execute([$tipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um nó calculando nível a partir do parent_id.
     *
     * @param array<string,mixed> $data Dados (deve conter parent_id ou null).
     *
     * @return int
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function create(array $data): int
    {
        $parentId = $data['parent_id'] ?? null;
        $parentId = ($parentId === '' || $parentId === null) ? null : (int) $parentId;

        $nivel = 1;
        if ($parentId !== null) {
            $stmt = $this->pdo->prepare('SELECT nivel FROM plano_contas WHERE id = ? LIMIT 1');
            $stmt->execute([$parentId]);
            $parent = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($parent === false) {
                throw new RuntimeException('Parent_id informado não existe.');
            }
            $nivel = (int) $parent['nivel'] + 1;
        }

        $data['parent_id'] = $parentId;
        $data['nivel'] = $nivel;

        $id = parent::create($data);
        $this->audit('CREATE', $id, null, $data);
        return $id;
    }

    /**
     * Override do update com auditoria.
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
     * Override do softDelete com auditoria.
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

    /**
     * Constrói árvore recursiva a partir de uma lista plana.
     *
     * @param list<array<string,mixed>> $all      Lista completa.
     * @param int|null                  $parentId Pai atual.
     *
     * @return list<array<string,mixed>>
     */
    private function buildTree(array $all, ?int $parentId): array
    {
        $nodes = [];
        foreach ($all as $row) {
            $rowParent = $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            if ($rowParent === $parentId) {
                $node = $row;
                $node['children'] = $this->buildTree($all, (int) $row['id']);
                $nodes[] = $node;
            }
        }
        return $nodes;
    }
}
