<?php
/**
 * @file    ContaProgramadaController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Contas Programadas (recorrências).
 *
 * Rotas:
 *   GET  /contas-programadas                  → index() — listar
 *   POST /contas-programadas                  → index() delega novo() — criar
 *   GET  /contas-programadas/novo             → novo() — form
 *   GET  /contas-programadas/{id}/editar      → editar($id) — form
 *   POST /contas-programadas/{id}             → index($id) delega editar() — atualizar (_method=PUT)
 *   POST /contas-programadas/{id}/desativar   → desativar($id)
 *   GET  /contas-programadas/gerar-pendentes  → gerarPendentes()
 *
 * Regra: se já gerou parcelas, tipo não pode ser alterado (bloqueio view-side).
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
use App\Models\ContaProgramada;
use App\Models\PlanoContas;

class ContaProgramadaController
{
    /**
     * GET  /contas-programadas — listar.
     * POST /contas-programadas — criar (delega para novo()).
     * POST /contas-programadas/{id} — atualizar (delega para editar()).
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
            Response::redirect('index.php?route=contas-programadas/' . urlencode($id) . '/editar');
            return;
        }

        Auth::require('contas_programadas', 'read');

        $cpModel = new ContaProgramada();
        $programadas = $cpModel->ativas();

        Response::view('contas_programadas/index', [
            'pageTitle'   => 'Contas Programadas',
            'programadas' => $programadas,
        ]);
    }

    /**
     * GET  /contas-programadas/novo — form.
     * POST /contas-programadas      — valida e cria (set proxima_geracao=data_inicio).
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('contas_programadas', 'create');

        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();
        $clientesFornecedores = (new ClienteFornecedor())->ativos();

        if (!Request::isPost()) {
            Response::view('contas_programadas/create', [
                'pageTitle'           => 'Nova Programação',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas-programadas/novo');
        }

        $data = $this->collectFromRequest();

        if (!$this->validate($data, null, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('contas_programadas/create', [
                'pageTitle'           => 'Nova Programação',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
            return;
        }

        try {
            (new ContaProgramada())->create($data);
            Flash::success('Programação criada com sucesso.');
            Response::redirect('index.php?route=contas-programadas');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar programação: ' . $e->getMessage());
            Flash::error('Erro ao criar programação: ' . $e->getMessage());
            Response::view('contas_programadas/create', [
                'pageTitle'           => 'Nova Programação',
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
        }
    }

    /**
     * GET  /contas-programadas/{id}/editar — form.
     * POST /contas-programadas/{id}        — valida e atualiza.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        Auth::require('contas_programadas', 'update');

        $cpModel = new ContaProgramada();
        $programada = $cpModel->find((int) $id);

        if ($programada === null) {
            Flash::error('Programação não encontrada.');
            Response::redirect('index.php?route=contas-programadas');
        }

        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();
        $clientesFornecedores = (new ClienteFornecedor())->ativos();

        if (!Request::isPost()) {
            Response::view('contas_programadas/edit', [
                'pageTitle'           => 'Editar Programação',
                'programada'          => $programada,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas-programadas/' . $id . '/editar');
        }

        $data = $this->collectFromRequest();

        // Se já gerou parcelas, tipo não pode mudar.
        $parcelasGeradas = (int) ($programada['parcelas_geradas'] ?? 0);
        if ($parcelasGeradas > 0) {
            $data['tipo'] = (string) ($programada['tipo'] ?? $data['tipo']);
        }

        if (!$this->validate($data, $programada, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('contas_programadas/edit', [
                'pageTitle'           => 'Editar Programação',
                'programada'          => $programada,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
            return;
        }

        $update = $this->prepareForPersistence($data);
        // Não atualizar proxima_geracao automaticamente — mantém o que tem.
        unset($update['proxima_geracao'], $update['parcelas_geradas'], $update['ultima_geracao']);

        try {
            $cpModel->update((int) $id, $update);
            Flash::success('Programação atualizada com sucesso.');
            Response::redirect('index.php?route=contas-programadas');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar programação: ' . $e->getMessage());
            Flash::error('Erro ao atualizar programação: ' . $e->getMessage());
            Response::view('contas_programadas/edit', [
                'pageTitle'           => 'Editar Programação',
                'programada'          => $programada,
                'contas'              => $contas,
                'planos'              => $planos,
                'clientesFornecedores' => $clientesFornecedores,
                'old'                 => $data,
            ]);
        }
    }

    /**
     * POST /contas-programadas/{id}/desativar — soft-delete.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function desativar(string $id): void
    {
        Auth::require('contas_programadas', 'delete');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas-programadas');
        }

        try {
            (new ContaProgramada())->softDelete((int) $id);
            Flash::success('Programação desativada com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao desativar programação: ' . $e->getMessage());
            Flash::error('Erro ao desativar programação.');
        }
        Response::redirect('index.php?route=contas-programadas');
    }

    /**
     * GET/POST /contas-programadas/gerar-pendentes — força geração.
     * A view T06 usa link GET com data-confirm; aceitamos ambos.
     *
     * @return void
     */
    public function gerarPendentes(): void
    {
        Auth::require('contas_programadas', 'create');

        // Tolerante a GET (link na view) — CSRF só em POST.
        if (Request::isPost() && !Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas-programadas');
        }

        try {
            $qtd = (new ContaProgramada())->gerarPendentes();
            if ($qtd > 0) {
                Flash::success("Foram gerados {$qtd} lançamento(s) pendente(s).");
            } else {
                Flash::info('Nenhuma programação vencida para gerar.');
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao gerar pendentes: ' . $e->getMessage());
            Flash::error('Erro ao gerar pendentes: ' . $e->getMessage());
        }
        Response::redirect('index.php?route=contas-programadas');
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Coleta dados do request e normaliza.
     *
     * @return array<string,mixed>
     */
    private function collectFromRequest(): array
    {
        $clienteId = Request::post('cliente_fornecedor_id', '');
        $clienteId = ($clienteId === '' || $clienteId === null) ? null : (int) $clienteId;

        $diaRef = Request::post('dia_referencia', '');
        $diaRef = ($diaRef === '' || $diaRef === null) ? null : (int) $diaRef;

        $parcelasTotal = Request::post('parcelas_total', '');
        $parcelasTotal = ($parcelasTotal === '' || $parcelasTotal === null) ? null : (int) $parcelasTotal;

        $dataTermino = Sanitizer::date((string) Request::post('data_termino', ''));

        return [
            'descricao'              => trim((string) Request::post('descricao', '')),
            'conta_id'               => (int) Request::post('conta_id', 0),
            'plano_conta_id'         => (int) Request::post('plano_conta_id', 0),
            'cliente_fornecedor_id'  => $clienteId,
            'tipo'                   => (string) Request::post('tipo', ''),
            'valor'                  => Sanitizer::decimal((string) Request::post('valor', '0')),
            'data_inicio'            => (string) (Sanitizer::date((string) Request::post('data_inicio', '')) ?? date('Y-m-d')),
            'data_termino'           => $dataTermino,
            'frequencia'             => (string) Request::post('frequencia', 'MENSAL'),
            'dia_referencia'         => $diaRef,
            'parcelas_total'         => $parcelasTotal,
            'forma_pagamento'        => Request::post('forma_pagamento', '') !== '' ? (string) Request::post('forma_pagamento', '') : null,
            'ativo'                  => Request::post('ativo') === '1' ? 1 : 0,
        ];
    }

    /**
     * Valida programação.
     *
     * @param array<string,mixed>     $data    Dados.
     * @param array<string,mixed>|null $atual  Atual (null em criação).
     * @param list<string>            $errors  Saída de erros.
     *
     * @return bool
     */
    private function validate(array $data, ?array $atual, array &$errors): bool
    {
        $errors = [];

        if (trim((string) ($data['descricao'] ?? '')) === '') {
            $errors[] = 'Descrição é obrigatória.';
        }
        if ((int) ($data['conta_id'] ?? 0) <= 0) {
            $errors[] = 'Conta é obrigatória.';
        } else {
            $conta = (new Conta())->find((int) $data['conta_id']);
            if ($conta === null || (int) ($conta['ativo'] ?? 0) !== 1) {
                $errors[] = 'Conta informada não existe ou está inativa.';
            }
        }
        if ((int) ($data['plano_conta_id'] ?? 0) <= 0) {
            $errors[] = 'Plano de contas é obrigatório.';
        } else {
            $plano = (new PlanoContas())->find((int) $data['plano_conta_id']);
            if ($plano === null || (int) ($plano['ativo'] ?? 0) !== 1) {
                $errors[] = 'Plano de contas informado não existe ou está inativo.';
            }
        }
        if (!in_array((string) ($data['tipo'] ?? ''), ['RECEITA', 'DESPESA'], true)) {
            $errors[] = 'Tipo deve ser RECEITA ou DESPESA.';
        }
        if ((float) ($data['valor'] ?? 0) <= 0) {
            $errors[] = 'Valor deve ser maior que zero.';
        }
        if (empty($data['data_inicio'])) {
            $errors[] = 'Data de início é obrigatória.';
        }
        if (!in_array((string) ($data['frequencia'] ?? ''), ['DIARIO', 'SEMANAL', 'MENSAL', 'ANUAL', 'PERSONALIZADO', 'UNICO'], true)) {
            $errors[] = 'Frequência inválida.';
        }
        // Valida data_termino > data_inicio se informado.
        if (!empty($data['data_termino']) && !empty($data['data_inicio'])) {
            if (strtotime($data['data_termino']) < strtotime($data['data_inicio'])) {
                $errors[] = 'Data de término deve ser posterior à data de início.';
            }
        }

        return count($errors) === 0;
    }

    /**
     * Filtra apenas campos persistíveis.
     *
     * @param array<string,mixed> $data Dados.
     *
     * @return array<string,mixed>
     */
    private function prepareForPersistence(array $data): array
    {
        $allowed = [
            'descricao', 'conta_id', 'plano_conta_id', 'cliente_fornecedor_id',
            'tipo', 'valor', 'data_inicio', 'data_termino', 'frequencia',
            'dia_referencia', 'parcelas_total', 'forma_pagamento', 'ativo',
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
