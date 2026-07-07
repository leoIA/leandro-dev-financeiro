<?php
/**
 * @file ContaProgramada.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de contas programadas (recorrências).
 *
 * gerarPendentes(): gera lançamentos PENDENTE enquanto proxima_geracao <= today,
 * respeitando data_termino e parcelas_total. Transação PDO atômica.
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Auth;
use App\Core\Logger;
use PDO;
use RuntimeException;

class ContaProgramada extends BaseModel
{
    protected string $table = 'contas_programadas';
    protected string $modulo = 'contas_programadas';

    /**
     * Lista próximas contas a vencer dentro de N dias.
     *
     * @param int $dias  Janela de dias.
     * @param int $limit Limite de resultados.
     *
     * @return list<array<string,mixed>>
     */
    public function proximasVencimentos(int $dias = 30, int $limit = 20): array
    {
        $sql = 'SELECT cp.*,
                       c.nome AS conta_nome,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome,
                       cf.nome_razao_social AS cf_nome
                FROM contas_programadas cp
                INNER JOIN contas c ON c.id = cp.conta_id
                INNER JOIN plano_contas pc ON pc.id = cp.plano_conta_id
                LEFT JOIN clientes_fornecedores cf ON cf.id = cp.cliente_fornecedor_id
                WHERE cp.ativo = 1
                  AND cp.proxima_geracao IS NOT NULL
                  AND cp.proxima_geracao <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY cp.proxima_geracao ASC
                LIMIT ?';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $dias, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Gera todos os lançamentos pendentes de contas programadas vencidas.
     *
     * Algoritmo:
     *   Para cada ativa com proxima_geracao <= today:
     *     while (proxima_geracao <= today)
     *        AND (data_termino IS NULL OR proxima_geracao <= data_termino)
     *        AND (parcelas_total IS NULL OR parcelas_geradas < parcelas_total):
     *       INSERT lancamento PENDENTE
     *       parcelas_geradas++
     *       ultima_geracao = proxima_geracao
     *       proxima_geracao = calcularProxima(...)
     *     UPDATE contas_programadas SET ultima_geracao=?, proxima_geracao=?, parcelas_geradas=? WHERE id=?
     *   Return total de lançamentos criados.
     *
     * @return int Quantidade de lançamentos gerados.
     *
     * @throws RuntimeException Em caso de falha (rollback automático).
     */
    public function gerarPendentes(): int
    {
        $today = date('Y-m-d');

        // Carrega todas as programadas que precisam de geração.
        $sql = 'SELECT * FROM contas_programadas
                WHERE ativo = 1
                  AND proxima_geracao IS NOT NULL
                  AND proxima_geracao <= ?
                ORDER BY id ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$today]);
        $programadas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($programadas)) {
            return 0;
        }

        $totalGerados = 0;
        $usuarioId = Auth::id();

        $insLanc = $this->pdo->prepare(
            'INSERT INTO lancamentos
                (conta_id, plano_conta_id, cliente_fornecedor_id, conta_programada_id,
                 tipo, valor, data_lancamento, data_vencimento, descricao, status,
                 forma_pagamento, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $updProg = $this->pdo->prepare(
            'UPDATE contas_programadas
             SET ultima_geracao = ?, proxima_geracao = ?, parcelas_geradas = ?
             WHERE id = ?'
        );

        try {
            $this->pdo->beginTransaction();

            foreach ($programadas as $p) {
                $id = (int) $p['id'];
                $proxima = (string) $p['proxima_geracao'];
                $frequencia = (string) $p['frequencia'];
                $diaRef = $p['dia_referencia'] !== null ? (int) $p['dia_referencia'] : null;
                $dataTermino = $p['data_termino'] !== null ? (string) $p['data_termino'] : null;
                $parcelasTotal = $p['parcelas_total'] !== null ? (int) $p['parcelas_total'] : null;
                $parcelasGeradas = (int) $p['parcelas_geradas'];
                $ultimaGeracao = $p['ultima_geracao'] !== null ? (string) $p['ultima_geracao'] : null;

                $geradasNestaExecucao = 0;
                $safetyCounter = 0;
                $safetyMax = 10000; // Proteção contra loop infinito defensiva.

                while (
                    strtotime($proxima) <= strtotime($today)
                    && ($dataTermino === null || strtotime($proxima) <= strtotime($dataTermino))
                    && ($parcelasTotal === null || $parcelasGeradas < $parcelasTotal)
                    && $safetyCounter < $safetyMax
                ) {
                    $insLanc->execute([
                        (int) $p['conta_id'],
                        (int) $p['plano_conta_id'],
                        $p['cliente_fornecedor_id'] !== null ? (int) $p['cliente_fornecedor_id'] : null,
                        $id,
                        (string) $p['tipo'],
                        (float) $p['valor'],
                        $proxima,
                        $proxima,
                        (string) $p['descricao'],
                        'PENDENTE',
                        $p['forma_pagamento'] !== null ? (string) $p['forma_pagamento'] : null,
                        $usuarioId,
                    ]);

                    $parcelasGeradas++;
                    $geradasNestaExecucao++;
                    $safetyCounter++;
                    $ultimaGeracao = $proxima;
                    $proxima = $this->calcularProxima($proxima, $frequencia, $diaRef);
                }

                if ($geradasNestaExecucao > 0) {
                    // Se a próxima caiu fora do limite (término/parcelas), manter NULL.
                    $proximaFinal = $proxima;
                    if ($dataTermino !== null && strtotime($proxima) > strtotime($dataTermino)) {
                        $proximaFinal = null;
                    }
                    if ($parcelasTotal !== null && $parcelasGeradas >= $parcelasTotal) {
                        $proximaFinal = null;
                    }

                    $updProg->execute([
                        $ultimaGeracao,
                        $proximaFinal,
                        $parcelasGeradas,
                        $id,
                    ]);

                    $totalGerados += $geradasNestaExecucao;
                }
            }

            $this->pdo->commit();

            if ($totalGerados > 0) {
                Logger::info('Contas programadas geradas', ['total' => $totalGerados]);
            }

            return $totalGerados;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Falha ao gerar contas programadas: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Calcula a próxima data de geração.
     *
     * @param string   $dataAtual     YYYY-MM-DD.
     * @param string   $frequencia    DIARIO|SEMANAL|MENSAL|ANUAL|PERSONALIZADO.
     * @param int|null $diaReferencia Dia do mês (1-31) ou semana (0-6).
     *
     * @return string YYYY-MM-DD.
     */
    public function calcularProxima(string $dataAtual, string $frequencia, ?int $diaReferencia): string
    {
        try {
            $date = new \DateTimeImmutable($dataAtual);
        } catch (\Throwable $e) {
            // Fallback defensivo.
            return date('Y-m-d', strtotime($dataAtual . ' +1 month'));
        }

        switch (strtoupper($frequencia)) {
            case 'DIARIO':
                return $date->modify('+1 day')->format('Y-m-d');

            case 'SEMANAL':
                return $date->modify('+7 days')->format('Y-m-d');

            case 'MENSAL':
                $next = $date->modify('+1 month');
                if ($diaReferencia !== null && $diaReferencia >= 1 && $diaReferencia <= 31) {
                    $diasNoMes = (int) $next->format('t');
                    $dia = min($diaReferencia, $diasNoMes);
                    $next = $next->setDate(
                        (int) $next->format('Y'),
                        (int) $next->format('m'),
                        $dia
                    );
                }
                return $next->format('Y-m-d');

            case 'ANUAL':
                return $date->modify('+1 year')->format('Y-m-d');

            case 'PERSONALIZADO':
            default:
                return $date->modify('+1 month')->format('Y-m-d');
        }
    }

    /**
     * Contas programadas ativas.
     *
     * @return list<array<string,mixed>>
     */
    public function ativas(): array
    {
        $sql = 'SELECT cp.*,
                       c.nome AS conta_nome,
                       pc.codigo AS plano_codigo, pc.nome AS plano_nome
                FROM contas_programadas cp
                INNER JOIN contas c ON c.id = cp.conta_id
                INNER JOIN plano_contas pc ON pc.id = cp.plano_conta_id
                WHERE cp.ativo = 1
                ORDER BY cp.proxima_geracao ASC, cp.descricao ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria conta programada, setando proxima_geracao = data_inicio.
     *
     * @param array<string,mixed> $data Dados.
     *
     * @return int
     *
     * @throws RuntimeException Se data_inicio não for informada.
     */
    public function create(array $data): int
    {
        if (empty($data['data_inicio'])) {
            throw new RuntimeException('data_inicio é obrigatório para conta programada.');
        }
        if (!isset($data['proxima_geracao']) || $data['proxima_geracao'] === '' || $data['proxima_geracao'] === null) {
            $data['proxima_geracao'] = $data['data_inicio'];
        }
        if (!isset($data['parcelas_geradas'])) {
            $data['parcelas_geradas'] = 0;
        }

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
