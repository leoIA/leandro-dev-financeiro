<?php
/**
 * @file    PlanoContasController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller do Plano de Contas (auto-relacionamento hierárquico).
 *
 * Rotas:
 *   GET  /plano-contas               → index() — árvore
 *   POST /plano-contas               → index() delega novo() — criar
 *   GET  /plano-contas/novo          → novo()  — form (aceita ?parent=X)
 *   GET  /plano-contas/{id}/editar   → editar($id) — form
 *   POST /plano-contas/{id}          → index($id) delega editar() — atualizar (_method=PUT)
 *   POST /plano-contas/{id}/desativar→ desativar($id)
 *   POST /plano-contas/{id}/ativar   → ativar($id)
 *
 * Regra: desativar bloqueia se hasFilhos ou hasLancamentos.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\PlanoContas;

class PlanoContasController
{
    /**
     * GET  /plano-contas — exibe árvore.
     * POST /plano-contas — cria (delega para novo()).
     * POST /plano-contas/{id} — atualiza (delega para editar()).
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
            Response::redirect('index.php?route=plano-contas/' . urlencode($id) . '/editar');
            return;
        }

        Auth::require('plano_contas', 'read');

        $planoModel = new PlanoContas();
        $planoTree = $planoModel->getTree();

        Response::view('plano_contas/index', [
            'pageTitle' => 'Plano de Contas',
            'planoTree' => $planoTree,
        ]);
    }

    /**
     * GET  /plano-contas/novo — form de criação.
     * POST /plano-contas      — valida e cria.
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('plano_contas', 'create');

        $planoModel = new PlanoContas();
        $planoTree = $planoModel->getTree();

        if (!Request::isPost()) {
            Response::view('plano_contas/novo', [
                'pageTitle' => 'Nova Conta',
                'planoTree' => $planoTree,
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=plano-contas/novo');
        }

        $parentId = Request::post('parent_id', '');
        $parentId = ($parentId === '' || $parentId === null) ? null : (int) $parentId;

        // Tipo é herdado do parent se houver.
        $tipo = (string) Request::post('tipo', '');
        if ($parentId !== null) {
            $parent = $planoModel->find($parentId);
            if ($parent !== null) {
                $tipo = (string) ($parent['tipo'] ?? $tipo);
            }
        }

        if (!in_array($tipo, ['RECEITA', 'DESPESA', 'NEUTRO'], true)) {
            Flash::error('Tipo inválido. Selecione Receita, Despesa ou Neutro.');
            Response::view('plano_contas/novo', [
                'pageTitle' => 'Nova Conta',
                'planoTree' => $planoTree,
                'old'       => Request::all(),
            ]);
            return;
        }

        $data = [
            'parent_id' => $parentId,
            'codigo'    => trim((string) Request::post('codigo', '')),
            'nome'      => trim((string) Request::post('nome', '')),
            'tipo'      => $tipo,
            'ativo'     => Request::post('ativo') === '1' ? 1 : 0,
        ];

        $v = (new Validator($data))
            ->rule('codigo', 'required')
            ->rule('codigo', 'max', 20)
            ->rule('nome', 'required')
            ->rule('nome', 'min', 2)
            ->rule('nome', 'max', 100);

        if (!$v->validate()) {
            $errors = $v->errors();
            Flash::error(implode(' ', array_map(static fn (array $e): string => $e[0], $errors)));
            Response::view('plano_contas/novo', [
                'pageTitle' => 'Nova Conta',
                'planoTree' => $planoTree,
                'old'       => $data,
            ]);
            return;
        }

        try {
            $planoModel->create($data);
            Flash::success('Conta do plano criada com sucesso.');
            Response::redirect('index.php?route=plano-contas');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar plano de contas: ' . $e->getMessage());
            Flash::error('Erro ao criar conta do plano: ' . $e->getMessage());
            Response::view('plano_contas/novo', [
                'pageTitle' => 'Nova Conta',
                'planoTree' => $planoTree,
                'old'       => $data,
            ]);
        }
    }

    /**
     * GET  /plano-contas/{id}/editar — form de edição.
     * POST /plano-contas/{id}        — valida e atualiza.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        Auth::require('plano_contas', 'update');

        $planoModel = new PlanoContas();
        $conta = $planoModel->find((int) $id);

        if ($conta === null) {
            Flash::error('Conta do plano não encontrada.');
            Response::redirect('index.php?route=plano-contas');
        }

        $hasFilhos = $planoModel->hasFilhos((int) $id);
        $planoTree = $planoModel->getTree();

        if (!Request::isPost()) {
            Response::view('plano_contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'planoTree' => $planoTree,
                'hasFilhos' => $hasFilhos,
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=plano-contas/' . $id . '/editar');
        }

        // parent_id não pode mudar se tem filhos.
        $currentParentId = $conta['parent_id'] !== null ? (int) $conta['parent_id'] : null;
        if ($hasFilhos) {
            $parentId = $currentParentId;
        } else {
            $parentInput = Request::post('parent_id', '');
            $parentId = ($parentInput === '' || $parentInput === null) ? null : (int) $parentInput;
        }

        // Tipo: se tem pai, herda; senão, usa o que veio do form.
        $tipo = (string) Request::post('tipo', '');
        if ($parentId !== null) {
            $parent = $planoModel->find($parentId);
            if ($parent !== null) {
                $tipo = (string) ($parent['tipo'] ?? $tipo);
            }
        }
        if (!in_array($tipo, ['RECEITA', 'DESPESA', 'NEUTRO'], true)) {
            Flash::error('Tipo inválido.');
            Response::view('plano_contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'planoTree' => $planoTree,
                'hasFilhos' => $hasFilhos,
                'old'       => Request::all(),
            ]);
            return;
        }

        $data = [
            'parent_id' => $parentId,
            'codigo'    => trim((string) Request::post('codigo', '')),
            'nome'      => trim((string) Request::post('nome', '')),
            'tipo'      => $tipo,
            'ativo'     => Request::post('ativo') === '1' ? 1 : 0,
        ];

        $v = (new Validator($data))
            ->rule('codigo', 'required')
            ->rule('codigo', 'max', 20)
            ->rule('nome', 'required')
            ->rule('nome', 'min', 2)
            ->rule('nome', 'max', 100);

        if (!$v->validate()) {
            $errors = $v->errors();
            Flash::error(implode(' ', array_map(static fn (array $e): string => $e[0], $errors)));
            Response::view('plano_contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'planoTree' => $planoTree,
                'hasFilhos' => $hasFilhos,
                'old'       => $data,
            ]);
            return;
        }

        try {
            $planoModel->update((int) $id, $data);
            Flash::success('Conta do plano atualizada com sucesso.');
            Response::redirect('index.php?route=plano-contas');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar plano de contas: ' . $e->getMessage());
            Flash::error('Erro ao atualizar conta: ' . $e->getMessage());
            Response::view('plano_contas/editar', [
                'pageTitle' => 'Editar Conta',
                'conta'     => $conta,
                'planoTree' => $planoTree,
                'hasFilhos' => $hasFilhos,
                'old'       => $data,
            ]);
        }
    }

    /**
     * POST /plano-contas/{id}/desativar — soft-delete com checagem.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function desativar(string $id): void
    {
        Auth::require('plano_contas', 'delete');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=plano-contas');
        }

        $planoModel = new PlanoContas();
        $idInt = (int) $id;

        if ($planoModel->hasFilhos($idInt)) {
            Flash::error('Não é possível desativar: esta conta possui sub-contas. Desative as sub-contas primeiro.');
            Response::redirect('index.php?route=plano-contas');
        }
        if ($planoModel->hasLancamentos($idInt)) {
            Flash::error('Não é possível desativar: existem lançamentos vinculados a esta conta do plano.');
            Response::redirect('index.php?route=plano-contas');
        }

        try {
            $planoModel->softDelete($idInt);
            Flash::success('Conta do plano desativada com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao desativar plano: ' . $e->getMessage());
            Flash::error('Erro ao desativar conta.');
        }
        Response::redirect('index.php?route=plano-contas');
    }

    /**
     * POST /plano-contas/{id}/ativar — reativa.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function ativar(string $id): void
    {
        Auth::require('plano_contas', 'update');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=plano-contas');
        }

        try {
            (new PlanoContas())->update((int) $id, ['ativo' => 1]);
            Flash::success('Conta do plano reativada com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao ativar plano: ' . $e->getMessage());
            Flash::error('Erro ao ativar conta.');
        }
        Response::redirect('index.php?route=plano-contas');
    }
}
