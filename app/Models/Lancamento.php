<?php
/**
 * @file Lancamento.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de lançamentos financeiros (receita / despesa / transferência).
 *
 * Regras:
 * - Lançamento PAGO não pode ser hard-deletado; apenas CANCELADO com motivo.
 * - Status: PENDENTE | PAGO | CANCELADO.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Lancamento extends BaseModel
{
    protected string $table = 'lancamentos';
    protected string $modulo = 'lancamentos';

    /**
     * Últimos lançamentos com joins (conta, plano, cf).
     *
     * @param int $limit Quantidade.
     *
     * @return list<array<string,mixed>>
     */
    public function ultimos(int $limit = 10): array
    {
        $sql = 'SELECT l.*,
                       c.nome AS conta_nome,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome,
                       cf.nome_razao_social AS cf_nome,
                       u.nome AS criado_por_nome
                FROM lancamentos l
                INNER JOIN contas c ON c.id = l.conta_id
                INNER JOIN plano_contas pc ON pc.id = l.plano_conta_id
                LEFT JOIN clientes_fornecedores cf ON cf.id = l.cliente_fornecedor_id
                LEFT JOIN usuarios u ON u.id = l.criado_por
                ORDER BY l.data_lancamento DESC, l.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lançamentos por período com filtros opcionais.
     *
     * @param string                $inicio  YYYY-MM-DD.
     * @param string                $fim     YYYY-MM-DD.
     * @param array<string,mixed>   $filtros Chaves opcionais: conta_id, plano_conta_id,
     *                                       cliente_fornecedor_id, tipo, status, forma_pagamento.
     *
     * @return list<array<string,mixed>>
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function byPeriodo(string $inicio, string $fim, array $filtros = []): array
    {
        $sql = 'SELECT l.*,
                       c.nome AS conta_nome,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome,
                       cf.nome_razao_social AS cf_nome,
                       u.nome AS criado_por_nome
                FROM lancamentos l
                INNER JOIN contas c ON c.id = l.conta_id
                INNER JOIN plano_contas pc ON pc.id = l.plano_conta_id
                LEFT JOIN clientes_fornecedores cf ON cf.id = l.cliente_fornecedor_id
                LEFT JOIN usuarios u ON u.id = l.criado_por
                WHERE l.data_lancamento BETWEEN ? AND ?';

        $params = [$inicio, $fim];

        $map = [
            'conta_id'                => 'l.conta_id',
            'plano_conta_id'          => 'l.plano_conta_id',
            'cliente_fornecedor_id'   => 'l.cliente_fornecedor_id',
            'tipo'                    => 'l.tipo',
            'status'                  => 'l.status',
            'forma_pagamento'         => 'l.forma_pagamento',
        ];

        foreach ($map as $key => $col) {
            if (isset($filtros[$key]) && $filtros[$key] !== '' && $filtros[$key] !== null) {
                $sql .= " AND {$col} = ?";
                $params[] = $filtros[$key];
            }
        }

        $sql .= ' ORDER BY l.data_lancamento DESC, l.id DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao consultar lançamentos por período: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Lançamentos de uma conta em período.
     *
     * @param int         $contaId ID da conta.
     * @param string|null $inicio  YYYY-MM-DD ou null.
     * @param string|null $fim     YYYY-MM-DD ou null.
     *
     * @return list<array<string,mixed>>
     */
    public function byConta(int $contaId, ?string $inicio, ?string $fim): array
    {
        $sql = 'SELECT l.*,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome,
                       cf.nome_razao_social AS cf_nome
                FROM lancamentos l
                INNER JOIN plano_contas pc ON pc.id = l.plano_conta_id
                LEFT JOIN clientes_fornecedores cf ON cf.id = l.cliente_fornecedor_id
                WHERE l.conta_id = ?';

        $params = [$contaId];

        if ($inicio !== null && $inicio !== '') {
            $sql .= ' AND l.data_lancamento >= ?';
            $params[] = $inicio;
        }
        if ($fim !== null && $fim !== '') {
            $sql .= ' AND l.data_lancamento <= ?';
            $params[] = $fim;
        }

        $sql .= ' ORDER BY l.data_lancamento DESC, l.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca lançamento como PAGO com data_pagamento = hoje.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function marcarComoPago(int $id): bool
    {
        $antes = $this->find($id);
        $sql = "UPDATE lancamentos
                SET status = 'PAGO', data_pagamento = CURDATE()
                WHERE id = ? AND status = 'PENDENTE'";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([$id]);
        if ($ok && $stmt->rowCount() > 0) {
            $this->audit('UPDATE', $id, $antes, ['status' => 'PAGO', 'data_pagamento' => date('Y-m-d')]);
        }
        return $ok;
    }

    /**
     * Estorna: volta de PAGO para PENDENTE e zera data_pagamento.
     *
     * @param int $id ID.
     *
     * @return bool
     */
    public function estornar(int $id): bool
    {
        $antes = $this->find($id);
        $sql = "UPDATE lancamentos
                SET status = 'PENDENTE', data_pagamento = NULL
                WHERE id = ? AND status = 'PAGO'";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([$id]);
        if ($ok && $stmt->rowCount() > 0) {
            $this->audit('UPDATE', $id, $antes, ['status' => 'PENDENTE', 'data_pagamento' => null]);
        }
        return $ok;
    }

    /**
     * Cancela um lançamento com motivo (append em observacao).
     * Lançamentos CANCELADOS não podem ser reativados por esta API.
     *
     * @param int    $id     ID.
     * @param string $motivo Motivo do cancelamento.
     *
     * @return bool
     */
    public function cancelar(int $id, string $motivo): bool
    {
        $antes = $this->find($id);
        if ($antes === null) {
            return false;
        }

        $obsAnterior = (string) ($antes['observacao'] ?? '');
        $novoObs = $obsAnterior;
        $linha = '[CANCELADO ' . date('Y-m-d H:i') . '] ' . trim($motivo);
        if ($novoObs !== '') {
            $novoObs .= "\n" . $linha;
        } else {
            $novoObs = $linha;
        }

        $sql = "UPDATE lancamentos
                SET status = 'CANCELADO', observacao = ?
                WHERE id = ? AND status <> 'CANCELADO'";
        $stmt = $this->pdo->prepare($sql);
        $ok = $stmt->execute([$novoObs, $id]);
        if ($ok && $stmt->rowCount() > 0) {
            $this->audit('DELETE', $id, $antes, ['status' => 'CANCELADO', 'observacao' => $novoObs]);
        }
        return $ok;
    }

    /**
     * Totais de receitas e despesas por mês (para gráfico de fluxo de caixa).
     * Considera apenas PAGO (caixa realizado).
     *
     * @param int $meses Quantidade de meses anteriores (incluindo o atual).
     *
     * @return list<array{mes: string, receitas: float, despesas: float}>
     */
    public function totaisByMes(int $meses = 6): array
    {
        $sql = "SELECT DATE_FORMAT(l.data_pagamento, '%Y-%m') AS mes,
                       COALESCE(SUM(CASE WHEN l.tipo = ? AND l.status = ? THEN l.valor ELSE 0 END), 0) AS receitas,
                       COALESCE(SUM(CASE WHEN l.tipo = ? AND l.status = ? THEN l.valor ELSE 0 END), 0) AS despesas
                FROM lancamentos l
                WHERE l.status = ?
                  AND l.data_pagamento IS NOT NULL
                  AND l.data_pagamento >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
                  AND l.tipo <> ?
                GROUP BY mes
                ORDER BY mes ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['RECEITA', 'PAGO', 'DESPESA', 'PAGO', 'PAGO', $meses, 'TRANSFERENCIA']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'mes'       => (string) $row['mes'],
                'receitas'  => (float) $row['receitas'],
                'despesas'  => (float) $row['despesas'],
            ];
        }
        return $out;
    }

    /**
     * Soma de receitas pagas em período, com filtros opcionais.
     *
     * @param string    $inicio  YYYY-MM-DD.
     * @param string    $fim     YYYY-MM-DD.
     * @param int|null  $contaId Filtro de conta (opcional).
     * @param int|null  $planoId Filtro de plano (opcional).
     *
     * @return float
     */
    public function somaReceitasByPeriodo(string $inicio, string $fim, ?int $contaId = null, ?int $planoId = null): float
    {
        return $this->somaByPeriodoETipo('RECEITA', $inicio, $fim, $contaId, $planoId);
    }

    /**
     * Soma de despesas pagas em período, com filtros opcionais.
     *
     * @param string    $inicio  YYYY-MM-DD.
     * @param string    $fim     YYYY-MM-DD.
     * @param int|null  $contaId Filtro de conta (opcional).
     * @param int|null  $planoId Filtro de plano (opcional).
     *
     * @return float
     */
    public function somaDespesasByPeriodo(string $inicio, string $fim, ?int $contaId = null, ?int $planoId = null): float
    {
        return $this->somaByPeriodoETipo('DESPESA', $inicio, $fim, $contaId, $planoId);
    }

    /**
     * Fluxo de caixa diário — realiza saldo_acumulado a partir de 0 no início
     * do período.
     *
     * @param string    $inicio  YYYY-MM-DD.
     * @param string    $fim     YYYY-MM-DD.
     * @param int|null  $contaId Filtro de conta (opcional, null = todas).
     *
     * @return list<array{data: string, receitas: float, despesas: float, saldo_dia: float, saldo_acumulado: float}>
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function fluxoCaixaDiario(string $inicio, string $fim, ?int $contaId = null): array
    {
        $sql = "SELECT l.data_pagamento AS data,
                       COALESCE(SUM(CASE WHEN l.tipo = ? THEN l.valor ELSE 0 END), 0) AS receitas,
                       COALESCE(SUM(CASE WHEN l.tipo = ? THEN l.valor ELSE 0 END), 0) AS despesas
                FROM lancamentos l
                WHERE l.status = ?
                  AND l.data_pagamento IS NOT NULL
                  AND l.data_pagamento BETWEEN ? AND ?
                  AND l.tipo <> ?";

        $params = ['RECEITA', 'DESPESA', 'PAGO', $inicio, $fim, 'TRANSFERENCIA'];

        if ($contaId !== null) {
            $sql .= ' AND l.conta_id = ?';
            $params[] = $contaId;
        }

        $sql .= ' GROUP BY l.data_pagamento ORDER BY l.data_pagamento ASC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao consultar fluxo de caixa diário: ' . $e->getMessage(), 0, $e);
        }

        $out = [];
        $acumulado = 0.0;
        foreach ($rows as $row) {
            $rec = (float) $row['receitas'];
            $des = (float) $row['despesas'];
            $dia = $rec - $des;
            $acumulado += $dia;
            $out[] = [
                'data'             => (string) $row['data'],
                'receitas'         => $rec,
                'despesas'         => $des,
                'saldo_dia'        => $dia,
                'saldo_acumulado'  => $acumulado,
            ];
        }
        return $out;
    }

    /**
     * Lançamentos somados por plano + filhos (recursivo).
     *
     * @param int    $planoId ID do plano raiz.
     * @param string $inicio  YYYY-MM-DD.
     * @param string $fim     YYYY-MM-DD.
     *
     * @return array{id: int, codigo: string, nome: string, total: float, filhos: list<array<string,mixed>>}
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function byPlanoTree(int $planoId, string $inicio, string $fim): array
    {
        $plano = $this->findPlano($planoId);
        if ($plano === null) {
            throw new RuntimeException('Plano de contas não encontrado: ' . $planoId);
        }
        return $this->aggregatePlanoTree($plano, $inicio, $fim);
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
     * Hard-delete bloqueado para lançamentos PAGO — use cancelar().
     *
     * @param int $id ID.
     *
     * @return bool
     *
     * @throws RuntimeException Se tentar excluir PAGO.
     */
    public function delete(int $id): bool
    {
        $row = $this->find($id);
        if ($row !== null && ($row['status'] ?? '') === 'PAGO') {
            throw new RuntimeException('Lançamento PAGO não pode ser excluído. Use cancelar().');
        }
        $ok = parent::delete($id);
        if ($ok) {
            $this->audit('DELETE', $id, $row, null);
        }
        return $ok;
    }

    // -----------------------------------------------------------------
    // Helpers internos.
    // -----------------------------------------------------------------

    /**
     * Soma por período e tipo (RECEITA | DESPESA), PAGO.
     *
     * @param string   $tipo    Tipo.
     * @param string   $inicio  YYYY-MM-DD.
     * @param string   $fim     YYYY-MM-DD.
     * @param int|null $contaId Filtro de conta.
     * @param int|null $planoId Filtro de plano.
     *
     * @return float
     */
    private function somaByPeriodoETipo(string $tipo, string $inicio, string $fim, ?int $contaId, ?int $planoId): float
    {
        $sql = 'SELECT COALESCE(SUM(l.valor), 0) AS total
                FROM lancamentos l
                WHERE l.tipo = ? AND l.status = ?
                  AND l.data_pagamento IS NOT NULL
                  AND l.data_pagamento BETWEEN ? AND ?';

        $params = [$tipo, 'PAGO', $inicio, $fim];

        if ($contaId !== null) {
            $sql .= ' AND l.conta_id = ?';
            $params[] = $contaId;
        }
        if ($planoId !== null) {
            $sql .= ' AND l.plano_conta_id = ?';
            $params[] = $planoId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Busca um nó de plano_contas.
     *
     * @param int $id ID.
     *
     * @return array<string,mixed>|null
     */
    private function findPlano(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM plano_contas WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Soma lançamentos de um plano específico em período.
     *
     * @param int    $planoId ID do plano.
     * @param string $inicio  YYYY-MM-DD.
     * @param string $fim     YYYY-MM-DD.
     *
     * @return float
     */
    private function somaPorPlano(int $planoId, string $inicio, string $fim): float
    {
        $sql = 'SELECT COALESCE(SUM(l.valor), 0) AS total
                FROM lancamentos l
                WHERE l.plano_conta_id = ?
                  AND l.status = ?
                  AND l.data_pagamento IS NOT NULL
                  AND l.data_pagamento BETWEEN ? AND ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$planoId, 'PAGO', $inicio, $fim]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (float) ($row['total'] ?? 0);
    }

    /**
     * Agrega recursivamente um nó de plano com seus filhos.
     *
     * @param array<string,mixed> $plano  Nó atual.
     * @param string              $inicio YYYY-MM-DD.
     * @param string              $fim    YYYY-MM-DD.
     *
     * @return array{id: int, codigo: string, nome: string, total: float, filhos: list<array<string,mixed>>}
     */
    private function aggregatePlanoTree(array $plano, string $inicio, string $fim): array
    {
        $id = (int) $plano['id'];
        $total = $this->somaPorPlano($id, $inicio, $fim);
        $filhos = [];
        $filhosRows = $this->pdo->prepare(
            'SELECT * FROM plano_contas WHERE parent_id = ? ORDER BY ordem ASC, codigo ASC'
        );
        $filhosRows->execute([$id]);
        foreach ($filhosRows->fetchAll(PDO::FETCH_ASSOC) as $child) {
            $filhos[] = $this->aggregatePlanoTree($child, $inicio, $fim);
        }

        // Total do nó inclui seus filhos.
        foreach ($filhos as $f) {
            $total += (float) $f['total'];
        }

        return [
            'id'     => $id,
            'codigo' => (string) $plano['codigo'],
            'nome'   => (string) $plano['nome'],
            'total'  => $total,
            'filhos' => $filhos,
        ];
    }
}
