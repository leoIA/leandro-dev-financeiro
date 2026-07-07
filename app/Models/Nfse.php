<?php
declare(strict_types=1);

/**
 * @file Nfse.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model principal de NFSe (Nota Fiscal de Serviço Eletrônica) — tabela nfse.
 *
 * Responsabilidades:
 *   - Sequenciamento atômico do número RPS (proximoRps)
 *   - Lookup com joins (municipio, conta, lancamento, usuario_criador)
 *   - Consultas por período, status, município, últimas emitidas
 *   - Transições de status: AUTORIZADA, REJEITADA, CANCELADA
 *   - Vínculo opcional com lançamento financeiro de receita
 *   - Cálculo automático de valor_base_calculo, valor_iss, valor_liquido
 *     (padrão ABRASF) em create() e update()
 *   - Auditoria automática em create/update/marcarComo*
 *
 * Status válidos: RASCUNHO | PROCESSANDO | AUTORIZADA | REJEITADA | CANCELADA.
 *
 * @see db_nfse.sql (schema da tabela nfse)
 * @see App\Nfse\Adapters\*Adapter (consome Nfse como array nos métodos emitir())
 */

/** @file Nfse.php | @package App\Models | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Models;

use PDO;
use RuntimeException;

class Nfse extends BaseModel
{
    protected string $table = 'nfse';
    protected string $modulo = 'nfse';

    // -----------------------------------------------------------------
    // Sequenciamento RPS.
    // -----------------------------------------------------------------

    /**
     * Lê nfse_proximo_numero_rps da tabela configuracoes (em transação com
     * SELECT ... FOR UPDATE), incrementa o contador e retorna o valor usado.
     *
     * @return int Número RPS utilizado.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function proximoRps(): int
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT valor FROM configuracoes WHERE chave = 'nfse_proximo_numero_rps' FOR UPDATE"
            );
            $stmt->execute();
            $row    = $stmt->fetch(PDO::FETCH_ASSOC);
            $atual  = $row === false ? 1 : (int) $row['valor'];
            $proximo = $atual + 1;

            // UPSERT — garante que a linha exista mesmo se o installer não a criou.
            $upd = $this->pdo->prepare(
                "INSERT INTO configuracoes (chave, valor, tipo, descricao)
                 VALUES ('nfse_proximo_numero_rps', ?, 'int', 'Próximo número de RPS a usar')
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()"
            );
            $upd->execute([(string) $proximo]);

            $this->pdo->commit();
            return $atual;
        } catch (RuntimeException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException(
                'Falha ao obter próximo RPS: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    // -----------------------------------------------------------------
    // Consultas com joins.
    // -----------------------------------------------------------------

    /**
     * Busca NFSe por ID com joins: municipio, conta, lancamento, usuario_criador.
     *
     * @param int $id ID da NFSe.
     *
     * @return array<string,mixed>|null
     */
    public function findWithRelations(int $id): ?array
    {
        $sql = 'SELECT n.*,
                       m.nome AS municipio_nome,
                       m.codigo_ibge AS municipio_ibge,
                       m.provedor AS municipio_provedor,
                       c.nome AS conta_nome,
                       l.descricao AS lancamento_descricao,
                       l.valor AS lancamento_valor,
                       u.nome AS criado_por_nome
                FROM nfse n
                LEFT JOIN municipios_nfse m ON m.id = n.municipio_id
                LEFT JOIN contas c          ON c.id = n.conta_id
                LEFT JOIN lancamentos l     ON l.id = n.lancamento_id
                LEFT JOIN usuarios u        ON u.id = n.criado_por
                WHERE n.id = ?
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Lista NFSes por período (data_emissao) com filtros opcionais.
     *
     * @param string                $inicio  YYYY-MM-DD.
     * @param string                $fim     YYYY-MM-DD.
     * @param array<string,mixed>   $filtros Chaves opcionais: status, municipio_id, ambiente, conta_id.
     *
     * @return list<array<string,mixed>>
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function byPeriodo(string $inicio, string $fim, array $filtros = []): array
    {
        $sql = 'SELECT n.*,
                       m.nome AS municipio_nome,
                       m.provedor AS municipio_provedor,
                       c.nome AS conta_nome
                FROM nfse n
                LEFT JOIN municipios_nfse m ON m.id = n.municipio_id
                LEFT JOIN contas c          ON c.id = n.conta_id
                WHERE n.data_emissao BETWEEN ? AND ?';

        $params = [$inicio, $fim];

        $map = [
            'status'       => 'n.status',
            'municipio_id' => 'n.municipio_id',
            'ambiente'     => 'n.ambiente',
            'conta_id'     => 'n.conta_id',
        ];
        foreach ($map as $key => $col) {
            if (isset($filtros[$key]) && $filtros[$key] !== '' && $filtros[$key] !== null) {
                $sql .= " AND {$col} = ?";
                $params[] = $filtros[$key];
            }
        }

        $sql .= ' ORDER BY n.data_emissao DESC, n.id DESC';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Falha ao consultar NFSes por período: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Lista NFSes por status.
     *
     * @param string $status RASCUNHO|PROCESSANDO|AUTORIZADA|REJEITADA|CANCELADA.
     *
     * @return list<array<string,mixed>>
     */
    public function byStatus(string $status): array
    {
        $sql = 'SELECT n.*,
                       m.nome AS municipio_nome,
                       m.provedor AS municipio_provedor
                FROM nfse n
                LEFT JOIN municipios_nfse m ON m.id = n.municipio_id
                WHERE n.status = ?
                ORDER BY n.data_emissao DESC, n.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista NFSes por município.
     *
     * @param int $municipioId ID do município.
     *
     * @return list<array<string,mixed>>
     */
    public function byMunicipio(int $municipioId): array
    {
        $sql = 'SELECT n.*,
                       m.nome AS municipio_nome,
                       m.provedor AS municipio_provedor
                FROM nfse n
                INNER JOIN municipios_nfse m ON m.id = n.municipio_id
                WHERE n.municipio_id = ?
                ORDER BY n.data_emissao DESC, n.id DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$municipioId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Últimas NFSes emitidas (com join de município).
     *
     * @param int $limit Quantidade (default 10).
     *
     * @return list<array<string,mixed>>
     */
    public function ultimas(int $limit = 10): array
    {
        $sql = 'SELECT n.*,
                       m.nome AS municipio_nome,
                       m.provedor AS municipio_provedor
                FROM nfse n
                LEFT JOIN municipios_nfse m ON m.id = n.municipio_id
                ORDER BY n.data_emissao DESC, n.id DESC
                LIMIT ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // -----------------------------------------------------------------
    // Transições de status.
    // -----------------------------------------------------------------

    /**
     * Marca NFSe como AUTORIZADA com dados de retorno da prefeitura.
     * Limpa mensagem_erro e define data_autorizacao = NOW.
     *
     * @param int                  $id      ID da NFSe.
     * @param array<string,mixed>  $retorno Deve conter: numero, codigo_verificacao,
     *                                      protocolo, xml_envio, xml_retorno.
     *
     * @return void
     */
    public function marcarComoAutorizada(int $id, array $retorno): void
    {
        $antes = $this->find($id);
        $data  = [
            'status'             => 'AUTORIZADA',
            'numero_nfse'        => (string) ($retorno['numero'] ?? ''),
            'codigo_verificacao' => (string) ($retorno['codigo_verificacao'] ?? ''),
            'protocolo'          => (string) ($retorno['protocolo'] ?? ''),
            'xml_envio'          => (string) ($retorno['xml_envio'] ?? ''),
            'xml_retorno'        => (string) ($retorno['xml_retorno'] ?? ''),
            'mensagem_erro'      => null,
            'data_autorizacao'   => date('Y-m-d H:i:s'),
        ];
        $ok = parent::update($id, $data);
        if ($ok) {
            // Não loga XMLs no payload de auditoria (podem conter dados sensíveis
            // e gerar volume grande no campo dados_novos da tabela logs_auditoria).
            $auditData = $data;
            unset($auditData['xml_envio'], $auditData['xml_retorno']);
            $this->audit('UPDATE', $id, $antes, $auditData);
        }
    }

    /**
     * Marca NFSe como REJEITADA com erro.
     *
     * @param int         $id         ID da NFSe.
     * @param string      $erro       Mensagem de erro da prefeitura.
     * @param string|null $xmlEnvio   XML enviado (opcional).
     * @param string|null $xmlRetorno XML retornado (opcional).
     *
     * @return void
     */
    public function marcarComoRejeitada(
        int $id,
        string $erro,
        ?string $xmlEnvio = null,
        ?string $xmlRetorno = null
    ): void {
        $antes = $this->find($id);
        $data  = [
            'status'        => 'REJEITADA',
            'mensagem_erro' => $erro,
        ];
        if ($xmlEnvio !== null) {
            $data['xml_envio'] = $xmlEnvio;
        }
        if ($xmlRetorno !== null) {
            $data['xml_retorno'] = $xmlRetorno;
        }
        $ok = parent::update($id, $data);
        if ($ok) {
            $auditData = $data;
            unset($auditData['xml_envio'], $auditData['xml_retorno']);
            $this->audit('UPDATE', $id, $antes, $auditData);
        }
    }

    /**
     * Marca NFSe como CANCELADA com motivo e data_cancelamento = NOW.
     *
     * @param int    $id     ID da NFSe.
     * @param string $motivo Motivo do cancelamento.
     *
     * @return void
     */
    public function marcarComoCancelada(int $id, string $motivo): void
    {
        $antes = $this->find($id);
        $data  = [
            'status'              => 'CANCELADA',
            'data_cancelamento'   => date('Y-m-d H:i:s'),
            'motivo_cancelamento' => $motivo,
        ];
        $ok = parent::update($id, $data);
        if ($ok) {
            $this->audit('DELETE', $id, $antes, $data);
        }
    }

    /**
     * Vincula um lançamento financeiro de receita à NFSe.
     *
     * @param int $nfseId       ID da NFSe.
     * @param int $lancamentoId ID do lançamento.
     *
     * @return void
     */
    public function vincularLancamento(int $nfseId, int $lancamentoId): void
    {
        $antes = $this->find($nfseId);
        $ok    = parent::update($nfseId, ['lancamento_id' => $lancamentoId]);
        if ($ok) {
            $this->audit('UPDATE', $nfseId, $antes, ['lancamento_id' => $lancamentoId]);
        }
    }

    // -----------------------------------------------------------------
    // Cálculo de valores (padrão ABRASF).
    // -----------------------------------------------------------------

    /**
     * Calcula valor_base_calculo, valor_iss e valor_liquido a partir de
     * valor_servicos, valor_deducoes, aliquota, descontos e iss_retido.
     *
     * Fórmulas:
     *   valor_base_calculo = valor_servicos - valor_deducoes - desconto_incondicionado
     *   valor_iss          = valor_base_calculo * aliquota
     *   valor_liquido      = valor_servicos - valor_deducoes - desconto_incondicionado
     *                        - desconto_condicionado - (iss_retido ? valor_iss : 0)
     *
     * @param array<string,mixed> $data
     *
     * @return array<string,mixed> $data com valor_base_calculo, valor_iss, valor_liquido preenchidos.
     */
    public function calcularValores(array $data): array
    {
        $valorServicos = (float) ($data['valor_servicos'] ?? 0);
        $valorDeducoes = (float) ($data['valor_deducoes'] ?? 0);
        $aliquota      = (float) ($data['aliquota'] ?? 0.03);
        $descIncond    = (float) ($data['desconto_incondicionado'] ?? 0);
        $descCond      = (float) ($data['desconto_condicionado'] ?? 0);
        $issRetido     = (int) ($data['iss_retido'] ?? 0) === 1;

        $base = $valorServicos - $valorDeducoes - $descIncond;
        if ($base < 0) {
            $base = 0.0;
        }
        $iss     = round($base * $aliquota, 2);
        $liquido = $valorServicos - $valorDeducoes - $descIncond - $descCond;
        if ($issRetido) {
            $liquido -= $iss;
        }

        $data['valor_base_calculo'] = round($base, 2);
        $data['valor_iss']          = $iss;
        $data['valor_liquido']      = round($liquido, 2);
        return $data;
    }

    // -----------------------------------------------------------------
    // Overrides de create/update com cálculo + auditoria.
    // -----------------------------------------------------------------

    /**
     * Override do create: chama calcularValores antes do insert + log auditoria.
     *
     * @param array<string,mixed> $data
     *
     * @return int
     */
    public function create(array $data): int
    {
        $data = $this->calcularValores($data);
        $id   = parent::create($data);
        $this->audit('CREATE', $id, null, $data);
        return $id;
    }

    /**
     * Override do update: se houver campos de valor/alíquota, recalcula;
     * em qualquer caso, registra auditoria (antes/depois).
     *
     * @param int                   $id
     * @param array<string,mixed>   $data
     *
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        $antes = $this->find($id);

        $camposValor = [
            'valor_servicos',
            'valor_deducoes',
            'aliquota',
            'desconto_incondicionado',
            'desconto_condicionado',
            'iss_retido',
        ];
        $recalc = false;
        foreach ($camposValor as $c) {
            if (array_key_exists($c, $data)) {
                $recalc = true;
                break;
            }
        }
        if ($recalc && $antes !== null) {
            $merged       = array_merge($antes, $data);
            $calc         = $this->calcularValores($merged);
            $data['valor_base_calculo'] = $calc['valor_base_calculo'];
            $data['valor_iss']          = $calc['valor_iss'];
            $data['valor_liquido']      = $calc['valor_liquido'];
        }

        $ok = parent::update($id, $data);
        if ($ok) {
            $this->audit('UPDATE', $id, $antes, $data);
        }
        return $ok;
    }
}
