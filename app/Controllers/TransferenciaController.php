<?php
/**
 * @file    TransferenciaController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Transferências entre contas.
 *
 * Rotas:
 *   GET  /transferencias      → index() — redireciona para /transferencias/novo
 *   GET  /transferencias/novo → novo() — form
 *   POST /transferencias      → novo() — valida e cria com lançamentos em transação
 *
 * Regras:
 *   - Contas de origem e destino devem ser diferentes.
 *   - Ambas devem estar ativas.
 *   - Valor deve ser > 0.
 *   - Cria 2 lançamentos TRANSFERENCIA PAGO via Transferencia::createComLancamentos.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Sanitizer;
use App\Models\Conta;
use App\Models\Transferencia;

class TransferenciaController
{
    /**
     * GET  /transferencias — redireciona para o form de nova transferência.
     * POST /transferencias — delega para novo() (criar).
     *
     * @return void
     */
    public function index(): void
    {
        Auth::require('transferencias', 'read');
        if (Request::isPost()) {
            $this->novo();
            return;
        }
        Response::redirect('/transferencias/novo');
    }

    /**
     * GET  /transferencias/novo — form de transferência.
     * POST /transferencias      — valida e cria.
     *
     * @return void
     */
    public function novo(): void
    {
        Auth::require('transferencias', 'create');

        $contas = (new Conta())->ativas();

        if (!Request::isPost()) {
            Response::view('transferencias/create', [
                'pageTitle' => 'Nova Transferência',
                'contas'    => $contas,
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/transferencias/novo');
        }

        $origemId  = (int) Request::post('conta_origem_id', 0);
        $destinoId = (int) Request::post('conta_destino_id', 0);
        $valor     = Sanitizer::decimal((string) Request::post('valor', '0'));
        $data      = (string) (Sanitizer::date((string) Request::post('data_transferencia', '')) ?? date('Y-m-d'));
        $observ    = trim((string) Request::post('observacao', '')) ?: null;

        $errors = [];
        if ($origemId <= 0) {
            $errors[] = 'Conta de origem é obrigatória.';
        }
        if ($destinoId <= 0) {
            $errors[] = 'Conta de destino é obrigatória.';
        }
        if ($origemId > 0 && $origemId === $destinoId) {
            $errors[] = 'Conta de origem e destino devem ser diferentes.';
        }
        if ($valor <= 0) {
            $errors[] = 'Valor deve ser maior que zero.';
        }
        if ($data === '' || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $data)) {
            $errors[] = 'Data da transferência inválida.';
        }

        // Verifica contas ativas.
        if ($origemId > 0) {
            $origem = (new Conta())->find($origemId);
            if ($origem === null) {
                $errors[] = 'Conta de origem não encontrada.';
            } elseif ((int) ($origem['ativo'] ?? 0) !== 1) {
                $errors[] = 'Conta de origem está inativa.';
            }
        }
        if ($destinoId > 0) {
            $destino = (new Conta())->find($destinoId);
            if ($destino === null) {
                $errors[] = 'Conta de destino não encontrada.';
            } elseif ((int) ($destino['ativo'] ?? 0) !== 1) {
                $errors[] = 'Conta de destino está inativa.';
            }
        }

        if (count($errors) > 0) {
            Flash::error(implode(' ', $errors));
            Response::view('transferencias/create', [
                'pageTitle' => 'Nova Transferência',
                'contas'    => $contas,
                'old'       => [
                    'conta_origem_id'  => $origemId,
                    'conta_destino_id' => $destinoId,
                    'valor'            => Request::post('valor', ''),
                    'data_transferencia' => Request::post('data_transferencia', ''),
                    'observacao'       => Request::post('observacao', ''),
                ],
            ]);
            return;
        }

        try {
            (new Transferencia())->createComLancamentos([
                'conta_origem_id'   => $origemId,
                'conta_destino_id'  => $destinoId,
                'valor'             => $valor,
                'data_transferencia' => $data,
                'observacao'        => $observ,
            ]);
            Flash::success('Transferência realizada com sucesso.');
            Response::redirect('/dashboard');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao realizar transferência: ' . $e->getMessage());
            Flash::error('Erro ao realizar transferência: ' . $e->getMessage());
            Response::view('transferencias/create', [
                'pageTitle' => 'Nova Transferência',
                'contas'    => $contas,
                'old'       => [
                    'conta_origem_id'  => $origemId,
                    'conta_destino_id' => $destinoId,
                    'valor'            => Request::post('valor', ''),
                    'data_transferencia' => Request::post('data_transferencia', ''),
                    'observacao'       => Request::post('observacao', ''),
                ],
            ]);
        }
    }
}
