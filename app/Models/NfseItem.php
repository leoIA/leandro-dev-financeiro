<?php
declare(strict_types=1);

/**
 * @file NfseItem.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de itens de NFSe (tabela nfse_item). Cada NFSe pode ter 1 ou N itens
 * discriminando o serviço prestado (descrição, quantidade, valor unitário, total).
 *
 * A inserção em lote (createMany) é transacional — insere todos ou nenhum.
 */

/** @file NfseItem.php | @package App\Models | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Models;

use PDO;
use RuntimeException;

class NfseItem extends BaseModel
{
    protected string $table = 'nfse_item';
    protected string $modulo = 'nfse_item';

    /**
     * Todos os itens de uma NFSe, ordenados por id (ordem de inserção).
     *
     * @param int $nfseId ID da NFSe.
     *
     * @return list<array<string,mixed>>
     */
    public function byNfse(int $nfseId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM nfse_item WHERE nfse_id = ? ORDER BY id ASC'
        );
        $stmt->execute([$nfseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Insere múltiplos itens de uma NFSe em lote (transacional).
     *
     * Cada item deve conter: descricao, quantidade, valor_unitario, valor_total.
     * Se valor_total estiver ausente ou zero, será calculado como
     * quantidade * valor_unitario.
     *
     * @param int                                  $nfseId ID da NFSe pai.
     * @param list<array<string,mixed>>            $itens  Lista de itens.
     *
     * @return void
     *
     * @throws RuntimeException Em caso de falha (rollback automático).
     */
    public function createMany(int $nfseId, array $itens): void
    {
        if (empty($itens)) {
            return;
        }

        $sql = 'INSERT INTO nfse_item
                    (nfse_id, descricao, quantidade, valor_unitario, valor_total)
                VALUES
                    (?, ?, ?, ?, ?)';

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $count = 0;
            foreach ($itens as $item) {
                $quantidade    = (float) ($item['quantidade'] ?? 1.0);
                $valorUnitario = (float) ($item['valor_unitario'] ?? 0.0);
                $valorTotal    = (float) ($item['valor_total'] ?? 0.0);
                if ($valorTotal === 0.0) {
                    $valorTotal = round($quantidade * $valorUnitario, 2);
                }
                $stmt->execute([
                    $nfseId,
                    (string) ($item['descricao'] ?? ''),
                    $quantidade,
                    $valorUnitario,
                    $valorTotal,
                ]);
                $count++;
            }
            $this->pdo->commit();
            $this->audit('CREATE', $nfseId, null, ['itens_inseridos' => $count]);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException(
                'Falha ao inserir itens da NFSe: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}
