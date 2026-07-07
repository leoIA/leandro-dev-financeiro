<?php
/**
 * @file    NfseController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller do módulo NFSe (Nota Fiscal de Serviço Eletrônica) Bahia.
 *
 * Responsável pelo fluxo completo de emissão, consulta, cancelamento,
 * configuração de município/ambiente e upload de certificado A1 (.pfx).
 *
 * Rotas (registradas em index.php pelo ticket N09):
 *   GET  /nfse                          → index()                 — dashboard NFSe
 *   GET  /nfse/emitir                   → emitir()                — form de emissão
 *   POST /nfse/emitir                   → emitir()                — emite NFSe na prefeitura
 *   GET  /nfse/lista                    → lista()                 — DataTables de NFSes
 *   GET  /nfse/{id}/visualizar          → visualizar($id)         — detalhes + DANFSE
 *   POST /nfse/{id}/cancelar            → cancelar($id)           — cancela NFSe na prefeitura
 *   GET  /nfse/configurar               → configurar()            — form de configuração
 *   POST /nfse/configurar               → configurar()            — salva configuração
 *   GET  /nfse/certificado              → certificado()           — form upload + lista de certificados
 *   POST /nfse/certificado              → certificado()           — processa upload .pfx
 *   POST /nfse/certificado-desativar    → certificadoDesativar()  — desativa certificado
 *   GET  /nfse/consultar/{id}           → consultar($id)          — consulta protocolo na prefeitura
 *
 * Segurança:
 *   - Auth::require('nfse', ...) em TODA action
 *   - Csrf::verify em TODO POST
 *   - Sanitizer para valores decimais, datas, CPF/CNPJ/CEP
 *   - Logger::audit para operações críticas (emitir, cancelar, certificado upload)
 *   - A senha do certificado NUNCA é logada (removed from audit context)
 *   - try/catch em todas as chamadas a adapters (HTTP pode falhar a qualquer momento)
 *
 * @see App\Models\{Nfse, NfseItem, MunicipioNfse, Certificado, Lancamento}
 * @see App\Nfse\Adapters\{WebissAdapter, BethaAdapter, DsfAdapter, SalvadorAdapter}
 * @see App\Nfse\Data\MunicipiosCatalog
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Helpers\Sanitizer;
use App\Models\Certificado;
use App\Models\ClienteFornecedor;
use App\Models\Conta;
use App\Models\Lancamento;
use App\Models\MunicipioNfse;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\PlanoContas;
use App\Nfse\Adapters\BethaAdapter;
use App\Nfse\Adapters\DsfAdapter;
use App\Nfse\Adapters\NfseAdapterInterface;
use App\Nfse\Adapters\SalvadorAdapter;
use App\Nfse\Adapters\WebissAdapter;
use RuntimeException;

class NfseController
{
    // -----------------------------------------------------------------
    // Dashboard.
    // -----------------------------------------------------------------

    /**
     * GET /nfse — dashboard NFSe com estatísticas do mês e status do certificado.
     *
     * @return void
     */
    public function index(): void
    {
        Auth::require('nfse', 'read');

        $nfseModel = new Nfse();
        $certModel = new Certificado();
        $munModel  = new MunicipioNfse();

        // Estatísticas do mês atual.
        $inicioMes = date('Y-m-01') . ' 00:00:00';
        $fimMes    = date('Y-m-t')  . ' 23:59:59';
        $nfsesMes  = $nfseModel->byPeriodo($inicioMes, $fimMes);

        $stats = [
            'total'        => count($nfsesMes),
            'autorizadas'  => 0,
            'rejeitadas'   => 0,
            'canceladas'   => 0,
            'rascunho'     => 0,
            'processando'  => 0,
        ];
        foreach ($nfsesMes as $n) {
            $s = (string) ($n['status'] ?? '');
            $key = match ($s) {
                'AUTORIZADA'  => 'autorizadas',
                'REJEITADA'   => 'rejeitadas',
                'CANCELADA'   => 'canceladas',
                'RASCUNHO'    => 'rascunho',
                'PROCESSANDO' => 'processando',
                default       => null,
            };
            if ($key !== null) {
                $stats[$key]++;
            }
        }

        // Últimas 5 NFSes emitidas.
        $ultimas = $nfseModel->ultimas(5);

        // Status do certificado ativo.
        $certificado = $certModel->ativo();
        $certInfo    = null;
        if ($certificado !== null) {
            $validade  = (string) ($certificado['validade'] ?? '');
            $diasRest  = null;
            $expirado  = false;
            if ($validade !== '') {
                $ts = strtotime($validade);
                if ($ts !== false) {
                    $hoje    = strtotime(date('Y-m-d'));
                    $diasRest = (int) round(($ts - $hoje) / 86400);
                    $expirado = $diasRest < 0;
                }
            }
            $certInfo = [
                'id'           => (int) ($certificado['id'] ?? 0),
                'nome'         => (string) ($certificado['nome'] ?? ''),
                'validade'     => $validade,
                'dias_restant' => $diasRest,
                'expirado'     => $expirado,
                'cnpj_titular' => (string) ($certificado['cnpj_titular'] ?? ''),
            ];
        }

        // Ambiente e município ativos.
        $ambiente     = (string) (Config::get('nfse_ambiente', 'HOMOLOGACAO') ?? 'HOMOLOGACAO');
        $munIbgeAtivo = (string) (Config::get('nfse_municipio_ativo', '') ?? '');
        $municipioAtivo = null;
        if ($munIbgeAtivo !== '') {
            $municipioAtivo = $munModel->byIbge($munIbgeAtivo);
        }

        Response::view('nfse/index', [
            'pageTitle'      => 'NFSe — Dashboard',
            'stats'          => $stats,
            'ultimas'        => $ultimas,
            'certificado'    => $certInfo,
            'ambiente'       => $ambiente,
            'municipioAtivo' => $municipioAtivo,
        ]);
    }

    // -----------------------------------------------------------------
    // Emissão.
    // -----------------------------------------------------------------

    /**
     * GET  /nfse/emitir — exibe form de emissão.
     * POST /nfse/emitir — valida, cria NFSe em RASCUNHO, chama adapter, marca AUTORIZADA/REJEITADA.
     *
     * @return void
     */
    public function emitir(): void
    {
        Auth::require('nfse', 'create');

        $munModel   = new MunicipioNfse();
        $contaModel = new Conta();
        $cfModel    = new ClienteFornecedor();
        $certModel  = new Certificado();
        $nfseModel  = new Nfse();

        // Dados para o form (GET e repostagem em caso de erro).
        $municipios  = $munModel->ativos();
        $contas      = $contaModel->ativas();
        $clientes    = $cfModel->ativos();
        $proxRps     = (int) (Config::get('nfse_proximo_numero_rps', 1) ?? 1);
        $serieRps    = (string) (Config::get('nfse_serie_rps', '1') ?? '1');
        $aliqDefault = (string) (Config::get('nfse_aliquota_default', '0.03') ?? '0.03');
        $servDefault = (string) (Config::get('nfse_servico_codigo_default', '') ?? '');
        $ambiente    = (string) (Config::get('nfse_ambiente', 'HOMOLOGACAO') ?? 'HOMOLOGACAO');

        // GET — apenas exibe o form.
        if (!Request::isPost()) {
            Response::view('nfse/emitir', [
                'pageTitle'        => 'Emitir NFSe',
                'municipios'       => $municipios,
                'contas'           => $contas,
                'clientes'         => $clientes,
                'proximoRps'       => $proxRps,
                'serieRps'         => $serieRps,
                'aliquotaDefault'  => $aliqDefault,
                'servicoDefault'   => $servDefault,
                'ambiente'         => $ambiente,
                'old'              => [],
            ]);
            return;
        }

        // POST — CSRF.
        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido. Recarregue a página e tente novamente.');
            Response::redirect('index.php?route=nfse/emitir');
        }

        // Coleta e sanitiza inputs.
        $ibge       = trim((string) Request::post('municipio_ibge', ''));
        $data       = $this->collectNfseFromRequest();
        $data['ambiente']    = $ambiente;
        $data['serie_rps']   = $serieRps;
        $data['tipo_rps']    = 'RPS';
        $data['criado_por']  = Auth::id();
        $data['data_emissao'] = date('Y-m-d H:i:s');

        // Validador básico.
        $v = (new Validator($data))
            ->rule('tomador_nome', 'required')
            ->rule('tomador_nome', 'min', 3)
            ->rule('tomador_nome', 'max', 200)
            ->rule('servico_codigo', 'required')
            ->rule('discriminacao', 'required')
            ->rule('discriminacao', 'min', 3);

        if (!$v->validate()) {
            $errors = $v->errors();
            Flash::error(implode(' ', array_map(static fn (array $e): string => $e[0], $errors)));
            Response::view('nfse/emitir', [
                'pageTitle'        => 'Emitir NFSe',
                'municipios'       => $municipios,
                'contas'           => $contas,
                'clientes'         => $clientes,
                'proximoRps'       => $proxRps,
                'serieRps'         => $serieRps,
                'aliquotaDefault'  => $aliqDefault,
                'servicoDefault'   => $servDefault,
                'ambiente'         => $ambiente,
                'old'              => $data,
            ]);
            return;
        }

        // Validações de negócio (valores > 0, CPF/CNPJ, município, conta).
        $bizErrors = [];
        if ($data['valor_servicos'] <= 0) {
            $bizErrors[] = 'O valor dos serviços deve ser maior que zero.';
        }
        if ($data['aliquota'] <= 0) {
            $bizErrors[] = 'A alíquota deve ser maior que zero.';
        }
        $doc = (string) ($data['tomador_cnpj_cpf'] ?? '');
        $docDigits = preg_replace('/\D/', '', $doc) ?? '';
        if (strlen($docDigits) !== 11 && strlen($docDigits) !== 14) {
            $bizErrors[] = 'CPF/CNPJ do tomador inválido (deve conter 11 ou 14 dígitos).';
        }
        if ($ibge === '') {
            $bizErrors[] = 'Selecione o município da prestação.';
        }
        if ((int) ($data['conta_id'] ?? 0) <= 0) {
            $bizErrors[] = 'Selecione a conta para crédito da receita.';
        }

        // Busca município por IBGE.
        $municipio = $ibge !== '' ? $munModel->byIbge($ibge) : null;
        if ($municipio === null && $ibge !== '') {
            $bizErrors[] = 'Município não encontrado no catálogo: ' . $ibge;
        }

        // Busca conta.
        $conta = (int) ($data['conta_id'] ?? 0) > 0
            ? $contaModel->find((int) $data['conta_id'])
            : null;
        if ($conta === null && (int) ($data['conta_id'] ?? 0) > 0) {
            $bizErrors[] = 'Conta selecionada não existe.';
        }

        if ($bizErrors !== []) {
            Flash::error(implode(' ', $bizErrors));
            Response::view('nfse/emitir', [
                'pageTitle'        => 'Emitir NFSe',
                'municipios'       => $municipios,
                'contas'           => $contas,
                'clientes'         => $clientes,
                'proximoRps'       => $proxRps,
                'serieRps'         => $serieRps,
                'aliquotaDefault'  => $aliqDefault,
                'servicoDefault'   => $servDefault,
                'ambiente'         => $ambiente,
                'old'              => $data,
            ]);
            return;
        }

        // Busca certificado ativo.
        $certificado = $certModel->ativo();
        if ($certificado === null) {
            Flash::error('Nenhum certificado ativo. Faça upload de um certificado .pfx antes de emitir.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        // Preenche dados do prestador a partir da configuração.
        $data['prestador_cnpj'] = (string) (Config::get('empresa_cnpj', '') ?? '');
        $data['prestador_im']   = (string) (Config::get('nfse_inscricao_municipal', '') ?? '');
        $data['municipio_id']   = (int) ($municipio['id'] ?? 0);

        // Calcula valores ABRASF (base, ISS, líquido).
        $data = $nfseModel->calcularValores($data);

        // Próximo RPS (atômico via SELECT ... FOR UPDATE em configuracoes).
        try {
            $numeroRps = $nfseModel->proximoRps();
        } catch (RuntimeException $e) {
            Logger::error('Falha ao obter próximo RPS: ' . $e->getMessage());
            Flash::error('Falha ao obter próximo número de RPS. Tente novamente.');
            Response::view('nfse/emitir', [
                'pageTitle'        => 'Emitir NFSe',
                'municipios'       => $municipios,
                'contas'           => $contas,
                'clientes'         => $clientes,
                'proximoRps'       => $proxRps,
                'serieRps'         => $serieRps,
                'aliquotaDefault'  => $aliqDefault,
                'servicoDefault'   => $servDefault,
                'ambiente'         => $ambiente,
                'old'              => $data,
            ]);
            return;
        }
        $data['numero_rps'] = $numeroRps;

        // Cria a NFSe em RASCUNHO (status default do schema).
        $data['status'] = 'RASCUNHO';
        try {
            $nfseId = $nfseModel->create($data);
        } catch (RuntimeException $e) {
            Logger::error('Falha ao criar NFSe em RASCUNHO: ' . $e->getMessage());
            Flash::error('Erro ao salvar rascunho da NFSe: ' . $e->getMessage());
            Response::view('nfse/emitir', [
                'pageTitle'        => 'Emitir NFSe',
                'municipios'       => $municipios,
                'contas'           => $contas,
                'clientes'         => $clientes,
                'proximoRps'       => $proxRps,
                'serieRps'         => $serieRps,
                'aliquotaDefault'  => $aliqDefault,
                'servicoDefault'   => $servDefault,
                'ambiente'         => $ambiente,
                'old'              => $data,
            ]);
            return;
        }

        // Monta array completo para o adapter (inclui IBGE e provedor do município).
        $nfseArray = $data;
        $nfseArray['id']              = $nfseId;
        $nfseArray['municipio_ibge']  = (string) ($municipio['codigo_ibge'] ?? '');
        $nfseArray['municipio_provedor'] = (string) ($municipio['provedor'] ?? '');
        $nfseArray['municipio_nome']  = (string) ($municipio['nome'] ?? '');

        // Prepara certificado com senha descriptografada para o adapter.
        $certForAdapter = $certificado;
        try {
            $certForAdapter['senha'] = $certModel->getSenha((int) $certificado['id']);
        } catch (RuntimeException $e) {
            Logger::error('Falha ao descriptografar senha do certificado: ' . $e->getMessage());
            $nfseModel->marcarComoRejeitada($nfseId, 'Falha ao descriptografar senha do certificado.');
            Flash::error('Falha ao descriptografar senha do certificado. Reenvie o certificado.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        // Seleciona adapter conforme provedor.
        try {
            $adapter = $this->getAdapter($municipio, $certForAdapter, $ambiente);
        } catch (RuntimeException $e) {
            $nfseModel->marcarComoRejeitada($nfseId, $e->getMessage());
            Logger::error('Adapter não disponível: ' . $e->getMessage(), [
                'nfse_id'  => $nfseId,
                'provedor' => $municipio['provedor'] ?? '',
                'ibge'     => $ibge,
            ]);
            Flash::error($e->getMessage());
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        // Marca como PROCESSANDO antes de chamar a prefeitura.
        try {
            $nfseModel->update($nfseId, ['status' => 'PROCESSANDO']);
        } catch (RuntimeException $e) {
            Logger::error('Falha ao marcar NFSe como PROCESSANDO: ' . $e->getMessage());
        }

        // Chama adapter->emitir — pode falhar (HTTP timeout, erro de SSL, etc.).
        try {
            $retorno = $adapter->emitir($nfseArray);
        } catch (\Throwable $e) {
            $errMsg = 'Erro inesperado ao chamar prefeitura: ' . $e->getMessage();
            Logger::error($errMsg, [
                'nfse_id'  => $nfseId,
                'provedor' => $municipio['provedor'] ?? '',
                'ibge'     => $ibge,
            ]);
            $nfseModel->marcarComoRejeitada($nfseId, $errMsg);
            Flash::error($errMsg);
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        // Trata retorno do adapter.
        if (($retorno['sucesso'] ?? false) === true) {
            // Sucesso — marca como AUTORIZADA.
            $nfseModel->marcarComoAutorizada($nfseId, [
                'numero'             => (string) ($retorno['numero'] ?? ''),
                'codigo_verificacao' => (string) ($retorno['codigo_verificacao'] ?? ''),
                'protocolo'          => (string) ($retorno['protocolo'] ?? ''),
                'xml_envio'          => (string) ($retorno['xml_envio'] ?? ''),
                'xml_retorno'        => (string) ($retorno['xml_retorno'] ?? ''),
            ]);

            // Auditoria (sem XMLs no payload — já excluídos em marcarComoAutorizada).
            Logger::audit('CREATE', 'nfse', $nfseId, null, [
                'numero_nfse'  => (string) ($retorno['numero'] ?? ''),
                'protocolo'    => (string) ($retorno['protocolo'] ?? ''),
                'municipio'    => $municipio['nome'] ?? '',
                'provedor'     => $municipio['provedor'] ?? '',
                'ambiente'     => $ambiente,
                'valor'        => $data['valor_servicos'] ?? 0,
            ]);

            // Cria lançamento de RECEITA na conta selecionada (se possível).
            $lancamentoId = $this->criarLancamentoReceita($nfseId, $data, $conta);
            if ($lancamentoId !== null) {
                $nfseModel->vincularLancamento($nfseId, $lancamentoId);
            }

            Flash::success(
                'NFSe autorizada com sucesso! Nº ' . (string) ($retorno['numero'] ?? '?')
                . ' — Protocolo ' . (string) ($retorno['protocolo'] ?? '?')
            );
        } else {
            // Erro — marca como REJEITADA.
            $erro = (string) ($retorno['erro'] ?? 'Erro desconhecido na emissão.');
            $nfseModel->marcarComoRejeitada(
                $nfseId,
                $erro,
                (string) ($retorno['xml_envio'] ?? null),
                (string) ($retorno['xml_retorno'] ?? null)
            );
            Logger::error('NFSe rejeitada pela prefeitura', [
                'nfse_id'  => $nfseId,
                'provedor' => $municipio['provedor'] ?? '',
                'ibge'     => $ibge,
                'erro'     => $erro,
            ]);
            Flash::error('NFSe rejeitada pela prefeitura: ' . $erro);
        }

        Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
    }

    // -----------------------------------------------------------------
    // Lista / DataTables.
    // -----------------------------------------------------------------

    /**
     * GET /nfse/lista — lista de NFSes com filtros por período, status, município e ambiente.
     *
     * @return void
     */
    public function lista(): void
    {
        Auth::require('nfse', 'read');

        $inicioRaw = (string) (Request::get('data_inicio') ?? '');
        $fimRaw    = (string) (Request::get('data_fim') ?? '');

        $inicio = $inicioRaw !== '' ? (Sanitizer::date($inicioRaw) ?? $inicioRaw) : date('Y-m-01');
        $fim    = $fimRaw    !== '' ? (Sanitizer::date($fimRaw)    ?? $fimRaw)    : date('Y-m-d');

        $filtros = [
            'status'       => (string) (Request::get('status') ?? ''),
            'municipio_id' => (string) (Request::get('municipio_id') ?? ''),
            'ambiente'     => (string) (Request::get('ambiente') ?? ''),
        ];

        $filtrosNorm = array_filter($filtros, static fn (string $v): bool => $v !== '');

        $nfseModel = new Nfse();
        $nfses     = $nfseModel->byPeriodo($inicio . ' 00:00:00', $fim . ' 23:59:59', $filtrosNorm);

        // Lista de municípios ativos para o select de filtro.
        $municipios = (new MunicipioNfse())->ativos();
        $ambiente   = (string) (Config::get('nfse_ambiente', 'HOMOLOGACAO') ?? 'HOMOLOGACAO');

        Response::view('nfse/lista', [
            'pageTitle'  => 'NFSe — Lista',
            'nfses'      => $nfses,
            'municipios' => $municipios,
            'filtros'    => array_merge($filtros, [
                'data_inicio' => $inicio,
                'data_fim'    => $fim,
            ]),
            'ambiente'   => $ambiente,
        ]);
    }

    // -----------------------------------------------------------------
    // Visualizar + DANFSE.
    // -----------------------------------------------------------------

    /**
     * GET /nfse/{id}/visualizar — detalhes da NFSe + DANFSE (se AUTORIZADA).
     *
     * @param string $id ID da NFSe.
     *
     * @return void
     */
    public function visualizar(string $id): void
    {
        Auth::require('nfse', 'read');

        $nfseId = (int) $id;
        if ($nfseId <= 0) {
            Flash::error('ID de NFSe inválido.');
            Response::redirect('index.php?route=nfse/lista');
        }

        $nfseModel = new Nfse();
        $nfse = $nfseModel->findWithRelations($nfseId);

        if ($nfse === null) {
            Flash::error('NFSe não encontrada.');
            Response::redirect('index.php?route=nfse/lista');
        }

        // Itens da NFSe.
        $itens = (new NfseItem())->byNfse($nfseId);

        // Gera DANFSE se AUTORIZADA.
        $danfseHtml = null;
        if ((string) ($nfse['status'] ?? '') === 'AUTORIZADA') {
            $certificado = (new Certificado())->ativo();
            if ($certificado !== null) {
                try {
                    $certForAdapter = $certificado;
                    $certForAdapter['senha'] = (new Certificado())->getSenha((int) $certificado['id']);
                    $municipio = [
                        'provedor'     => (string) ($nfse['municipio_provedor'] ?? ''),
                        'nome'         => (string) ($nfse['municipio_nome'] ?? ''),
                        'codigo_ibge'  => (string) ($nfse['municipio_ibge'] ?? ''),
                    ];
                    $ambiente = (string) ($nfse['ambiente'] ?? Config::get('nfse_ambiente', 'HOMOLOGACAO'));
                    $adapter  = $this->getAdapter($municipio, $certForAdapter, $ambiente);
                    $danfseHtml = $adapter->gerarDanfse($nfse);
                } catch (\Throwable $e) {
                    Logger::error('Falha ao gerar DANFSE: ' . $e->getMessage(), ['nfse_id' => $nfseId]);
                    // Não aborta — apenas deixa o DANFSE em branco.
                }
            }
        }

        Response::view('nfse/visualizar', [
            'pageTitle'   => 'NFSe #' . $nfseId,
            'nfse'        => $nfse,
            'itens'       => $itens,
            'danfseHtml'  => $danfseHtml,
        ]);
    }

    // -----------------------------------------------------------------
    // Cancelamento.
    // -----------------------------------------------------------------

    /**
     * POST /nfse/{id}/cancelar — cancela NFSe na prefeitura com motivo.
     *
     * @param string $id ID da NFSe.
     *
     * @return void
     */
    public function cancelar(string $id): void
    {
        Auth::require('nfse', 'delete');

        $nfseId = (int) $id;
        if ($nfseId <= 0) {
            Flash::error('ID de NFSe inválido.');
            Response::redirect('index.php?route=nfse/lista');
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $motivo = trim((string) Request::post('motivo', ''));
        if (mb_strlen($motivo) < 5) {
            Flash::error('Motivo do cancelamento é obrigatório (mínimo 5 caracteres).');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $nfseModel = new Nfse();
        $nfse = $nfseModel->findWithRelations($nfseId);
        if ($nfse === null) {
            Flash::error('NFSe não encontrada.');
            Response::redirect('index.php?route=nfse/lista');
        }

        if ((string) ($nfse['status'] ?? '') !== 'AUTORIZADA') {
            Flash::error('Apenas NFSes AUTORIZADAS podem ser canceladas.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $protocolo = (string) ($nfse['protocolo'] ?? '');
        if ($protocolo === '') {
            Flash::error('NFSe não possui protocolo — impossível cancelar.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        // Busca certificado ativo.
        $certModel = new Certificado();
        $certificado = $certModel->ativo();
        if ($certificado === null) {
            Flash::error('Nenhum certificado ativo. Impossível cancelar.');
            Response::redirect('index.php?route=nfse/certificado');
        }
        try {
            $certificado['senha'] = $certModel->getSenha((int) $certificado['id']);
        } catch (RuntimeException $e) {
            Logger::error('Falha ao descriptografar senha do certificado: ' . $e->getMessage());
            Flash::error('Falha ao descriptografar senha do certificado.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $municipio = [
            'provedor'    => (string) ($nfse['municipio_provedor'] ?? ''),
            'nome'        => (string) ($nfse['municipio_nome'] ?? ''),
            'codigo_ibge' => (string) ($nfse['municipio_ibge'] ?? ''),
        ];
        $ambiente = (string) ($nfse['ambiente'] ?? Config::get('nfse_ambiente', 'HOMOLOGACAO'));

        try {
            $adapter = $this->getAdapter($municipio, $certificado, $ambiente);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        try {
            $retorno = $adapter->cancelar($protocolo, $motivo);
        } catch (\Throwable $e) {
            Logger::error('Erro inesperado ao cancelar NFSe: ' . $e->getMessage(), ['nfse_id' => $nfseId]);
            Flash::error('Erro inesperado ao cancelar NFSe: ' . $e->getMessage());
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        if (($retorno['sucesso'] ?? false) === true) {
            $nfseModel->marcarComoCancelada($nfseId, $motivo);
            Logger::audit('DELETE', 'nfse', $nfseId, null, [
                'protocolo' => $protocolo,
                'motivo'    => $motivo,
            ]);
            Flash::success('NFSe cancelada com sucesso na prefeitura.');
        } else {
            $erro = (string) ($retorno['erro'] ?? 'Erro desconhecido no cancelamento.');
            Logger::error('Cancelamento de NFSe recusado', [
                'nfse_id'   => $nfseId,
                'protocolo' => $protocolo,
                'erro'      => $erro,
            ]);
            Flash::error('Cancelamento recusado: ' . $erro);
        }

        Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
    }

    // -----------------------------------------------------------------
    // Configuração.
    // -----------------------------------------------------------------

    /**
     * GET  /nfse/configurar — exibe form de configuração NFSe.
     * POST /nfse/configurar — salva município ativo, ambiente, série RPS, próximo RPS,
     *                         alíquota default e código de serviço default.
     *
     * @return void
     */
    public function configurar(): void
    {
        Auth::require('nfse', 'configuracao');

        $munModel    = new MunicipioNfse();
        $municipios  = $munModel->ativos();

        if (!Request::isPost()) {
            Response::view('nfse/configurar', [
                'pageTitle'   => 'NFSe — Configuração',
                'municipios'  => $municipios,
                'config'      => [
                    'nfse_municipio_ativo'         => (string) (Config::get('nfse_municipio_ativo', '') ?? ''),
                    'nfse_ambiente'                => (string) (Config::get('nfse_ambiente', 'HOMOLOGACAO') ?? 'HOMOLOGACAO'),
                    'nfse_serie_rps'               => (string) (Config::get('nfse_serie_rps', '1') ?? '1'),
                    'nfse_proximo_numero_rps'      => (int)    (Config::get('nfse_proximo_numero_rps', 1) ?? 1),
                    'nfse_aliquota_default'        => (string) (Config::get('nfse_aliquota_default', '0.03') ?? '0.03'),
                    'nfse_servico_codigo_default'  => (string) (Config::get('nfse_servico_codigo_default', '') ?? ''),
                    'nfse_inscricao_municipal'     => (string) (Config::get('nfse_inscricao_municipal', '') ?? ''),
                ],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=nfse/configurar');
        }

        $ibge    = trim((string) Request::post('nfse_municipio_ativo', ''));
        $ambiente = (string) Request::post('nfse_ambiente', 'HOMOLOGACAO');
        if (!in_array($ambiente, ['HOMOLOGACAO', 'PRODUCAO'], true)) {
            $ambiente = 'HOMOLOGACAO';
        }

        $serieRps = trim((string) Request::post('nfse_serie_rps', '1'));
        if ($serieRps === '') {
            $serieRps = '1';
        }
        $proxRps = max(1, Sanitizer::int((string) Request::post('nfse_proximo_numero_rps', '1')));
        $aliq    = Sanitizer::decimal((string) Request::post('nfse_aliquota_default', '0.03'));
        $servCod = trim((string) Request::post('nfse_servico_codigo_default', ''));
        $im      = trim((string) Request::post('nfse_inscricao_municipal', ''));

        // Valida município se informado.
        if ($ibge !== '' && $munModel->byIbge($ibge) === null) {
            Flash::error('Município informado não está no catálogo.');
            Response::redirect('index.php?route=nfse/configurar');
        }

        try {
            Config::set('nfse_municipio_ativo',         $ibge);
            Config::set('nfse_ambiente',                $ambiente);
            Config::set('nfse_serie_rps',               $serieRps);
            Config::set('nfse_proximo_numero_rps',      $proxRps);
            Config::set('nfse_aliquota_default',        number_format($aliq, 4, '.', ''));
            Config::set('nfse_servico_codigo_default',  $servCod);
            Config::set('nfse_inscricao_municipal',     $im);

            Logger::audit('UPDATE', 'nfse_config', null, null, [
                'municipio_ativo'    => $ibge,
                'ambiente'           => $ambiente,
                'serie_rps'          => $serieRps,
                'proximo_rps'        => $proxRps,
                'aliquota_default'   => $aliq,
                'servico_codigo'     => $servCod,
            ]);
            Flash::success('Configurações de NFSe salvas com sucesso.');
        } catch (\Throwable $e) {
            Logger::error('Falha ao salvar configurações NFSe: ' . $e->getMessage());
            Flash::error('Erro ao salvar configurações: ' . $e->getMessage());
        }

        Response::redirect('index.php?route=nfse/configurar');
    }

    // -----------------------------------------------------------------
    // Certificado.
    // -----------------------------------------------------------------

    /**
     * GET  /nfse/certificado — exibe form de upload + lista de certificados.
     * POST /nfse/certificado — processa upload de .pfx (valida, move, criptografa senha).
     *
     * @return void
     */
    public function certificado(): void
    {
        Auth::require('nfse', 'certificado');

        $certModel = new Certificado();

        if (!Request::isPost()) {
            $certs = $certModel->all(['id' => 'DESC']);
            // Enriquece com status calculado (expirado / dias restantes).
            foreach ($certs as &$c) {
                $validade = (string) ($c['validade'] ?? '');
                $diasRest = null;
                $expirado = false;
                if ($validade !== '') {
                    $ts = strtotime($validade);
                    if ($ts !== false) {
                        $hoje = strtotime(date('Y-m-d'));
                        $diasRest = (int) round(($ts - $hoje) / 86400);
                        $expirado = $diasRest < 0;
                    }
                }
                $c['dias_restantes'] = $diasRest;
                $c['expirado']       = $expirado;
            }
            unset($c);

            Response::view('nfse/certificado', [
                'pageTitle'    => 'NFSe — Certificado A1',
                'certificados' => $certs,
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        // Valida upload.
        if (!isset($_FILES['certificado']) || !is_array($_FILES['certificado'])) {
            Flash::error('Nenhum arquivo enviado. Selecione um arquivo .pfx.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        $upload = $_FILES['certificado'];
        $errCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errCode !== UPLOAD_ERR_OK) {
            Flash::error('Erro no upload do arquivo (código ' . $errCode . '): ' . $this->uploadErrorMessage($errCode));
            Response::redirect('index.php?route=nfse/certificado');
        }

        $tmpPath  = (string) ($upload['tmp_name'] ?? '');
        $nomeOrig = (string) ($upload['name'] ?? '');
        $tamanho  = (int) ($upload['size'] ?? 0);

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            Flash::error('Upload inválido. Tente novamente.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        // Extensão deve ser .pfx ou .p12.
        $ext = strtolower(pathinfo($nomeOrig, PATHINFO_EXTENSION));
        if (!in_array($ext, ['pfx', 'p12'], true)) {
            Flash::error('Extensão inválida. Apenas arquivos .pfx ou .p12 são aceitos.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        // Tamanho máximo: 100 KB.
        if ($tamanho > 102400) {
            Flash::error('Arquivo muito grande. Tamanho máximo: 100 KB.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        $senha = (string) Request::post('senha', '');
        if ($senha === '') {
            Flash::error('Senha do certificado é obrigatória.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        $nome = trim((string) Request::post('nome', ''));
        if ($nome === '') {
            $nome = $nomeOrig;
        }

        // Valida .pfx via openssl_pkcs12_read antes de persistir (falha rápida com mensagem clara).
        $pfxContent = file_get_contents($tmpPath);
        if ($pfxContent === false) {
            Flash::error('Não foi possível ler o arquivo enviado.');
            Response::redirect('index.php?route=nfse/certificado');
        }
        $certs = [];
        if (!@openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            Flash::error('Senha do certificado inválida ou arquivo .pfx corrompido.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        // Desativa certificado ativo anterior (regra: apenas 1 ativo).
        $ativo = $certModel->ativo();
        if ($ativo !== null) {
            try {
                $certModel->desativar((int) $ativo['id']);
            } catch (\Throwable $e) {
                Logger::error('Falha ao desativar certificado anterior: ' . $e->getMessage());
            }
        }

        // Cria registro + move arquivo + criptografa senha.
        try {
            $novoId = $certModel->createWithFile(['nome' => $nome, 'senha' => $senha], $tmpPath);

            // Auditoria — NUNCA incluir senha no payload (mesmo criptografada).
            Logger::audit('CREATE', 'certificados', $novoId, null, [
                'nome'         => $nome,
                'arquivo_path' => '(omitted)',
                'tamanho'      => $tamanho,
            ]);
            Flash::success('Certificado cadastrado e ativado com sucesso.');
        } catch (RuntimeException $e) {
            Logger::error('Falha ao cadastrar certificado: ' . $e->getMessage());
            Flash::error('Erro ao cadastrar certificado: ' . $e->getMessage());
        }

        Response::redirect('index.php?route=nfse/certificado');
    }

    /**
     * POST /nfse/certificado-desativar — desativa um certificado (soft-delete).
     *
     * @return void
     */
    public function certificadoDesativar(): void
    {
        Auth::require('nfse', 'certificado');

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        $certId = (int) Request::post('certificado_id', 0);
        if ($certId <= 0) {
            Flash::error('ID de certificado inválido.');
            Response::redirect('index.php?route=nfse/certificado');
        }

        try {
            (new Certificado())->desativar($certId);
            Logger::audit('DELETE', 'certificados', $certId);
            Flash::success('Certificado desativado com sucesso.');
        } catch (\Throwable $e) {
            Logger::error('Falha ao desativar certificado: ' . $e->getMessage());
            Flash::error('Erro ao desativar certificado.');
        }

        Response::redirect('index.php?route=nfse/certificado');
    }

    // -----------------------------------------------------------------
    // Consulta protocolo.
    // -----------------------------------------------------------------

    /**
     * GET /nfse/consultar/{id} — consulta situação da NFSe na prefeitura pelo protocolo.
     *
     * @param string $id ID da NFSe.
     *
     * @return void
     */
    public function consultar(string $id): void
    {
        Auth::require('nfse', 'read');

        $nfseId = (int) $id;
        if ($nfseId <= 0) {
            Flash::error('ID de NFSe inválido.');
            Response::redirect('index.php?route=nfse/lista');
        }

        $nfseModel = new Nfse();
        $nfse = $nfseModel->findWithRelations($nfseId);
        if ($nfse === null) {
            Flash::error('NFSe não encontrada.');
            Response::redirect('index.php?route=nfse/lista');
        }

        $protocolo = (string) ($nfse['protocolo'] ?? '');
        if ($protocolo === '') {
            Flash::error('NFSe não possui protocolo — impossível consultar.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $certModel = new Certificado();
        $certificado = $certModel->ativo();
        if ($certificado === null) {
            Flash::error('Nenhum certificado ativo. Impossível consultar.');
            Response::redirect('index.php?route=nfse/certificado');
        }
        try {
            $certificado['senha'] = $certModel->getSenha((int) $certificado['id']);
        } catch (RuntimeException $e) {
            Logger::error('Falha ao descriptografar senha do certificado: ' . $e->getMessage());
            Flash::error('Falha ao descriptografar senha do certificado.');
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        $municipio = [
            'provedor'    => (string) ($nfse['municipio_provedor'] ?? ''),
            'nome'        => (string) ($nfse['municipio_nome'] ?? ''),
            'codigo_ibge' => (string) ($nfse['municipio_ibge'] ?? ''),
        ];
        $ambiente = (string) ($nfse['ambiente'] ?? Config::get('nfse_ambiente', 'HOMOLOGACAO'));

        try {
            $adapter = $this->getAdapter($municipio, $certificado, $ambiente);
        } catch (RuntimeException $e) {
            Flash::error($e->getMessage());
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        try {
            $retorno = $adapter->consultar($protocolo);
        } catch (\Throwable $e) {
            Logger::error('Erro inesperado ao consultar NFSe: ' . $e->getMessage(), ['nfse_id' => $nfseId]);
            Flash::error('Erro inesperado ao consultar NFSe: ' . $e->getMessage());
            Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
        }

        if (($retorno['sucesso'] ?? false) === true) {
            $status = (string) ($retorno['status'] ?? 'desconhecido');
            Flash::info('Situação atual na prefeitura: ' . $status);
        } else {
            $erro = (string) ($retorno['erro'] ?? 'Erro desconhecido na consulta.');
            Flash::error('Consulta recusada: ' . $erro);
        }

        Response::redirect('index.php?route=nfse/' . $nfseId . '/visualizar');
    }

    // -----------------------------------------------------------------
    // Helpers privados.
    // -----------------------------------------------------------------

    /**
     * Factory de adapter conforme provedor do município.
     *
     * @param array<string,mixed> $municipio   Registro de municipios_nfse (deve conter 'provedor').
     * @param array<string,mixed> $certificado Registro de certificados (deve conter 'arquivo_path' e 'senha').
     * @param string              $ambiente    'HOMOLOGACAO' ou 'PRODUCAO'.
     *
     * @return NfseAdapterInterface
     *
     * @throws RuntimeException Se provedor não implementado ou desconhecido.
     */
    private function getAdapter(array $municipio, array $certificado, string $ambiente): NfseAdapterInterface
    {
        $provedor = strtoupper((string) ($municipio['provedor'] ?? ''));
        return match ($provedor) {
            'WEBISS'   => new WebissAdapter($certificado, $ambiente),
            'BETHA'    => new BethaAdapter($certificado, $ambiente),
            'DSF'      => new DsfAdapter($certificado, $ambiente),
            'SALVADOR' => new SalvadorAdapter($certificado, $ambiente),
            'SIMPLISS', 'ISSNET' => throw new RuntimeException(
                'Provedor ' . $provedor . ' não implementado para o município '
                . (string) ($municipio['nome'] ?? '?') . '.'
            ),
            default => throw new RuntimeException('Provedor desconhecido: ' . $provedor),
        };
    }

    /**
     * Coleta e sanitiza os dados da NFSe a partir de $_POST (form de emissão).
     *
     * @return array<string,mixed>
     */
    private function collectNfseFromRequest(): array
    {
        $valorServicos = Sanitizer::decimal((string) Request::post('valor_servicos', '0'));
        $valorDeducoes = Sanitizer::decimal((string) Request::post('valor_deducoes', '0'));
        $aliquota      = Sanitizer::decimal((string) Request::post('aliquota', '0.03'));
        $issRetido     = Request::post('iss_retido', '0') === '1' ? 1 : 0;

        // CPF/CNPJ e CEP em apenas dígitos.
        $tomadorDoc = preg_replace('/\D/', '', (string) Request::post('tomador_cnpj_cpf', '')) ?? '';
        $cep        = preg_replace('/\D/', '', (string) Request::post('tomador_cep', '')) ?? '';

        // Tenta vincular a um cliente/fornecedor existente via CPF/CNPJ.
        $clienteFornecedorId = null;
        if ($tomadorDoc !== '') {
            $cf = (new ClienteFornecedor())->search($tomadorDoc);
            if ($cf !== []) {
                $clienteFornecedorId = (int) ($cf[0]['id'] ?? 0);
                if ($clienteFornecedorId === 0) {
                    $clienteFornecedorId = null;
                }
            }
        }

        return [
            'tomador_nome'        => trim((string) Request::post('tomador_nome', '')),
            'tomador_cnpj_cpf'    => $tomadorDoc,
            'tomador_email'       => trim((string) Request::post('tomador_email', '')) ?: null,
            'tomador_endereco'    => trim((string) Request::post('tomador_endereco', '')) ?: null,
            'tomador_numero'      => trim((string) Request::post('tomador_numero', '')) ?: null,
            'tomador_bairro'      => trim((string) Request::post('tomador_bairro', '')) ?: null,
            'tomador_cidade'      => trim((string) Request::post('tomador_cidade', '')) ?: null,
            'tomador_uf'          => strtoupper(trim((string) Request::post('tomador_uf', ''))) ?: null,
            'tomador_cep'         => $cep ?: null,
            'servico_codigo'      => trim((string) Request::post('servico_codigo', '')),
            'servico_descricao'   => trim((string) Request::post('servico_descricao', '')) ?: null,
            'discriminacao'       => trim((string) Request::post('discriminacao', '')),
            'valor_servicos'      => $valorServicos,
            'valor_deducoes'      => $valorDeducoes,
            'aliquota'            => $aliquota,
            'iss_retido'          => $issRetido,
            'conta_id'            => (int) Request::post('conta_id', 0),
            'cliente_fornecedor_id' => $clienteFornecedorId,
        ];
    }

    /**
     * Cria um lançamento de RECEITA PAGO na conta selecionada, vinculado à NFSe.
     *
     * Regras:
     *   - valor = valor_liquido da NFSe
     *   - descricao = "NFSe #{numero_nfse ou id} — {tomador_nome}"
     *   - tipo = RECEITA, status = PAGO, data_pagamento = hoje
     *   - plano_conta_id: tenta nfse_plano_conta_receita_id (config), senão primeiro plano
     *     ativo do tipo RECEITA. Se não houver, loga warning e retorna null (não bloqueia
     *     a autorização da NFSe).
     *
     * @param int                  $nfseId ID da NFSe autorizada.
     * @param array<string,mixed>  $data   Dados sanitizados da NFSe (já persistidos).
     * @param array<string,mixed>|null $conta Conta selecionada (já validada).
     *
     * @return int|null ID do lançamento criado ou null se não foi possível.
     */
    private function criarLancamentoReceita(int $nfseId, array $data, ?array $conta): ?int
    {
        $contaId = (int) ($data['conta_id'] ?? 0);
        if ($contaId <= 0 || $conta === null) {
            return null;
        }

        // Resolve plano de contas de receita.
        $planoId = (int) (Config::get('nfse_plano_conta_receita_id', 0) ?? 0);
        if ($planoId <= 0) {
            $planos = (new PlanoContas())->byTipo('RECEITA');
            if ($planos !== []) {
                $planoId = (int) ($planos[0]['id'] ?? 0);
            }
        }
        if ($planoId <= 0) {
            Logger::error(
                'Não foi possível criar lançamento de receita: nenhum plano de contas de RECEITA encontrado.',
                ['nfse_id' => $nfseId]
            );
            return null;
        }

        $valorLiquido = (float) ($data['valor_liquido'] ?? $data['valor_servicos'] ?? 0);
        $descNum      = (string) ($data['numero_nfse'] ?? (string) $nfseId);
        $tomadorNome  = (string) ($data['tomador_nome'] ?? '');
        $descricao    = 'NFSe #' . $descNum . ($tomadorNome !== '' ? ' — ' . $tomadorNome : '');

        $lancData = [
            'conta_id'              => $contaId,
            'plano_conta_id'        => $planoId,
            'cliente_fornecedor_id' => $data['cliente_fornecedor_id'] ?? null,
            'tipo'                  => 'RECEITA',
            'valor'                 => $valorLiquido,
            'data_lancamento'       => date('Y-m-d'),
            'data_pagamento'        => date('Y-m-d'),
            'descricao'             => $descricao,
            'observacao'            => 'Lançamento automático de emissão de NFSe #' . $nfseId,
            'status'                => 'PAGO',
            'forma_pagamento'       => 'NFSE',
            'documento'             => $descNum,
            'criado_por'            => Auth::id(),
        ];

        try {
            $lancId = (new Lancamento())->create($lancData);
            Logger::audit('CREATE', 'lancamentos', $lancId, null, array_merge($lancData, [
                'origem'  => 'nfse',
                'nfse_id' => $nfseId,
            ]));
            return $lancId;
        } catch (\Throwable $e) {
            Logger::error('Falha ao criar lançamento de receita para NFSe: ' . $e->getMessage(), [
                'nfse_id' => $nfseId,
            ]);
            return null;
        }
    }

    /**
     * Mapeia código de erro de upload PHP para mensagem amigável.
     *
     * @param int $code Código de erro (UPLOAD_ERR_*).
     *
     * @return string
     */
    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE   => 'Arquivo maior que o limite do PHP (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE  => 'Arquivo maior que o limite do formulário.',
            UPLOAD_ERR_PARTIAL    => 'Upload parcial — arquivo incompleto.',
            UPLOAD_ERR_NO_FILE    => 'Nenhum arquivo enviado.',
            UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário ausente no servidor.',
            UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar no disco.',
            UPLOAD_ERR_EXTENSION  => 'Extensão bloqueada pelo PHP.',
            default               => 'Erro desconhecido no upload.',
        };
    }
}
