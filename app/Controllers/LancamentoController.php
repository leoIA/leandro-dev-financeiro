<?php
/**
 * @file    LancamentoController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Lançamentos financeiros (RECEITA / DESPESA / TRANSFERENCIA).
 *
 * Rotas:
 *   GET  /lancamentos                  → index() — listar com filtros
 *   POST /lancamentos                  → index() delega novo() — criar
 *   GET  /lancamentos/novo             → novo() — form
 *   GET  /lancamentos/{id}/editar      → editar($id) — form
 *   POST /lancamentos/{id}             → index($id) delega editar() — atualizar (_method=PUT)
 *   POST /lancamentos/{id}/marcar-pago → marcarPago($id)
 *   POST /lancamentos/{id}/estornar    → estornar($id)
 *   POST /lancamentos/{id}/cancelar    → cancelar($id) — com motivo
 *   GET  /lancamentos/exportar-csv     → exportarCsv()
 *
 * Regras:
 *   - Lançamento PAGO não pode ter tipo/valor/conta/plano alterados (estorne primeiro).
 *   - Lançamento CANCELADO não pode ser editado.
 *   - delete() bloqueia PAGO; use cancelar() com motivo.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Helpers\Sanitizer;
use App\Models\ClienteFornecedor;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\PlanoContas;

class LancamentoController
{
    /**
     * GET  /lancamentos — listar com filtros.
     * POST /lancamentos — criar (delega para novo()).
     * POST /lancamentos/{id} — atualizar (delega para editar()).
     *
     * @param string $id ID opcional.
     *
     * @return void
     */
    public function index(string $id = ''): void
    {
        if (Request::isPost() && $id === '') {
            $this->novo();
            return;
        }
        if (Request::isPost() && $id !== '') {
            $this->editar($id);
            return;
        }
        if ($id !== '') {
            Response::redirect('index.php?route=lancamentos/' . urlencode($id) . '/editar');
            return;
        }

        Auth::require('lancamentos', 'read');

        $filtros = [
            'conta_id'       => (string) (Request::get('conta_id') ?? ''),
            'plano_conta_id' => (string) (Request::get('plano_conta_id') ?? ''),
            'tipo'           => (string) (Request::get('tipo') ?? ''),
            'status'         => (string) (Request::get('status') ?? ''),
            'favorecido'     => trim((string) (Request::get('favorecido') ?? '')),
            'data_inicio'    => (string) (Request::get('data_inicio') ?? ''),
            'data_fim'       => (string) (Request::get('data_fim') ?? ''),
        ];

        // Defaults: últimos 90 dias se nenhum período.
        $inicio = $filtros['data_inicio'] !== ''
            ? (Sanitizer::date($filtros['data_inicio']) ?? $filtros['data_inicio'])
            : date('Y-m-d', strtotime('-90 days'));
        $fim = $filtros['data_fim'] !== ''
            ? (Sanitizer::date($filtros['data_fim']) ?? $filtros['data_fim'])
            : date('Y-m-d');

        $filtrosNorm = [
            'conta_id'       => $filtros['conta_id'] !== '' ? (int) $filtros['conta_id'] : null,
            'plano_conta_id' => $filtros['plano_conta_id'] !== '' ? (int) $filtros['plano_conta_id'] : null,
            'tipo'           => $filtros['tipo'] !== '' ? $filtros['tipo'] : null,
            'status'         => $filtros['status'] !== '' ? $filtros['status'] : null,
        ];

        $lancamentoModel = new Lancamento();
        $lancamentos = $lancamentoModel->byPeriodo($inicio, $fim, array_filter($filtrosNorm, static fn ($v): bool => $v !== null));

        // Filtro por favorecido em memória (nome pode estar em cf_nome).
        if ($filtros['favorecido'] !== '') {
            $fav = mb_strtolower($filtros['favorecido']);
            $lancamentos = array_values(array_filter($lancamentos, static function (array $l) use ($fav): bool {
                $nome = mb_strtolower((string) ($l['cf_nome'] ?? ''));
                return $nome !== '' && str_contains($nome, $fav);
            }));
        }

        // Carrega contas, planos e clientes para os selects/filtros.
        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();
        $clientesFornecedores = (new ClienteFornecedor())->ativos();

        Response::view('lancamentos/index', [
            'pageTitle'           => 'Lançamentos',
            'lancamentos'         => $lancamentos,
            'contas'              => $contas,
            'planos'              => $planos,
            'clientesFornecedores' => $clientesFornecedores,
            'filtros'             => $filtros,
        ]);
    }

    /**
     * GET  /lancamentos/novo — form de criação.
     * POST /lancamentos      — valida e cria.
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('lancamentos', 'create');

        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();
        $clientesFornecedores = (new ClienteFornecedor())->ativos();

        if (!Request::isPost()) {
            Response::view('lancamentos/novo', [
                'pageTitle'           => 'Novo Lançamento',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=lancamentos/novo');
        }

        $data = $this->collectFromRequest();
        $data['criado_por'] = Auth::id();

        // Validações de negócio.
        if (!$this->validateLancamento($data, null, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('lancamentos/novo', [
                'pageTitle'           => 'Novo Lançamento',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
            return;
        }

        // Remove campos não-pertencentes à tabela.
        $insert = $this->prepareForPersistence($data);

        try {
            (new Lancamento())->create($insert);
            Flash::success('Lançamento criado com sucesso.');
            Response::redirect('index.php?route=lancamentos');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar lançamento: ' . $e->getMessage());
            Flash::error('Erro ao criar lançamento: ' . $e->getMessage());
            Response::view('lancamentos/novo', [
                'pageTitle'           => 'Novo Lançamento',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
        }
    }

    /**
     * GET  /lancamentos/{id}/editar — form de edição.
     * POST /lancamentos/{id}        — valida e atualiza (lock se PAGO/CANCELADO).
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        Auth::require('lancamentos', 'update');

        $lancamentoModel = new Lancamento();
        $lancamento = $lancamentoModel->find((int) $id);

        if ($lancamento === null) {
            Flash::error('Lançamento não encontrado.');
            Response::redirect('index.php?route=lancamentos');
        }

        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();
        $clientesFornecedores = (new ClienteFornecedor())->ativos();

        if (!Request::isPost()) {
            // Adiciona nome_razao_social se houver cliente_fornecedor_id.
            $cfId = (int) ($lancamento['cliente_fornecedor_id'] ?? 0);
            if ($cfId > 0) {
                foreach ($clientesFornecedores as $cf) {
                    if ((int) $cf['id'] === $cfId) {
                        $lancamento['nome_razao_social'] = $cf['nome_razao_social'] ?? '';
                        break;
                    }
                }
            }
            Response::view('lancamentos/editar', [
                'pageTitle'           => 'Editar Lançamento',
                'lancamento'          => $lancamento,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=lancamentos/' . $id . '/editar');
        }

        $status = (string) ($lancamento['status'] ?? 'PENDENTE');

        if ($status === 'CANCELADO') {
            Flash::error('Lançamento CANCELADO não pode ser editado.');
            Response::redirect('index.php?route=lancamentos/' . $id . '/editar');
        }

        // Se PAGO: preserva tipo/valor/conta/plano/data_lancamento/data_vencimento/forma_pagamento/documento/descricao.
        if ($status === 'PAGO') {
            $data = $this->collectFromRequestLocked($lancamento);
        } else {
            $data = $this->collectFromRequest();
        }

        if (!$this->validateLancamento($data, $lancamento, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('lancamentos/editar', [
                'pageTitle'           => 'Editar Lançamento',
                'lancamento'          => $lancamento,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
            return;
        }

        $update = $this->prepareForPersistence($data);

        try {
            $lancamentoModel->update((int) $id, $update);
            Flash::success('Lançamento atualizado com sucesso.');
            Response::redirect('index.php?route=lancamentos');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar lançamento: ' . $e->getMessage());
            Flash::error('Erro ao atualizar lançamento: ' . $e->getMessage());
            Response::view('lancamentos/editar', [
                'pageTitle'           => 'Editar Lançamento',
                'lancamento'          => $lancamento,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
        }
    }

    /**
     * POST /lancamentos/{id}/marcar-pago — marca como PAGO com data_pagamento = hoje.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function marcarPago(string $id): void
    {
        Auth::require('lancamentos', 'update');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=lancamentos');
        }

        try {
            $ok = (new Lancamento())->marcarComoPago((int) $id);
            if ($ok) {
                Flash::success('Lançamento marcado como PAGO.');
            } else {
                Flash::warning('Lançamento não estava PENDENTE ou não foi encontrado.');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao marcar lançamento como pago: ' . $e->getMessage());
            Flash::error('Erro ao marcar lançamento como pago.');
        }
        Response::redirect('index.php?route=lancamentos');
    }

    /**
     * POST /lancamentos/{id}/estornar — volta de PAGO para PENDENTE.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function estornar(string $id): void
    {
        Auth::require('lancamentos', 'update');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=lancamentos');
        }

        try {
            $ok = (new Lancamento())->estornar((int) $id);
            if ($ok) {
                Flash::success('Lançamento estornado (voltou para PENDENTE).');
            } else {
                Flash::warning('Lançamento não estava PAGO ou não foi encontrado.');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao estornar lançamento: ' . $e->getMessage());
            Flash::error('Erro ao estornar lançamento.');
        }
        Response::redirect('index.php?route=lancamentos');
    }

    /**
     * POST /lancamentos/{id}/cancelar — cancela com motivo (append em observacao).
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function cancelar(string $id): void
    {
        Auth::require('lancamentos', 'delete');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=lancamentos');
        }

        $motivo = trim((string) Request::post('motivo', ''));
        if ($motivo === '') {
            Flash::error('Motivo do cancelamento é obrigatório.');
            Response::redirect('index.php?route=lancamentos/' . $id . '/editar');
        }

        try {
            $ok = (new Lancamento())->cancelar((int) $id, $motivo);
            if ($ok) {
                Flash::success('Lançamento cancelado com sucesso.');
            } else {
                Flash::warning('Lançamento já estava CANCELADO ou não foi encontrado.');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao cancelar lançamento: ' . $e->getMessage());
            Flash::error('Erro ao cancelar lançamento.');
        }
        Response::redirect('index.php?route=lancamentos');
    }

    /**
     * GET /lancamentos/exportar-csv — exporta lançamentos filtrados em CSV.
     *
     * @return void
     */
    public function exportarCsv(): void
    {
        Auth::require('lancamentos', 'read');

        $filtros = [
            'conta_id'       => (string) (Request::get('conta_id') ?? ''),
            'plano_conta_id' => (string) (Request::get('plano_conta_id') ?? ''),
            'tipo'           => (string) (Request::get('tipo') ?? ''),
            'status'         => (string) (Request::get('status') ?? ''),
            'data_inicio'    => (string) (Request::get('data_inicio') ?? ''),
            'data_fim'       => (string) (Request::get('data_fim') ?? ''),
        ];

        $inicio = $filtros['data_inicio'] !== ''
            ? (Sanitizer::date($filtros['data_inicio']) ?? $filtros['data_inicio'])
            : date('Y-m-d', strtotime('-365 days'));
        $fim = $filtros['data_fim'] !== ''
            ? (Sanitizer::date($filtros['data_fim']) ?? $filtros['data_fim'])
            : date('Y-m-d');

        $filtrosNorm = [
            'conta_id'       => $filtros['conta_id'] !== '' ? (int) $filtros['conta_id'] : null,
            'plano_conta_id' => $filtros['plano_conta_id'] !== '' ? (int) $filtros['plano_conta_id'] : null,
            'tipo'           => $filtros['tipo'] !== '' ? $filtros['tipo'] : null,
            'status'         => $filtros['status'] !== '' ? $filtros['status'] : null,
        ];

        $lancamentos = (new Lancamento())->byPeriodo($inicio, $fim, array_filter($filtrosNorm, static fn ($v): bool => $v !== null));

        $fileName = 'lancamentos_' . date('Ymd_His') . '.csv';

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel.
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['ID', 'Data', 'Conta', 'Favorecido', 'Plano', 'Tipo', 'Valor', 'Status', 'Data Pagamento', 'Forma Pagamento', 'Documento', 'Descrição', 'Observação'], ';');

        foreach ($lancamentos as $l) {
            fputcsv($out, [
                (int) ($l['id'] ?? 0),
                (string) ($l['data_lancamento'] ?? ''),
                (string) ($l['conta_nome'] ?? ''),
                (string) ($l['cf_nome'] ?? ''),
                (string) ($l['plano_nome'] ?? ''),
                (string) ($l['tipo'] ?? ''),
                number_format((float) ($l['valor'] ?? 0), 2, ',', '.'),
                (string) ($l['status'] ?? ''),
                (string) ($l['data_pagamento'] ?? ''),
                (string) ($l['forma_pagamento'] ?? ''),
                (string) ($l['documento'] ?? ''),
                (string) ($l['descricao'] ?? ''),
                (string) ($l['observacao'] ?? ''),
            ], ';');
        }
        fclose($out);
        exit;
    }

    // -----------------------------------------------------------------
    // Helpers internos.
    // -----------------------------------------------------------------

    /**
     * Coleta dados do request e normaliza para o formato do model.
     *
     * @return array<string,mixed>
     */
    private function collectFromRequest(): array
    {
        $clienteId = Request::post('cliente_fornecedor_id', '');
        $clienteId = ($clienteId === '' || $clienteId === null) ? null : (int) $clienteId;

        $status = (string) Request::post('status', 'PENDENTE');
        $dataPagamento = null;
        if ($status === 'PAGO') {
            $dp = trim((string) Request::post('data_pagamento', ''));
            $dataPagamento = $dp !== '' ? $dp : date('Y-m-d');
        }

        return [
            'conta_id'               => (int) Request::post('conta_id', 0),
            'plano_conta_id'         => (int) Request::post('plano_conta_id', 0),
            'cliente_fornecedor_id'  => $clienteId,
            'tipo'                   => (string) Request::post('tipo', ''),
            'valor'                  => Sanitizer::decimal((string) Request::post('valor', '0')),
            'data_lancamento'        => (string) (Sanitizer::date((string) Request::post('data_lancamento', '')) ?? date('Y-m-d')),
            'data_vencimento'        => Sanitizer::date((string) Request::post('data_vencimento', '')),
            'data_pagamento'         => $dataPagamento,
            'descricao'              => trim((string) Request::post('descricao', '')),
            'observacao'             => trim((string) Request::post('observacao', '')) ?: null,
            'status'                 => $status,
            'forma_pagamento'        => Request::post('forma_pagamento', '') !== '' ? (string) Request::post('forma_pagamento', '') : null,
            'documento'              => trim((string) Request::post('documento', '')) ?: null,
        ];
    }

    /**
     * Coleta dados do request preservando campos bloqueados quando PAGO.
     *
     * @param array<string,mixed> $lancamento Registro atual.
     *
     * @return array<string,mixed>
     */
    private function collectFromRequestLocked(array $lancamento): array
    {
        $data = $this->collectFromRequest();
        // Preserva campos que não podem mudar quando PAGO.
        $data['tipo']            = (string) ($lancamento['tipo'] ?? $data['tipo']);
        $data['valor']           = (float) ($lancamento['valor'] ?? $data['valor']);
        $data['conta_id']        = (int) ($lancamento['conta_id'] ?? $data['conta_id']);
        $data['plano_conta_id']  = (int) ($lancamento['plano_conta_id'] ?? $data['plano_conta_id']);
        $data['data_lancamento'] = (string) ($lancamento['data_lancamento'] ?? $data['data_lancamento']);
        $data['data_vencimento'] = $lancamento['data_vencimento'] ?? $data['data_vencimento'];
        $data['forma_pagamento'] = $lancamento['forma_pagamento'] ?? $data['forma_pagamento'];
        $data['documento']       = $lancamento['documento'] ?? $data['documento'];
        $data['descricao']       = (string) ($lancamento['descricao'] ?? $data['descricao']);
        $data['status']          = 'PAGO';

        return $data;
    }

    /**
     * Valida um lançamento (criação ou edição).
     *
     * @param array<string,mixed>     $data    Dados normalizados.
     * @param array<string,mixed>|null $atual  Registro atual (null em criação).
     * @param list<string>            $errors  Saída: mensagens de erro (por referência).
     *
     * @return bool
     */
    private function validateLancamento(array $data, ?array $atual, array &$errors): bool
    {
        $errors = [];

        $tipo = (string) ($data['tipo'] ?? '');
        if (!in_array($tipo, ['RECEITA', 'DESPESA', 'TRANSFERENCIA'], true)) {
            $errors[] = 'Tipo inválido. Selecione Receita, Despesa ou Transferência.';
        }

        $valor = (float) ($data['valor'] ?? 0);
        if ($valor <= 0) {
            $errors[] = 'Valor deve ser maior que zero.';
        }

        $contaId = (int) ($data['conta_id'] ?? 0);
        if ($contaId <= 0) {
            $errors[] = 'Conta é obrigatória.';
        } else {
            $conta = (new Conta())->find($contaId);
            if ($conta === null) {
                $errors[] = 'Conta informada não existe.';
            } elseif ((int) ($conta['ativo'] ?? 0) !== 1) {
                $errors[] = 'Conta informada está inativa.';
            }
        }

        $planoId = (int) ($data['plano_conta_id'] ?? 0);
        if ($planoId <= 0) {
            $errors[] = 'Plano de contas é obrigatório.';
        } else {
            $plano = (new PlanoContas())->find($planoId);
            if ($plano === null) {
                $errors[] = 'Plano de contas informado não existe.';
            } elseif ((int) ($plano['ativo'] ?? 0) !== 1) {
                $errors[] = 'Plano de contas informado está inativo.';
            }
        }

        $descricao = trim((string) ($data['descricao'] ?? ''));
        if ($descricao === '') {
            $errors[] = 'Descrição é obrigatória.';
        }

        $dataLancamento = (string) ($data['data_lancamento'] ?? '');
        if ($dataLancamento === '' || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $dataLancamento)) {
            $errors[] = 'Data de lançamento inválida.';
        }

        // Se editando e o atual é PAGO, tipo/valor não podem mudar.
        if ($atual !== null && ($atual['status'] ?? '') === 'PAGO') {
            if ((float) $atual['valor'] !== $valor) {
                $errors[] = 'Valor não pode ser alterado em lançamento PAGO. Estorne primeiro.';
            }
            if ((string) $atual['tipo'] !== $tipo) {
                $errors[] = 'Tipo não pode ser alterado em lançamento PAGO. Estorne primeiro.';
            }
        }

        return count($errors) === 0;
    }

    /**
     * Prepara array para persistência (remove campos inexistentes na tabela).
     *
     * @param array<string,mixed> $data Dados normalizados.
     *
     * @return array<string,mixed>
     */
    private function prepareForPersistence(array $data): array
    {
        $allowed = [
            'conta_id', 'plano_conta_id', 'cliente_fornecedor_id', 'tipo',
            'valor', 'data_lancamento', 'data_vencimento', 'data_pagamento',
            'descricao', 'observacao', 'status', 'forma_pagamento',
            'documento', 'criado_por',
        ];
        $out = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $out[$k] = $data[$k];
            }
        }
        return $out;
    }
}
