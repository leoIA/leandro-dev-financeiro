<?php
/**
 * @file ClienteFornecedor.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de clientes / fornecedores / ambos.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;

class ClienteFornecedor extends BaseModel
{
    protected string $table = 'clientes_fornecedores';
    protected string $modulo = 'clientes_fornecedores';

    /**
     * Filtra por tipo (CLIENTE|FORNECEDOR|AMBOS).
     *
     * @param string $tipo Tipo.
     *
     * @return list<array<string,mixed>>
     */
    public function byTipo(string $tipo): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM clientes_fornecedores
             WHERE ativo = 1 AND (tipo = ? OR tipo = ?)
             ORDER BY nome_razao_social ASC'
        );
        // Inclui AMBOS em qualquer filtro (cliente ou fornecedor).
        $stmt->execute([$tipo, 'AMBOS']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Registros ativos.
     *
     * @return list<array<string,mixed>>
     */
    public function ativos(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM clientes_fornecedores WHERE ativo = 1 ORDER BY nome_razao_social ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Autocomplete (LIMIT 10) por nome/CPF/CNPJ.
     *
     * @param string $term Termo de busca.
     *
     * @return list<array<string,mixed>>
     */
    public function search(string $term): array
    {
        $like = '%' . $term . '%';
        $stmt = $this->pdo->prepare(
            'SELECT id, tipo, tipo_pessoa, nome_razao_social, cpf_cnpj, email, telefone, celular
             FROM clientes_fornecedores
             WHERE ativo = 1
               AND (nome_razao_social LIKE ? OR cpf_cnpj LIKE ? OR email LIKE ?)
             ORDER BY nome_razao_social ASC
             LIMIT 10'
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se há lançamentos vinculados (impede hard-delete).
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function hasLancamentos(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) AS total FROM lancamentos WHERE cliente_fornecedor_id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($row['total'] ?? 0)) > 0;
    }

    /**
     * Override do create com auditoria.
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
}
