<?php
/**
 * @file    RelatorioController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Relatórios.
 *
 * Rotas:
 *   GET /relatorios                  → index() — redireciona para /relatorios/fluxo-caixa
 *   GET /relatorios/fluxo-caixa      → fluxoCaixa()
 *   GET /relatorios/dre              → dre()
 *   GET /relatorios/saldos           → saldos()
 *   GET /relatorios/exportar-csv     → exportarCsv()
 *
 * Fluxo de Caixa: detalhamento diário com saldo_acumulado.
 * DRE: tree de plano_contas agregando filhos por mês (RECEITA - DESPESA).
 * Saldos: por conta e por plano em período.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Sanitizer;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\PlanoContas;

class RelatorioController
{
    /**
     * GET /relatorios — redireciona para fluxo de caixa.
     *
     * @return void
     */
    public function index(): void
    {
        Auth::require('relatorios', 'read');
        Response::redirect('/relatorios/fluxo-caixa');
    }

    /**
     * GET /relatorios/fluxo-caixa — relatório de fluxo de caixa diário.
     *
     * @return void
     */
    public function fluxoCaixa(): void
    {
        Auth::require('relatorios', 'read');

        $inicioRaw = (string) (Request::get('data_inicio') ?? date('Y-m-01'));
        $fimRaw    = (string) (Request::get('data_fim') ?? date('Y-m-d'));
        $contaId   = (string) (Request::get('conta_id') ?? '');
        $planoId   = (string) (Request::get('plano_conta_id') ?? '');

        $inicio = Sanitizer::date($inicioRaw) ?? date('Y-m-01');
        $fim    = Sanitizer::date($fimRaw) ?? date('Y-m-d');

        $filtros = [
            'data_inicio'    => $inicio,
            'data_fim'       => $fim,
            'conta_id'       => $contaId,
            'plano_conta_id' => $planoId,
        ];

        $contas = (new Conta())->ativas();
        $planos = (new PlanoContas())->ativos();

        $contaIdInt = $contaId !== '' ? (int) $contaId : null;

        $lancamentoModel = new Lancamento();
        $dados = $lancamentoModel->fluxoCaixaDiario($inicio, $fim, $contaIdInt);

        // Filtro por plano_conta_id em memória (opcional, pois fluxoCaixaDiario não filtra por plano).
        if ($planoId !== '') {
            $pid = (int) $planoId;
            // Recarrega por plano (somatório por dia considerando só aquele plano).
            // Estratégia simples: filtra dados via query adicional.
            $dados = $this->filtrarFluxoPorPlano($pid, $inicio, $fim, $contaIdInt);
        }

        // Calcula totais.
        $totais = ['receitas' => 0.0, 'despesas' => 0.0, 'saldo_dia' => 0.0, 'saldo_acumulado' => 0.0];
        foreach ($dados as $row) {
            $totais['receitas'] += (float) $row['receitas'];
            $totais['despesas'] += (float) $row['despesas'];
            $totais['saldo_dia'] += (float) $row['saldo_dia'];
        }
        $totais['saldo_acumulado'] = count($dados) > 0 ? (float) end($dados)['saldo_acumulado'] : 0.0;

        Response::view('relatorios/fluxo_caixa', [
            'pageTitle' => 'Relatório: Fluxo de Caixa',
            'filtros'   => $filtros,
            'contas'    => $contas,
            'planos'    => $planos,
            'dados'     => $dados,
            'totais'    => $totais,
        ]);
    }

    /**
     * GET /relatorios/dre — DRE simplificado por mês.
     *
     * @return void
     */
    public function dre(): void
    {
        Auth::require('relatorios', 'read');

        $mesSelecionado = (string) (Request::get('mes') ?? date('Y-m'));

        // Valida formato YYYY-MM.
        if (!preg_match('#^\d{4}-\d{2}$#', $mesSelecionado)) {
            $mesSelecionado = date('Y-m');
        }

        [$ano, $mes] = explode('-', $mesSelecionado);
        $inicio = sprintf('%04d-%02d-01', (int) $ano, (int) $mes);
        $fim    = date('Y-m-t', strtotime($inicio));

        // Gera lista de meses (12 anteriores + atual).
        $meses = [];
        $nomesMeses = [
            1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
            5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
            9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
        ];
        for ($i = 11; $i >= 0; $i--) {
            $ts = strtotime(sprintf('first day of -%d months', $i));
            $val = date('Y-m', $ts);
            $numMes = (int) date('n', $ts);
            $ano = (int) date('Y', $ts);
            $meses[] = [
                'value' => $val,
                'label' => $nomesMeses[$numMes] . '/' . $ano,
            ];
        }

        // Agrega por plano raiz.
        $planoModel = new PlanoContas();
        $raizes = $planoModel->raizes();

        $linhas = [];
        $totalReceitas = 0.0;
        $totalDespesas = 0.0;

        $linhas[] = ['nivel' => 0, 'descricao' => 'RECEITAS', 'prefixo' => '', 'valor' => 0, 'classe' => 'secao'];

        foreach ($raizes as $r) {
            if (($r['tipo'] ?? '') !== 'RECEITA') {
                continue;
            }
            $total = (new Lancamento())->somaReceitasByPeriodo($inicio, $fim, null, (int) $r['id']);
            // Soma filhos.
            $filhos = $planoModel->filhos((int) $r['id']);
            $subFilhos = 0.0;
            foreach ($filhos as $f) {
                $fv = $this->somaPlanoRecursivo((int) $f['id'], $inicio, $fim, 'RECEITA');
                $subFilhos += $fv;
                $linhas[] = ['nivel' => 1, 'descricao' => $f['nome'], 'prefixo' => $f['codigo'] . ' —', 'valor' => $fv, 'classe' => 'normal'];
            }
            $totalReceitas += $subFilhos;
            $linhas[] = ['nivel' => 0, 'descricao' => $r['nome'], 'prefixo' => $r['codigo'] . ' —', 'valor' => $subFilhos, 'classe' => 'subtotal'];
        }

        // Atualiza total de RECEITAS na primeira linha.
        $linhas[0]['valor'] = $totalReceitas;

        $linhas[] = ['nivel' => 0, 'descricao' => 'DESPESAS', 'prefixo' => '', 'valor' => 0, 'classe' => 'secao'];

        foreach ($raizes as $r) {
            if (($r['tipo'] ?? '') !== 'DESPESA') {
                continue;
            }
            $filhos = $planoModel->filhos((int) $r['id']);
            $subFilhos = 0.0;
            foreach ($filhos as $f) {
                $fv = $this->somaPlanoRecursivo((int) $f['id'], $inicio, $fim, 'DESPESA');
                $subFilhos += $fv;
                $linhas[] = ['nivel' => 1, 'descricao' => $f['nome'], 'prefixo' => $f['codigo'] . ' —', 'valor' => $fv, 'classe' => 'normal'];
            }
            $totalDespesas += $subFilhos;
            $linhas[] = ['nivel' => 0, 'descricao' => $r['nome'], 'prefixo' => $r['codigo'] . ' —', 'valor' => $subFilhos, 'classe' => 'subtotal'];
        }

        // Atualiza total de DESPESAS na linha adequada.
        foreach ($linhas as &$linha) {
            if ($linha['classe'] === 'secao' && $linha['descricao'] === 'DESPESAS') {
                $linha['valor'] = $totalDespesas;
                break;
            }
        }
        unset($linha);

        $resultado = $totalReceitas - $totalDespesas;
        $linhas[] = ['nivel' => 0, 'descricao' => 'RESULTADO LÍQUIDO', 'prefixo' => '=', 'valor' => $resultado, 'classe' => 'resultado'];

        $totais = [
            'receitas' => $totalReceitas,
            'despesas' => $totalDespesas,
            'resultado' => $resultado,
        ];

        Response::view('relatorios/dre', [
            'pageTitle'      => 'Relatório: DRE Simplificado',
            'mesSelecionado' => $mesSelecionado,
            'meses'          => $meses,
            'linhas'         => $linhas,
            'totais'         => $totais,
        ]);
    }

    /**
     * GET /relatorios/saldos — saldos por conta e por plano em período.
     *
     * @return void
     */
    public function saldos(): void
    {
        Auth::require('relatorios', 'read');

        $inicioRaw = (string) (Request::get('data_inicio') ?? date('Y-m-01'));
        $fimRaw    = (string) (Request::get('data_fim') ?? date('Y-m-d'));

        $inicio = Sanitizer::date($inicioRaw) ?? date('Y-m-01');
        $fim    = Sanitizer::date($fimRaw) ?? date('Y-m-d');

        $filtros = ['data_inicio' => $inicio, 'data_fim' => $fim];

        // Saldos por conta.
        $contaModel = new Conta();
        $contas = $contaModel->all(['nome' => 'ASC']);
        $porConta = [];
        foreach ($contas as $c) {
            $lancamentoModel = new Lancamento();
            $receitas = $lancamentoModel->somaReceitasByPeriodo($inicio, $fim, (int) $c['id'], null);
            $despesas = $lancamentoModel->somaDespesasByPeriodo($inicio, $fim, (int) $c['id'], null);
            $saldoInicial = (float) ($c['saldo_inicial'] ?? 0);
            $porConta[] = [
                'conta'         => (string) ($c['nome'] ?? ''),
                'saldo_inicial' => $saldoInicial,
                'receitas'      => $receitas,
                'despesas'      => $despesas,
                'saldo_atual'   => $saldoInicial + $receitas - $despesas,
            ];
        }

        // Saldos por plano.
        $planoModel = new PlanoContas();
        $planosFlat = $planoModel->ativos();
        $porPlano = [];
        foreach ($planosFlat as $p) {
            $lancamentoModel = new Lancamento();
            $tipo = (string) ($p['tipo'] ?? 'NEUTRO');
            $valor = 0.0;
            if ($tipo === 'RECEITA') {
                $valor = $lancamentoModel->somaReceitasByPeriodo($inicio, $fim, null, (int) $p['id']);
            } elseif ($tipo === 'DESPESA') {
                $valor = $lancamentoModel->somaDespesasByPeriodo($inicio, $fim, null, (int) $p['id']);
            }
            $porPlano[] = [
                'codigo'    => (string) ($p['codigo'] ?? ''),
                'nome'      => (string) ($p['nome'] ?? ''),
                'nivel'     => (int) ($p['nivel'] ?? 0),
                'is_grupo'  => $this->ehGrupo($p, $planosFlat),
                'receitas'  => $tipo === 'RECEITA' ? $valor : 0.0,
                'despesas'  => $tipo === 'DESPESA' ? $valor : 0.0,
                'saldo'     => $tipo === 'RECEITA' ? $valor : ($tipo === 'DESPESA' ? -$valor : 0.0),
            ];
        }

        Response::view('relatorios/saldos', [
            'pageTitle' => 'Relatório: Saldos por Conta e Plano',
            'filtros'   => $filtros,
            'porConta'  => $porConta,
            'porPlano'  => $porPlano,
        ]);
    }

    /**
     * GET /relatorios/exportar-csv — exporta relatório conforme ?tipo.
     *
     * @return void
     */
    public function exportarCsv(): void
    {
        Auth::require('relatorios', 'read');

        $tipo = (string) (Request::get('tipo') ?? 'fluxo');

        $fileName = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.csv';

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");

        switch ($tipo) {
            case 'dre':
                $this->writeDreCsv($out);
                break;
            case 'saldos':
                $this->writeSaldosCsv($out);
                break;
            case 'fluxo':
            default:
                $this->writeFluxoCsv($out);
                break;
        }

        fclose($out);
        exit;
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Soma receitas ou despesas de um plano e seus descendentes recursivamente.
     *
     * @param int    $planoId ID do plano.
     * @param string $inicio  YYYY-MM-DD.
     * @param string $fim     YYYY-MM-DD.
     * @param string $tipo    RECEITA | DESPESA.
     *
     * @return float
     */
    private function somaPlanoRecursivo(int $planoId, string $inicio, string $fim, string $tipo): float
    {
        $lancamentoModel = new Lancamento();
        $valor = $tipo === 'RECEITA'
            ? $lancamentoModel->somaReceitasByPeriodo($inicio, $fim, null, $planoId)
            : $lancamentoModel->somaDespesasByPeriodo($inicio, $fim, null, $planoId);

        $filhos = (new PlanoContas())->filhos($planoId);
        foreach ($filhos as $f) {
            $valor += $this->somaPlanoRecursivo((int) $f['id'], $inicio, $fim, $tipo);
        }

        return $valor;
    }

    /**
     * Filtra fluxo de caixa por plano (somatório diário dos lançamentos daquele plano).
     *
     * @param int       $planoId ID do plano.
     * @param string    $inicio  YYYY-MM-DD.
     * @param string    $fim     YYYY-MM-DD.
     * @param int|null  $contaId Filtro de conta (opcional).
     *
     * @return list<array<string,mixed>>
     */
    private function filtrarFluxoPorPlano(int $planoId, string $inicio, string $fim, ?int $contaId): array
    {
        // Implementação simplificada: reusa byPeriodo e agrega por data_pagamento.
        $filtros = ['plano_conta_id' => $planoId, 'status' => 'PAGO'];
        if ($contaId !== null) {
            $filtros['conta_id'] = $contaId;
        }
        $lancs = (new Lancamento())->byPeriodo($inicio, $fim, $filtros);
        $porDia = [];
        foreach ($lancs as $l) {
            $data = (string) ($l['data_pagamento'] ?? $l['data_lancamento'] ?? '');
            if ($data === '') {
                continue;
            }
            if (!isset($porDia[$data])) {
                $porDia[$data] = ['data' => $data, 'receitas' => 0.0, 'despesas' => 0.0];
            }
            $tipo = (string) ($l['tipo'] ?? '');
            $valor = (float) ($l['valor'] ?? 0);
            if ($tipo === 'RECEITA') {
                $porDia[$data]['receitas'] += $valor;
            } elseif ($tipo === 'DESPESA') {
                $porDia[$data]['despesas'] += $valor;
            }
        }
        ksort($porDia);
        $out = [];
        $acumulado = 0.0;
        foreach ($porDia as $row) {
            $dia = $row['receitas'] - $row['despesas'];
            $acumulado += $dia;
            $out[] = [
                'data'            => $row['data'],
                'receitas'        => $row['receitas'],
                'despesas'        => $row['despesas'],
                'saldo_dia'       => $dia,
                'saldo_acumulado' => $acumulado,
            ];
        }
        return $out;
    }

    /**
     * Verifica se um plano é "grupo" (tem filhos).
     *
     * @param array<string,mixed>     $plano       Plano atual.
     * @param list<array<string,mixed>> $todosPlanos Lista completa.
     *
     * @return bool
     */
    private function ehGrupo(array $plano, array $todosPlanos): bool
    {
        $id = (int) ($plano['id'] ?? 0);
        foreach ($todosPlanos as $p) {
            if ((int) ($p['parent_id'] ?? 0) === $id) {
                return true;
            }
        }
        return false;
    }

    /**
     * Escreve CSV do fluxo de caixa.
     *
     * @param resource $out Stream de saída.
     *
     * @return void
     */
    private function writeFluxoCsv($out): void
    {
        $inicio = Sanitizer::date((string) (Request::get('data_inicio') ?? date('Y-m-01'))) ?? date('Y-m-01');
        $fim    = Sanitizer::date((string) (Request::get('data_fim') ?? date('Y-m-d'))) ?? date('Y-m-d');
        $contaId = (string) (Request::get('conta_id') ?? '');
        $contaIdInt = $contaId !== '' ? (int) $contaId : null;

        fputcsv($out, ['Data', 'Receitas', 'Despesas', 'Saldo do Dia', 'Saldo Acumulado'], ';');

        $dados = (new Lancamento())->fluxoCaixaDiario($inicio, $fim, $contaIdInt);
        foreach ($dados as $row) {
            fputcsv($out, [
                (string) ($row['data'] ?? ''),
                number_format((float) ($row['receitas'] ?? 0), 2, ',', '.'),
                number_format((float) ($row['despesas'] ?? 0), 2, ',', '.'),
                number_format((float) ($row['saldo_dia'] ?? 0), 2, ',', '.'),
                number_format((float) ($row['saldo_acumulado'] ?? 0), 2, ',', '.'),
            ], ';');
        }
    }

    /**
     * Escreve CSV do DRE.
     *
     * @param resource $out Stream de saída.
     *
     * @return void
     */
    private function writeDreCsv($out): void
    {
        $mes = (string) (Request::get('mes') ?? date('Y-m'));
        if (!preg_match('#^\d{4}-\d{2}$#', $mes)) {
            $mes = date('Y-m');
        }
        [$ano, $mesNum] = explode('-', $mes);
        $inicio = sprintf('%04d-%02d-01', (int) $ano, (int) $mesNum);
        $fim    = date('Y-m-t', strtotime($inicio));

        fputcsv($out, ['Descrição', 'Valor'], ';');

        $planoModel = new PlanoContas();
        foreach ($planoModel->raizes() as $r) {
            $tipo = (string) ($r['tipo'] ?? 'NEUTRO');
            $filhos = $planoModel->filhos((int) $r['id']);
            foreach ($filhos as $f) {
                $valor = $this->somaPlanoRecursivo((int) $f['id'], $inicio, $fim, $tipo);
                if ($tipo === 'DESPESA') {
                    $valor = -$valor;
                }
                fputcsv($out, [
                    ($r['codigo'] ?? '') . ' — ' . ($r['nome'] ?? '') . ' :: ' . ($f['codigo'] ?? '') . ' ' . ($f['nome'] ?? ''),
                    number_format($valor, 2, ',', '.'),
                ], ';');
            }
        }
    }

    /**
     * Escreve CSV de saldos por conta e por plano.
     *
     * @param resource $out Stream de saída.
     *
     * @return void
     */
    private function writeSaldosCsv($out): void
    {
        $inicio = Sanitizer::date((string) (Request::get('data_inicio') ?? date('Y-m-01'))) ?? date('Y-m-01');
        $fim    = Sanitizer::date((string) (Request::get('data_fim') ?? date('Y-m-d'))) ?? date('Y-m-d');

        fputcsv($out, ['Tipo', 'Nome', 'Saldo Inicial', 'Receitas', 'Despesas', 'Saldo Atual'], ';');

        $contaModel = new Conta();
        $lancamentoModel = new Lancamento();
        foreach ($contaModel->all(['nome' => 'ASC']) as $c) {
            $cid = (int) $c['id'];
            $rec = $lancamentoModel->somaReceitasByPeriodo($inicio, $fim, $cid, null);
            $des = $lancamentoModel->somaDespesasByPeriodo($inicio, $fim, $cid, null);
            $si  = (float) ($c['saldo_inicial'] ?? 0);
            fputcsv($out, [
                'Conta',
                (string) ($c['nome'] ?? ''),
                number_format($si, 2, ',', '.'),
                number_format($rec, 2, ',', '.'),
                number_format($des, 2, ',', '.'),
                number_format($si + $rec - $des, 2, ',', '.'),
            ], ';');
        }
    }
}
