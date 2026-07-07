<?php
/**
 * @file Transferencia.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de transferências entre contas.
 *
 * createComLancamentos(): TRANSAÇÃO ATÔMICA.
 *   1. INSERT transferencias
 *   2. Garante planos NEUTRO "Transferências Saída" (TS) e "Transferências Entrada" (TE).
 *   3. INSERT lancamento TRANSFERENCIA na conta de origem (débito) — status PAGO.
 *   4. INSERT lancamento TRANSFERENCIA na conta de destino (crédito) — status PAGO.
 *   5. Commit. Em exceção: rollback + rethrow.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use PDO;
use RuntimeException;

class Transferencia extends BaseModel
{
    protected string $table = 'transferencias';
    protected string $modulo = 'transferencias';

    /**
     * IDs de planos de transferência (cache em propriedade).
     *
     * @var array<string,int>
     */
    private array $planoCache = [];

    /**
     * Cria transferência e os dois lançamentos associados em transação atômica.
     *
     * @param array<string,mixed> $data Deve conter: conta_origem_id, conta_destino_id,
     *                                  valor, data_transferencia, observacao (opcional).
     *
     * @return int ID da transferência criada.
     *
     * @throws RuntimeException Em caso de falha (rollback automático).
     */
    public function createComLancamentos(array $data): int
    {
        $required = ['conta_origem_id', 'conta_destino_id', 'valor', 'data_transferencia'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                throw new RuntimeException("Campo obrigatório ausente: {$field}");
            }
        }

        $origemId = (int) $data['conta_origem_id'];
        $destinoId = (int) $data['conta_destino_id'];
        $valor = (float) $data['valor'];
        $dataTransferencia = (string) $data['data_transferencia'];
        $observacao = $data['observacao'] ?? null;
        $usuarioId = Auth::id();

        if ($origemId === $destinoId) {
            throw new RuntimeException('Conta de origem e destino devem ser diferentes.');
        }
        if ($valor <= 0) {
            throw new RuntimeException('Valor da transferência deve ser maior que zero.');
        }

        // Busca contas.
        $contaOrigem = $this->findConta($origemId);
        if ($contaOrigem === null) {
            throw new RuntimeException('Conta de origem não encontrada.');
        }
        $contaDestino = $this->findConta($destinoId);
        if ($contaDestino === null) {
            throw new RuntimeException('Conta de destino não encontrada.');
        }

        // Garante planos de transferência.
        $planoSaidaId = $this->ensurePlanoTransferencia('TS', 'Transferências Saída');
        $planoEntradaId = $this->ensurePlanoTransferencia('TE', 'Transferências Entrada');

        try {
            $this->pdo->beginTransaction();

            // 1. INSERT transferencias.
            $stmtTr = $this->pdo->prepare(
                'INSERT INTO transferencias
                    (conta_origem_id, conta_destino_id, valor, data_transferencia, observacao, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmtTr->execute([
                $origemId,
                $destinoId,
                $valor,
                $dataTransferencia,
                $observacao,
                $usuarioId,
            ]);
            $transferId = (int) $this->pdo->lastInsertId();

            // 2. INSERT lançamento de saída (débito na conta origem).
            $stmtLancOut = $this->pdo->prepare(
                'INSERT INTO lancamentos
                    (conta_id, plano_conta_id, transferencia_id, tipo, valor,
                     data_lancamento, data_vencimento, data_pagamento, descricao,
                     status, forma_pagamento, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtLancOut->execute([
                $origemId,
                $planoSaidaId,
                $transferId,
                'TRANSFERENCIA',
                $valor,
                $dataTransferencia,
                $dataTransferencia,
                $dataTransferencia,
                'Transferência para: ' . $contaDestino['nome'],
                'PAGO',
                'TRANSFERENCIA',
                $usuarioId,
            ]);

            // 3. INSERT lançamento de entrada (crédito na conta destino).
            $stmtLancIn = $this->pdo->prepare(
                'INSERT INTO lancamentos
                    (conta_id, plano_conta_id, transferencia_id, tipo, valor,
                     data_lancamento, data_vencimento, data_pagamento, descricao,
                     status, forma_pagamento, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtLancIn->execute([
                $destinoId,
                $planoEntradaId,
                $transferId,
                'TRANSFERENCIA',
                $valor,
                $dataTransferencia,
                $dataTransferencia,
                $dataTransferencia,
                'Transferência de: ' . $contaOrigem['nome'],
                'PAGO',
                'TRANSFERENCIA',
                $usuarioId,
            ]);

            $this->pdo->commit();

            $this->audit('CREATE', $transferId, null, $data);
            return $transferId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Falha ao registrar transferência: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Carrega uma transferência com joins de contas origem/destino.
     *
     * @param int $id ID.
     *
     * @return array<string,mixed>|null
     */
    public function withRelations(int $id): ?array
    {
        $sql = 'SELECT t.*,
                       co.nome AS conta_origem_nome, co.tipo AS conta_origem_tipo,
                       cd.nome AS conta_destino_nome, cd.tipo AS conta_destino_tipo,
                       u.nome AS criado_por_nome
                FROM transferencias t
                INNER JOIN contas co ON co.id = t.conta_origem_id
                INNER JOIN contas cd ON cd.id = t.conta_destino_id
                LEFT JOIN usuarios u ON u.id = t.criado_por
                WHERE t.id = ? LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Lista transferências com joins, ordenadas pela data mais recente.
     *
     * @param int $limit Limite.
     *
     * @return list<array<string,mixed>>
     */
    public function recentes(int $limit = 50): array
    {
        $sql = 'SELECT t.*,
                       co.nome AS conta_origem_nome,
                       cd.nome AS conta_destino_nome,
                       u.nome AS criado_por_nome
                FROM transferencias t
                INNER JOIN contas co ON co.id = t.conta_origem_id
                INNER JOIN contas cd ON cd.id = t.conta_destino_id
                LEFT JOIN usuarios u ON u.id = t.criado_por
                ORDER BY t.data_transferencia DESC, t.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Busca uma conta por ID.
     *
     * @param int $id ID.
     *
     * @return array<string,mixed>|null
     */
    private function findConta(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, tipo FROM contas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Garante a existência do plano "Transferências" (root), "Transferências Saída"
     * e "Transferências Entrada". Retorna o ID do plano solicitado (TS ou TE).
     *
     * @param string $codigo 'TS' ou 'TE'.
     * @param string $nome   Nome do plano.
     *
     * @return int
     *
     * @throws RuntimeException Se não conseguir criar/obter.
     */
    private function ensurePlanoTransferencia(string $codigo, string $nome): int
    {
        if (isset($this->planoCache[$codigo])) {
            return $this->planoCache[$codigo];
        }

        // Tenta buscar plano existente pelo código.
        $stmt = $this->pdo->prepare('SELECT id FROM plano_contas WHERE codigo = ? LIMIT 1');
        $stmt->execute([$codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row !== false) {
            $this->planoCache[$codigo] = (int) $row['id'];
            return $this->planoCache[$codigo];
        }

        // Garante o nó raiz "Transferências" (código 'T').
        $stmtRoot = $this->pdo->prepare('SELECT id FROM plano_contas WHERE codigo = ? LIMIT 1');
        $stmtRoot->execute(['T']);
        $rootRow = $stmtRoot->fetch(PDO::FETCH_ASSOC);

        if ($rootRow !== false) {
            $rootId = (int) $rootRow['id'];
        } else {
            $insRoot = $this->pdo->prepare(
                'INSERT INTO plano_contas (parent_id, codigo, nome, tipo, nivel, ordem, ativo)
                 VALUES (NULL, ?, ?, ?, ?, ?, 1)'
            );
            $insRoot->execute(['T', 'Transferências', 'NEUTRO', 1, 0]);
            $rootId = (int) $this->pdo->lastInsertId();
        }

        // Cria o nó filho.
        $insChild = $this->pdo->prepare(
            'INSERT INTO plano_contas (parent_id, codigo, nome, tipo, nivel, ordem, ativo)
             VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $insChild->execute([$rootId, $codigo, $nome, 'NEUTRO', 2, 0]);
        $this->planoCache[$codigo] = (int) $this->pdo->lastInsertId();

        return $this->planoCache[$codigo];
    }
}
