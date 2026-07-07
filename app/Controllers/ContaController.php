<?php
/**
 * @file    ContaController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Contas (bancárias / caixa / Asaas / carteira / outro).
 *
 * Rotas (alinhadas com T05/T06 views):
 *   GET  /contas               → index()              — listar
 *   POST /contas               → index() delega novo()— criar
 *   GET  /contas/novo          → novo()               — form
 *   GET  /contas/{id}/editar   → editar($id)          — form
 *   POST /contas/{id}          → index($id) delega editar() — atualizar (_method=PUT)
 *   GET  /contas/{id}/extrato  → extrato($id)
 *   POST /contas/{id}/desativar→ desativar($id)
 *   POST /contas/{id}/ativar   → ativar($id)
 *
 * Regra de negócio: saldo_inicial não pode ser alterado após criação.
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
use App\Models\Conta;

class ContaController
{
    /**
     * GET  /contas        — listar contas (com filtros tipo/status).
     * POST /contas        — criar nova conta (delega para novo()).
     * POST /contas/{id}   — atualizar conta (delega para editar(), _method=PUT).
     *
     * @param string $id ID opcional (quando POST /contas/{id}).
     *
     * @return void
     */
    public function index(string $id = ''): void
    {
        // POST sem id → criar
        if (Request::isPost() && $id === '') {
            $this->novo();
            return;
        }
        // POST com id → atualizar
        if (Request::isPost() && $id !== '') {
            $this->editar($id);
            return;
        }
        // GET com id → redireciona para edit form
        if ($id !== '') {
            Response::redirect('index.php?route=contas/' . urlencode($id) . '/editar');
            return;
        }

        // GET sem id → listar
        Auth::require('contas', 'read');

        $filtros = [
            'tipo'   => (string) (Request::get('tipo') ?? ''),
            'status' => (string) (Request::get('status') ?? ''),
        ];

        $contaModel = new Conta();
        $contas = $contaModel->all(['nome' => 'ASC']);

        // Aplica filtros em memória (lista de contas é pequena).
        if ($filtros['tipo'] !== '') {
            $contas = array_values(array_filter($contas, static fn (array $c): bool => ($c['tipo'] ?? '') === $filtros['tipo']));
        }
        if ($filtros['status'] !== '') {
            $ativo = $filtros['status'] === 'ativo' ? 1 : 0;
            $contas = array_values(array_filter($contas, static fn (array $c): bool => (int) ($c['ativo'] ?? 0) === $ativo));
        }

        // Adiciona saldo_atual em cada conta.
        foreach ($contas as &$c) {
            $c['saldo_atual'] = $contaModel->saldoAtual((int) $c['id']);
        }
        unset($c);

        Response::view('contas/index', [
            'pageTitle' => 'Contas',
            'contas'    => $contas,
            'filtros'   => $filtros,
        ]);
    }

    /**
     * GET  /contas/novo — exibe form de criação.
     * POST /contas      — valida e cria conta.
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('contas', 'create');

        if (!Request::isPost()) {
            Response::view('contas/novo', [
                'pageTitle' => 'Nova Conta',
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido. Recarregue a página e tente novamente.');
            Response::redirect('index.php?route=contas/novo');
        }

        $data = [
            'nome'          => trim((string) Request::post('nome', '')),
            'tipo'          => (string) Request::post('tipo', ''),
            'instituicao'   => trim((string) Request::post('instituicao', '')) ?: null,
            'agencia'       => trim((string) Request::post('agencia', '')) ?: null,
            'conta_numero'  => trim((string) Request::post('conta_numero', '')) ?: null,
            'saldo_inicial' => Sanitizer::decimal((string) Request::post('saldo_inicial', '0')),
            'descricao'     => trim((string) Request::post('descricao', '')) ?: null,
            'ativo'         => Request::post('ativo') === '1' ? 1 : 0,
        ];

        $v = (new Validator($data))
            ->rule('nome', 'required')
            ->rule('nome', 'min', 3)
            ->rule('nome', 'max', 100);

        if (!in_array($data['tipo'], ['BANCO', 'CAIXA', 'ASAAS', 'CARTEIRA', 'OUTRO'], true)) {
            Flash::error('Tipo de conta inválido.');
            Response::view('contas/novo', [
                'pageTitle' => 'Nova Conta',
                'old'       => $data,
            ]);
            return;
        }

        if (!$v->validate()) {
            $errors = $v->errors();
            Flash::error(implode(' ', array_map(static fn (array $e): string => $e[0], $errors)));
            Response::view('contas/novo', [
                'pageTitle' => 'Nova Conta',
                'old'       => $data,
            ]);
            return;
        }

        try {
            $contaModel = new Conta();
            $id = $contaModel->create($data);
            Flash::success('Conta criada com sucesso.');
            Response::redirect('index.php?route=contas/' . $id . '/editar');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar conta: ' . $e->getMessage());
            Flash::error('Erro ao criar conta: ' . $e->getMessage());
            Response::view('contas/novo', [
                'pageTitle' => 'Nova Conta',
                'old'       => $data,
            ]);
        }
    }

    /**
     * GET  /contas/{id}/editar — exibe form de edição.
     * POST /contas/{id}        — valida e atualiza (saldo_inicial NÃO deve mudar).
     *
     * @param string $id ID da conta.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        Auth::require('contas', 'update');

        $contaModel = new Conta();
        $conta = $contaModel->find((int) $id);

        if ($conta === null) {
            Flash::error('Conta não encontrada.');
            Response::redirect('index.php?route=contas');
        }

        if (!Request::isPost()) {
            Response::view('contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido. Recarregue a página e tente novamente.');
            Response::redirect('index.php?route=contas/' . $id . '/editar');
        }

        $data = [
            'nome'          => trim((string) Request::post('nome', '')),
            'tipo'          => (string) Request::post('tipo', ''),
            'instituicao'   => trim((string) Request::post('instituicao', '')) ?: null,
            'agencia'       => trim((string) Request::post('agencia', '')) ?: null,
            'conta_numero'  => trim((string) Request::post('conta_numero', '')) ?: null,
            'saldo_inicial' => (float) ($conta['saldo_inicial'] ?? 0), // NÃO deve mudar após criação.
            'descricao'     => trim((string) Request::post('descricao', '')) ?: null,
            'ativo'         => Request::post('ativo') === '1' ? 1 : 0,
        ];

        $v = (new Validator($data))
            ->rule('nome', 'required')
            ->rule('nome', 'min', 3)
            ->rule('nome', 'max', 100);

        if (!in_array($data['tipo'], ['BANCO', 'CAIXA', 'ASAAS', 'CARTEIRA', 'OUTRO'], true)) {
            Flash::error('Tipo de conta inválido.');
            Response::view('contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'old'       => $data,
            ]);
            return;
        }

        if (!$v->validate()) {
            $errors = $v->errors();
            Flash::error(implode(' ', array_map(static fn (array $e): string => $e[0], $errors)));
            Response::view('contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'old'       => $data,
            ]);
            return;
        }

        try {
            $contaModel->update((int) $id, $data);
            Flash::success('Conta atualizada com sucesso.');
            Response::redirect('index.php?route=contas');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar conta: ' . $e->getMessage());
            Flash::error('Erro ao atualizar conta: ' . $e->getMessage());
            Response::view('contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'old'       => $data,
            ]);
        }
    }

    /**
     * POST /contas/{id}/desativar — soft-delete.
     *
     * @param string $id ID da conta.
     *
     * @return void
     */
    public function desativar(string $id): void
    {
        Auth::require('contas', 'delete');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas');
        }

        try {
            (new Conta())->softDelete((int) $id);
            Flash::success('Conta desativada com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao desativar conta: ' . $e->getMessage());
            Flash::error('Erro ao desativar conta.');
        }
        Response::redirect('index.php?route=contas');
    }

    /**
     * POST /contas/{id}/ativar — reativa conta.
     *
     * @param string $id ID da conta.
     *
     * @return void
     */
    public function ativar(string $id): void
    {
        Auth::require('contas', 'update');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=contas');
        }

        try {
            $contaModel = new Conta();
            $contaModel->update((int) $id, ['ativo' => 1]);
            Flash::success('Conta reativada com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao ativar conta: ' . $e->getMessage());
            Flash::error('Erro ao ativar conta.');
        }
        Response::redirect('index.php?route=contas');
    }

    /**
     * GET /contas/{id}/extrato — extrato de lançamentos da conta no período.
     *
     * @param string $id ID da conta.
     *
     * @return void
     */
    public function extrato(string $id): void
    {
        Auth::require('contas', 'read');

        $contaModel = new Conta();
        $conta = $contaModel->find((int) $id);

        if ($conta === null) {
            Flash::error('Conta não encontrada.');
            Response::redirect('index.php?route=contas');
        }

        // Aceita datas em formato ISO (YYYY-MM-DD) ou BR (dd/mm/AAAA) — input type=date envia ISO.
        $inicioRaw = (string) (Request::get('data_inicio') ?? '');
        $fimRaw    = (string) (Request::get('data_fim') ?? '');

        $inicio = $inicioRaw !== '' ? (Sanitizer::date($inicioRaw) ?? $inicioRaw) : null;
        $fim    = $fimRaw !== '' ? (Sanitizer::date($fimRaw) ?? $fimRaw) : null;

        // Defaults: mês atual se nenhum filtro.
        if ($inicio === null && $fim === null) {
            $inicio = date('Y-m-01');
            $fim    = date('Y-m-d');
        }

        $lancamentos = $contaModel->extrato((int) $id, $inicio, $fim);
        $saldoAtual  = $contaModel->saldoAtual((int) $id);

        Response::view('contas/extrato', [
            'pageTitle'  => 'Extrato: ' . ($conta['nome'] ?? ''),
            'conta'      => $conta,
            'lancamentos' => $lancamentos,
            'filtros'    => [
                'data_inicio' => $inicio ?? '',
                'data_fim'    => $fim ?? '',
            ],
            'saldoAtual' => $saldoAtual,
        ]);
    }
}
