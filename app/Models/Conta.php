<?php
/**
 * @file Conta.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de contas (bancária / caixa / ASAAS / carteira / outro).
 *
 * Saldo atual = saldo_inicial
 *               + SUM(RECEITA + TRANSFERENCIA) PAGO (créditos)
 *               - SUM(DESPESA + TRANSFERENCIA) PAGO (débitos).
 *
 * Observação: para transferências, criam-se DOIS lançamentos TRANSFERENCIA —
 * um na conta de origem (débito) e um na de destino (crédito). Logo, somar
 * todas as TRANSFERENCIA PAGO à coluna de crédito e subtrair todas as
 * TRANSFERENCIA PAGO da coluna de débito é equivalente a somar/diminuir os
 * pares corretamente por conta.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Conta extends BaseModel
{
    protected string $table = 'contas';
    protected string $modulo = 'contas';

    /**
     * Saldo atual de uma conta específica.
     *
     * Fórmula:
     *   saldo_inicial
     *   + SUM(valor WHERE tipo IN ('RECEITA','TRANSFERENCIA') AND status='PAGO')
     *   - SUM(valor WHERE tipo IN ('DESPESA','TRANSFERENCIA') AND status='PAGO')
     *
     * @param int $contaId ID da conta.
     *
     * @return float
     */
    public function saldoAtual(int $contaId): float
    {
        $sql = 'SELECT c.saldo_inicial AS saldo_inicial,
                       COALESCE(SUM(CASE WHEN l.tipo IN (?, ?) AND l.status = ? THEN l.valor ELSE 0 END), 0) AS creditos,
                       COALESCE(SUM(CASE WHEN l.tipo IN (?, ?) AND l.status = ? THEN l.valor ELSE 0 END), 0) AS debitos
                FROM contas c
                LEFT JOIN lancamentos l ON l.conta_id = c.id
                WHERE c.id = ?
                GROUP BY c.id, c.saldo_inicial';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'RECEITA',
            'TRANSFERENCIA',
            'PAGO',
            'DESPESA',
            'TRANSFERENCIA',
            'PAGO',
            $contaId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            // Conta não existe — tenta apenas saldo_inicial.
            $stmt2 = $this->pdo->prepare('SELECT saldo_inicial FROM contas WHERE id = ?');
            $stmt2->execute([$contaId]);
            $c = $stmt2->fetch(PDO::FETCH_ASSOC);
            return $c === false ? 0.0 : (float) $c['saldo_inicial'];
        }

        return (float) $row['saldo_inicial'] + (float) $row['creditos'] - (float) $row['debitos'];
    }

    /**
     * Soma dos saldos atuais de todas as contas ativas.
     *
     * @return float
     */
    public function saldoGeral(): float
    {
        $contas = $this->ativas();
        $total = 0.0;
        foreach ($contas as $conta) {
            $total += $this->saldoAtual((int) $conta['id']);
        }
        return $total;
    }

    /**
     * Contas ativas (ativo=1).
     *
     * @return list<array<string,mixed>>
     */
    public function ativas(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM contas WHERE ativo = 1 ORDER BY nome ASC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Extrato de lançamentos de uma conta em período.
     *
     * @param int         $contaId    ID da conta.
     * @param string|null $dataInicio YYYY-MM-DD ou null.
     * @param string|null $dataFim    YYYY-MM-DD ou null.
     *
     * @return list<array<string,mixed>>
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function extrato(int $contaId, ?string $dataInicio, ?string $dataFim): array
    {
        $sql = 'SELECT l.*,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome,
                       cf.nome_razao_social AS cf_nome
                FROM lancamentos l
                INNER JOIN plano_contas pc ON pc.id = l.plano_conta_id
                LEFT JOIN clientes_fornecedores cf ON cf.id = l.cliente_fornecedor_id
                WHERE l.conta_id = ?';

        $params = [$contaId];

        if ($dataInicio !== null && $dataInicio !== '') {
            $sql .= ' AND l.data_lancamento >= ?';
            $params[] = $dataInicio;
        }
        if ($dataFim !== null && $dataFim !== '') {
            $sql .= ' AND l.data_lancamento <= ?';
            $params[] = $dataFim;
        }

        $sql .= ' ORDER BY l.data_lancamento DESC, l.id DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao consultar extrato: ' . $e->getMessage(), 0, $e);
        }
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
