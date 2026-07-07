<?php
/**
 * @file BaseModel.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Abstract base para todos os models. Provê CRUD genérico, paginação,
 * contagem, soft-delete (ativo=0) e helper de auditoria.
 *
 * Subclasses DEVEM definir a propriedade protected string $table.
 * A propriedade protected string $modulo é opcional (default = $table).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use PDO;
use RuntimeException;

abstract class BaseModel
{
    protected PDO $pdo;
    protected string $table;
    protected string $primaryKey = 'id';
    protected string $modulo = '';

    /**
     * Construtor — instancia PDO singleton.
     */
    public function __construct()
    {
        $this->pdo = Database::getInstance();
        if ($this->modulo === '' && isset($this->table)) {
            $this->modulo = $this->table;
        }
    }

    /**
     * Busca um registro pela PK.
     *
     * @param int $id ID.
     *
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ? LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Busca múltiplos registros por condições de igualdade (AND).
     * Suporta conditions com valor escalar ou array (IN).
     *
     * @param array<string,mixed> $conditions Coluna => valor (escalar) ou Coluna => [v1,v2,...] (IN).
     * @param array<string,string> $order     ['coluna' => 'ASC|DESC'].
     *
     * @return list<array<string,mixed>>
     */
    public function findWhere(array $conditions, array $order = []): array
    {
        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $colEscaped = preg_replace('/[^a-zA-Z0-9_\.]/', '', (string) $col);
                if (is_array($val)) {
                    if (count($val) === 0) {
                        // IN () vazio — força resultado vazio.
                        $where[] = '1 = 0';
                        continue;
                    }
                    $placeholders = implode(', ', array_fill(0, count($val), '?'));
                    $where[] = "`{$colEscaped}` IN ({$placeholders})";
                    foreach ($val as $v) {
                        $params[] = $v;
                    }
                } else {
                    $where[] = "`{$colEscaped}` = ?";
                    $params[] = $val;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= $this->buildOrderClause($order);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os registros, opcionalmente ordenados.
     *
     * @param array<string,string> $order ['coluna' => 'ASC|DESC'].
     *
     * @return list<array<string,mixed>>
     */
    public function all(array $order = []): array
    {
        $sql = "SELECT * FROM `{$this->table}`" . $this->buildOrderClause($order);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginação simples.
     *
     * @param int  $page       Página atual (1-based).
     * @param int  $perPage    Itens por página.
     * @param array<string,mixed> $conditions Filtros de igualdade (AND).
     *
     * @return array{data: list<array<string,mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public function paginate(int $page, int $perPage, array $conditions = []): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $total = $this->count($conditions);
        $pages = $total === 0 ? 1 : (int) ceil($total / $perPage);
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $colEscaped = preg_replace('/[^a-zA-Z0-9_\.]/', '', (string) $col);
                if (is_array($val)) {
                    if (count($val) === 0) {
                        $where[] = '1 = 0';
                        continue;
                    }
                    $placeholders = implode(', ', array_fill(0, count($val), '?'));
                    $where[] = "`{$colEscaped}` IN ({$placeholders})";
                    foreach ($val as $v) {
                        $params[] = $v;
                    }
                } else {
                    $where[] = "`{$colEscaped}` = ?";
                    $params[] = $val;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        // LIMIT/OFFSET: $perPage e $offset são int validados internamente
        // (max(1, ...)) — concatenação segura.
        $sql .= ' LIMIT ' . $perPage . ' OFFSET ' . $offset;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $pages,
        ];
    }

    /**
     * Cria um registro a partir de um map coluna => valor.
     *
     * @param array<string,mixed> $data Dados.
     *
     * @return int ID inserido (lastInsertId).
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function create(array $data): int
    {
        if (empty($data)) {
            throw new RuntimeException('create() requer ao menos uma coluna.');
        }

        $columns = array_keys($data);
        $colList = implode(', ', array_map(fn ($c) => '`' . $c . '`', $columns));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO `{$this->table}` ({$colList}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Atualiza um registro pela PK.
     *
     * @param int  $id   ID.
     * @param array<string,mixed> $data Coluna => valor.
     *
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        $set = [];
        foreach (array_keys($data) as $col) {
            $set[] = "`{$col}` = ?";
        }
        $params = array_values($data);
        $params[] = $id;

        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $set) . " WHERE `{$this->primaryKey}` = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Hard-delete pela PK.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Soft-delete (ativo=0) — apenas se a tabela possuir coluna ativo.
     *
     * @param int $id ID.
     *
     * @return bool True se desativou, False se a tabela não tem ativo.
     */
    public function softDelete(int $id): bool
    {
        if (!$this->hasColumn('ativo')) {
            return false;
        }
        $sql = "UPDATE `{$this->table}` SET ativo = 0 WHERE `{$this->primaryKey}` = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$id]);
    }

    /**
     * Conta registros com filtros opcionais.
     *
     * @param array<string,mixed> $conditions Filtros de igualdade (AND).
     *
     * @return int
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `{$this->table}`";
        $params = [];

        if (!empty($conditions)) {
            $where = [];
            foreach ($conditions as $col => $val) {
                $colEscaped = preg_replace('/[^a-zA-Z0-9_\.]/', '', (string) $col);
                if (is_array($val)) {
                    if (count($val) === 0) {
                        $where[] = '1 = 0';
                        continue;
                    }
                    $placeholders = implode(', ', array_fill(0, count($val), '?'));
                    $where[] = "`{$colEscaped}` IN ({$placeholders})";
                    foreach ($val as $v) {
                        $params[] = $v;
                    }
                } else {
                    $where[] = "`{$colEscaped}` = ?";
                    $params[] = $val;
                }
            }
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Helper de auditoria — delega para App\Core\Logger::audit.
     *
     * @param string                  $acao        CREATE|UPDATE|DELETE|...
     * @param int|null                $registroId  ID afetado.
     * @param array<string,mixed>|null $antes       Estado anterior.
     * @param array<string,mixed>|null $depois      Estado novo.
     */
    protected function audit(string $acao, ?int $registroId, ?array $antes, ?array $depois): void
    {
        Logger::audit($acao, $this->modulo, $registroId, $antes, $depois);
    }

    /**
     * Constrói cláusula ORDER BY segura (colunas validadas contra lista).
     *
     * @param array<string,string> $order ['col' => 'ASC|DESC'].
     *
     * @return string
     */
    protected function buildOrderClause(array $order): string
    {
        if (empty($order)) {
            return '';
        }

        $parts = [];
        foreach ($order as $col => $dir) {
            $colEscaped = preg_replace('/[^a-zA-Z0-9_\.]/', '', (string) $col);
            $dirUpper = strtoupper((string) $dir);
            if (!in_array($dirUpper, ['ASC', 'DESC'], true)) {
                $dirUpper = 'ASC';
            }
            $parts[] = "`{$colEscaped}` {$dirUpper}";
        }
        return ' ORDER BY ' . implode(', ', $parts);
    }

    /**
     * Verifica se a tabela possui uma dada coluna (cache em propriedade estática).
     *
     * @param string $column Nome da coluna.
     *
     * @return bool
     */
    protected function hasColumn(string $column): bool
    {
        static $cache = [];
        $key = $this->table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        try {
            $stmt = $this->pdo->prepare('SHOW COLUMNS FROM `' . $this->table . '` WHERE Field = ?');
            $stmt->execute([$column]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
        } catch (\Throwable $e) {
            $exists = false;
        }
        $cache[$key] = $exists;
        return $exists;
    }
}
