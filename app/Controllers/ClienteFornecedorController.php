<?php
/**
 * @file    ClienteFornecedorController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Clientes / Fornecedores / Ambos.
 *
 * Rotas:
 *   GET  /clientes-fornecedores                  → index() — listar com filtros
 *   POST /clientes-fornecedores                  → index() delega novo() — criar
 *   GET  /clientes-fornecedores/novo             → novo() — form
 *   GET  /clientes-fornecedores/{id}/editar      → editar($id) — form
 *   POST /clientes-fornecedores/{id}             → index($id) delega editar() — atualizar (_method=PUT)
 *   POST /clientes-fornecedores/{id}/desativar   → desativar($id)
 *
 * Valida CPF/CNPJ conforme tipo_pessoa.
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

class ClienteFornecedorController
{
    /**
     * GET  /clientes-fornecedores — listar.
     * POST /clientes-fornecedores — criar (delega para novo()).
     * POST /clientes-fornecedores/{id} — atualizar (delega para editar()).
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
            Response::redirect('index.php?route=clientes-fornecedores/' . urlencode($id) . '/editar');
            return;
        }

        Auth::require('clientes_fornecedores', 'read');

        $filtros = [
            'tipo' => (string) (Request::get('tipo') ?? ''),
            'nome' => trim((string) (Request::get('nome') ?? '')),
        ];

        $cfModel = new ClienteFornecedor();
        $clientesFornecedores = $cfModel->all(['nome_razao_social' => 'ASC']);

        // Filtros em memória.
        if ($filtros['tipo'] !== '') {
            $t = $filtros['tipo'];
            $clientesFornecedores = array_values(array_filter(
                $clientesFornecedores,
                static fn (array $cf): bool => ($cf['tipo'] ?? '') === $t || ($cf['tipo'] ?? '') === 'AMBOS'
            ));
        }
        if ($filtros['nome'] !== '') {
            $n = mb_strtolower($filtros['nome']);
            $clientesFornecedores = array_values(array_filter(
                $clientesFornecedores,
                static function (array $cf) use ($n): bool {
                    $nome = mb_strtolower((string) ($cf['nome_razao_social'] ?? ''));
                    $doc  = mb_strtolower((string) ($cf['cpf_cnpj'] ?? ''));
                    return $nome !== '' && (str_contains($nome, $n) || str_contains($doc, $n));
                }
            ));
        }

        Response::view('clientes_fornecedores/index', [
            'pageTitle'           => 'Clientes / Fornecedores',
            'clientesFornecedores' => $clientesFornecedores,
            'filtros'             => $filtros,
        ]);
    }

    /**
     * GET  /clientes-fornecedores/novo — form.
     * POST /clientes-fornecedores      — valida e cria.
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('clientes_fornecedores', 'create');

        if (!Request::isPost()) {
            Response::view('clientes_fornecedores/create', [
                'pageTitle' => 'Novo Cliente / Fornecedor',
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=clientes-fornecedores/novo');
        }

        $data = $this->collectFromRequest();

        if (!$this->validate($data, null, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('clientes_fornecedores/create', [
                'pageTitle' => 'Novo Cliente / Fornecedor',
                'old'       => $data,
            ]);
            return;
        }

        try {
            (new ClienteFornecedor())->create($data);
            Flash::success('Cliente / Fornecedor criado com sucesso.');
            Response::redirect('index.php?route=clientes-fornecedores');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar cliente/fornecedor: ' . $e->getMessage());
            Flash::error('Erro ao criar cliente/fornecedor: ' . $e->getMessage());
            Response::view('clientes_fornecedores/create', [
                'pageTitle' => 'Novo Cliente / Fornecedor',
                'old'       => $data,
            ]);
        }
    }

    /**
     * GET  /clientes-fornecedores/{id}/editar — form.
     * POST /clientes-fornecedores/{id}        — valida e atualiza.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        Auth::require('clientes_fornecedores', 'update');

        $cfModel = new ClienteFornecedor();
        $clienteFornecedor = $cfModel->find((int) $id);

        if ($clienteFornecedor === null) {
            Flash::error('Cliente / Fornecedor não encontrado.');
            Response::redirect('index.php?route=clientes-fornecedores');
        }

        if (!Request::isPost()) {
            Response::view('clientes_fornecedores/edit', [
                'pageTitle'           => 'Editar Cliente / Fornecedor',
                'clienteFornecedor'  => $clienteFornecedor,
                'old'                 => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=clientes-fornecedores/' . $id . '/editar');
        }

        $data = $this->collectFromRequest();

        if (!$this->validate($data, $clienteFornecedor, $errors)) {
            Flash::error(implode(' ', $errors));
            Response::view('clientes_fornecedores/edit', [
                'pageTitle'           => 'Editar Cliente / Fornecedor',
                'clienteFornecedor'  => $clienteFornecedor,
                'old'                 => $data,
            ]);
            return;
        }

        $update = $this->prepareForPersistence($data);

        try {
            $cfModel->update((int) $id, $update);
            Flash::success('Cliente / Fornecedor atualizado com sucesso.');
            Response::redirect('index.php?route=clientes-fornecedores');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar cliente/fornecedor: ' . $e->getMessage());
            Flash::error('Erro ao atualizar cliente/fornecedor: ' . $e->getMessage());
            Response::view('clientes_fornecedores/edit', [
                'pageTitle'           => 'Editar Cliente / Fornecedor',
                'clienteFornecedor'  => $clienteFornecedor,
                'old'                 => $data,
            ]);
        }
    }

    /**
     * POST /clientes-fornecedores/{id}/desativar — soft-delete.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function desativar(string $id): void
    {
        Auth::require('clientes_fornecedores', 'delete');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=clientes-fornecedores');
        }

        try {
            (new ClienteFornecedor())->softDelete((int) $id);
            Flash::success('Cliente / Fornecedor desativado com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao desativar cliente/fornecedor: ' . $e->getMessage());
            Flash::error('Erro ao desativar cliente/fornecedor.');
        }
        Response::redirect('index.php?route=clientes-fornecedores');
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
        $tipoPessoa = (string) Request::post('tipo_pessoa', 'FISICA');
        $cpfCnpjRaw = (string) Request::post('cpf_cnpj', '');
        $cpfCnpj = $tipoPessoa === 'FISICA' ? Sanitizer::cpf($cpfCnpjRaw) : Sanitizer::cnpj($cpfCnpjRaw);

        return [
            'tipo'               => (string) Request::post('tipo', ''),
            'tipo_pessoa'        => $tipoPessoa,
            'cpf_cnpj'           => $cpfCnpj,
            'nome_razao_social'  => trim((string) Request::post('nome_razao_social', '')),
            'email'              => trim((string) Request::post('email', '')) ?: null,
            'telefone'           => trim((string) Request::post('telefone', '')) ?: null,
            'celular'            => trim((string) Request::post('celular', '')) ?: null,
            'cep'                => Sanitizer::cep((string) Request::post('cep', '')) ?: null,
            'endereco'           => trim((string) Request::post('endereco', '')) ?: null,
            'numero'             => trim((string) Request::post('numero', '')) ?: null,
            'complemento'        => trim((string) Request::post('complemento', '')) ?: null,
            'bairro'             => trim((string) Request::post('bairro', '')) ?: null,
            'cidade'             => trim((string) Request::post('cidade', '')) ?: null,
            'uf'                 => trim((string) Request::post('uf', '')) ?: null,
            'observacao'         => trim((string) Request::post('observacao', '')) ?: null,
            'ativo'              => Request::post('ativo') === '1' ? 1 : 0,
        ];
    }

    /**
     * Valida cliente/fornecedor.
     *
     * @param array<string,mixed>     $data    Dados.
     * @param array<string,mixed>|null $atual  Atual.
     * @param list<string>            $errors  Saída de erros.
     *
     * @return bool
     */
    private function validate(array $data, ?array $atual, array &$errors): bool
    {
        $errors = [];

        if (!in_array((string) ($data['tipo'] ?? ''), ['CLIENTE', 'FORNECEDOR', 'AMBOS'], true)) {
            $errors[] = 'Tipo é obrigatório (Cliente, Fornecedor ou Ambos).';
        }
        if (!in_array((string) ($data['tipo_pessoa'] ?? ''), ['FISICA', 'JURIDICA'], true)) {
            $errors[] = 'Tipo de pessoa inválido.';
        }
        if (trim((string) ($data['nome_razao_social'] ?? '')) === '') {
            $errors[] = 'Nome / Razão Social é obrigatório.';
        }

        $doc = (string) ($data['cpf_cnpj'] ?? '');
        if ($doc === '') {
            $errors[] = 'CPF / CNPJ é obrigatório.';
        } else {
            if ($data['tipo_pessoa'] === 'FISICA') {
                if (!Validator::cpf($doc)) {
                    $errors[] = 'CPF inválido.';
                }
            } else {
                if (!Validator::cnpj($doc)) {
                    $errors[] = 'CNPJ inválido.';
                }
            }
        }

        // Valida email se informado.
        $email = trim((string) ($data['email'] ?? ''));
        if ($email !== '' && !Validator::email($email)) {
            $errors[] = 'Email inválido.';
        }

        // Valida UF.
        $uf = (string) ($data['uf'] ?? '');
        if ($uf !== '' && !in_array($uf, ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'], true)) {
            $errors[] = 'UF inválida.';
        }

        // Unicidade do CPF/CNPJ (exceto para o próprio registro em edição).
        if ($doc !== '') {
            $v = (new Validator(['cpf_cnpj' => $doc]))
                ->rule('cpf_cnpj', 'unique', ['table' => 'clientes_fornecedores', 'column' => 'cpf_cnpj', 'except' => $atual !== null ? (int) ($atual['id'] ?? 0) : 0]);
            if (!$v->validate()) {
                $errors[] = 'CPF/CNPJ já cadastrado para outro cliente/fornecedor.';
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
            'tipo', 'tipo_pessoa', 'cpf_cnpj', 'nome_razao_social',
            'email', 'telefone', 'celular', 'cep', 'endereco', 'numero',
            'complemento', 'bairro', 'cidade', 'uf', 'observacao', 'ativo',
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
