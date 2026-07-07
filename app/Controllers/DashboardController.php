<?php
/**
 * @file    DashboardController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller do Dashboard — tela inicial pós-login.
 * Gera contas programadas pendentes no boot e exibe widgets:
 *  - Contas ativas com saldo atual
 *  - Saldo geral consolidado
 *  - Fluxo de caixa dos últimos 6 meses (gráfico)
 *  - Próximas contas a vencer (30 dias)
 *  - Últimos 10 lançamentos
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Models\Conta;
use App\Models\ContaProgramada;
use App\Models\Lancamento;

class DashboardController
{
    /**
     * Renderiza o dashboard principal.
     *
     * @return void
     */
    public function index(): void
    {
        Auth::require();

        // Boot: gera lançamentos pendentes de contas programadas vencidas.
        $cpModel = new ContaProgramada();
        try {
            $cpModel->gerarPendentes();
        } catch (\Throwable $e) {
            // Falha não bloqueia o dashboard — apenas loga.
            \App\Core\Logger::error('Falha ao gerar pendentes no boot do dashboard: ' . $e->getMessage());
        }

        $contaModel = new Conta();
        $lancamentoModel = new Lancamento();

        // Contas ativas com saldo_atual.
        $contas = $contaModel->ativas();
        foreach ($contas as &$c) {
            $c['saldo_atual'] = $contaModel->saldoAtual((int) $c['id']);
        }
        unset($c);

        $saldoGeral = $contaModel->saldoGeral();
        $totaisByMes = $lancamentoModel->totaisByMes(6);
        $proximasVencimentos = $cpModel->proximasVencimentos(30, 20);
        $ultimosLancamentos = $lancamentoModel->ultimos(10);

        Response::view('dashboard/index', [
            'pageTitle'           => 'Dashboard',
            'contas'              => $contas,
            'saldoGeral'          => $saldoGeral,
            'totaisByMes'         => $totaisByMes,
            'proximasVencimentos' => $proximasVencimentos,
            'ultimosLancamentos'  => $ultimosLancamentos,
        ]);
    }
}
