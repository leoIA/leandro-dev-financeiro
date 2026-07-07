<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $nfse       Registro completo da NFSe (com joins: municipio_nome, conta_nome, etc.).
 * @var array  $itens      Itens da NFSe (tabela nfse_item).
 * @var string|null $danfseHtml HTML do DANFSE (para iframe srcdoc) — apenas se AUTORIZADA.
 */
use App\Helpers\Format;

$nfse       = $nfse       ?? [];
$itens      = $itens      ?? [];
$danfseHtml = $danfseHtml ?? null;

$status = (string) ($nfse['status'] ?? '');
$amb    = (string) ($nfse['ambiente'] ?? 'HOMOLOGACAO');
$id     = (int) ($nfse['id'] ?? 0);

$statusBadge = [
    'AUTORIZADA'  => '<span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i>Autorizada</span>',
    'REJEITADA'   => '<span class="badge bg-danger fs-6"><i class="bi bi-x-octagon me-1"></i>Rejeitada</span>',
    'CANCELADA'   => '<span class="badge bg-secondary fs-6"><i class="bi bi-slash-circle me-1"></i>Cancelada</span>',
    'RASCUNHO'    => '<span class="badge bg-warning text-dark fs-6"><i class="bi bi-pencil me-1"></i>Rascunho</span>',
    'PROCESSANDO' => '<span class="badge bg-info text-dark fs-6"><i class="bi bi-hourglass-split me-1"></i>Processando</span>',
];
$ambienteBadge = [
    'PRODUCAO'    => '<span class="badge bg-success">Produção</span>',
    'HOMOLOGACAO' => '<span class="badge bg-warning text-dark">Homologação</span>',
];

$valorServicos = (float) ($nfse['valor_servicos'] ?? 0);
$valorDeducoes = (float) ($nfse['valor_deducoes'] ?? 0);
$valorBase     = (float) ($nfse['valor_base_calculo'] ?? ($valorServicos - $valorDeducoes));
$aliquota      = (float) ($nfse['aliquota'] ?? 0);
$valorIss      = (float) ($nfse['valor_iss'] ?? 0);
$valorLiquido  = (float) ($nfse['valor_liquido'] ?? 0);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0">
            <i class="bi bi-receipt-cutoff me-2 text-primary"></i>
            NFSe
            <?php $numero = (string) ($nfse['numero_nfse'] ?? ''); ?>
            <?php if ($numero !== ''): ?>
                <span class="text-primary">#<?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?></span>
            <?php else: ?>
                <span class="text-muted">RPS #<?= (int) ($nfse['numero_rps'] ?? 0) ?></span>
            <?php endif; ?>
        </h1>
        <div>
            <a href="index.php?route=nfse/lista" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar à lista</a>
        </div>
    </div>

    <!-- Card header com status + ações -->
    <div class="card shadow-sm mb-3">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <?= $statusBadge[$status] ?? '<span class="badge bg-secondary">—</span>' ?>
                <?= $ambienteBadge[$amb] ?? '' ?>
                <?php if (!empty($nfse['protocolo'])): ?>
                    <span class="text-muted small">Protocolo: <code><?= htmlspecialchars($nfse['protocolo'], ENT_QUOTES, 'UTF-8') ?></code></span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-1">
                <?php if ($status === 'AUTORIZADA' && $danfseHtml !== null): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Imprimir DANFSE
                    </button>
                <?php endif; ?>
                <?php if (!empty($nfse['protocolo'])): ?>
                    <a href="index.php?route=nfse/consultar/<?= $id ?>" class="btn btn-sm btn-outline-info" title="Consultar situação na prefeitura">
                        <i class="bi bi-arrow-repeat me-1"></i> Consultar
                    </a>
                <?php endif; ?>
                <?php if ($status === 'AUTORIZADA'): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalCancelar">
                        <i class="bi bi-x-circle me-1"></i> Cancelar
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if ($status === 'REJEITADA' && !empty($nfse['mensagem_erro'])): ?>
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-exclamation-octagon me-1"></i>
                    <strong>NFSe rejeitada pela prefeitura:</strong>
                    <?= htmlspecialchars($nfse['mensagem_erro'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php elseif ($status === 'CANCELADA' && !empty($nfse['motivo_cancelamento'])): ?>
                <div class="alert alert-secondary mb-0">
                    <i class="bi bi-slash-circle me-1"></i>
                    <strong>NFSe cancelada.</strong> Motivo:
                    <?= htmlspecialchars($nfse['motivo_cancelamento'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3">
        <!-- Prestador / Tomador -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-building me-1 text-primary"></i>Prestador</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Razão Social</dt>
                        <dd class="col-sm-8">MM Construtora</dd>
                        <dt class="col-sm-4 text-muted">CNPJ</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($nfse['prestador_cnpj'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4 text-muted">Inscrição Municipal</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($nfse['prestador_im'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4 text-muted">Município</dt>
                        <dd class="col-sm-8">
                            <?= htmlspecialchars($nfse['municipio_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($nfse['municipio_provedor'])): ?>
                                <span class="text-muted">(<?= htmlspecialchars($nfse['municipio_provedor'], ENT_QUOTES, 'UTF-8') ?>)</span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-person me-1 text-primary"></i>Tomador</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">Nome / Razão Social</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($nfse['tomador_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4 text-muted">CPF / CNPJ</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($nfse['tomador_cnpj_cpf'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4 text-muted">Email</dt>
                        <dd class="col-sm-8"><?= htmlspecialchars($nfse['tomador_email'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-4 text-muted">Endereço</dt>
                        <dd class="col-sm-8">
                            <?= htmlspecialchars(implode(', ', array_filter([
                                $nfse['tomador_endereco'] ?? '',
                                $nfse['tomador_numero'] ?? '',
                            ])), ENT_QUOTES, 'UTF-8') ?>
                            <?php if (!empty($nfse['tomador_bairro'])): ?>
                                <br><span class="text-muted">Bairro: <?= htmlspecialchars($nfse['tomador_bairro'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if (!empty($nfse['tomador_cidade']) || !empty($nfse['tomador_uf'])): ?>
                                <br><span class="text-muted">
                                    <?= htmlspecialchars($nfse['tomador_cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    /<?= htmlspecialchars($nfse['tomador_uf'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($nfse['tomador_cep'])): ?>
                                        — CEP <?= htmlspecialchars($nfse['tomador_cep'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </span>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Serviço -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-briefcase me-1 text-primary"></i>Serviço</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-3">
                        <dt class="col-sm-2 text-muted">Código LC 116</dt>
                        <dd class="col-sm-4"><code><?= htmlspecialchars($nfse['servico_codigo'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></dd>
                        <dt class="col-sm-2 text-muted">ISS Retido</dt>
                        <dd class="col-sm-4">
                            <?php if ((int) ($nfse['iss_retido'] ?? 0) === 1): ?>
                                <span class="badge bg-warning text-dark">Sim — pelo tomador</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Não — recolhido pelo prestador</span>
                            <?php endif; ?>
                        </dd>
                        <dt class="col-sm-2 text-muted">Discriminação</dt>
                        <dd class="col-sm-10">
                            <div class="border rounded p-2 bg-light small">
                                <?= nl2br(htmlspecialchars($nfse['discriminacao'] ?? '—', ENT_QUOTES, 'UTF-8')) ?>
                            </div>
                        </dd>
                    </dl>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0">
                            <tbody>
                                <tr>
                                    <th class="table-light" style="width:25%">Valor dos Serviços</th>
                                    <td class="text-end fw-semibold"><?= Format::moneyBRL($valorServicos) ?></td>
                                    <th class="table-light" style="width:25%">Deduções</th>
                                    <td class="text-end"><?= Format::moneyBRL($valorDeducoes) ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">Base de Cálculo</th>
                                    <td class="text-end"><?= Format::moneyBRL($valorBase) ?></td>
                                    <th class="table-light">Alíquota ISS</th>
                                    <td class="text-end"><?= number_format($aliquota * 100, 4, ',', '.') ?>%</td>
                                </tr>
                                <tr>
                                    <th class="table-light">Valor do ISS</th>
                                    <td class="text-end"><?= Format::moneyBRL($valorIss) ?></td>
                                    <th class="table-light bg-info-subtle">Valor Líquido</th>
                                    <td class="text-end fw-bold text-success"><?= Format::moneyBRL($valorLiquido) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Itens -->
        <?php if (!empty($itens)): ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-list-ul me-1 text-primary"></i>Itens da NFSe</h2>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Descrição</th>
                                        <th class="text-end">Qtd.</th>
                                        <th class="text-end">Valor Unitário</th>
                                        <th class="text-end">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($itens as $it): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($it['descricao'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= number_format((float) ($it['quantidade'] ?? 0), 2, ',', '.') ?></td>
                                            <td class="text-end"><?= Format::moneyBRL((float) ($it['valor_unitario'] ?? 0)) ?></td>
                                            <td class="text-end fw-semibold"><?= Format::moneyBRL((float) ($it['valor_total'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- XML -->
        <?php if (!empty($nfse['xml_envio']) || !empty($nfse['xml_retorno'])): ?>
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h2 class="h6 mb-0"><i class="bi bi-code-slash me-1 text-primary"></i>XMLs</h2>
                    </div>
                    <div class="card-body d-flex gap-2 flex-wrap">
                        <?php if (!empty($nfse['xml_envio'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalXmlEnvio">
                                <i class="bi bi-box-arrow-up me-1"></i> Ver XML Envio
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($nfse['xml_retorno'])): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalXmlRetorno">
                                <i class="bi bi-box-arrow-in-down me-1"></i> Ver XML Retorno
                            </button>
                        <?php endif; ?>
                        <?php if (!empty($nfse['codigo_verificacao'])): ?>
                            <div class="ms-auto small text-muted align-self-center">
                                Código de verificação: <code><?= htmlspecialchars($nfse['codigo_verificacao'], ENT_QUOTES, 'UTF-8') ?></code>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- DANFSE -->
        <?php if ($status === 'AUTORIZADA' && $danfseHtml !== null): ?>
            <div class="col-12 d-none d-print-block">
                <div class="card shadow-sm">
                    <div class="card-body p-0">
                        <iframe srcdoc="<?= htmlspecialchars($danfseHtml, ENT_QUOTES, 'UTF-8') ?>"
                                style="width:100%;height:1100px;border:0;"></iframe>
                    </div>
                </div>
            </div>
            <div class="col-12 d-print-none">
                <div class="card shadow-sm">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h2 class="h6 mb-0"><i class="bi bi-file-earmark-text me-1 text-primary"></i>DANFSE</h2>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimir
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <iframe srcdoc="<?= htmlspecialchars($danfseHtml, ENT_QUOTES, 'UTF-8') ?>"
                                style="width:100%;height:1100px;border:0;"></iframe>
                    </div>
                </div>
            </div>
        <?php elseif ($status === 'AUTORIZADA'): ?>
            <div class="col-12 d-print-none">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <?php $message = 'DANFSE não disponível. Gere novamente consultando a NFSe.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Auditoria -->
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-clock-history me-1 text-primary"></i>Auditoria</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-2 text-muted">Criada por</dt>
                        <dd class="col-sm-4"><?= htmlspecialchars($nfse['criado_por_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <dt class="col-sm-2 text-muted">Criada em</dt>
                        <dd class="col-sm-4"><?= Format::datetimeBR((string) ($nfse['criado_em'] ?? '')) ?></dd>

                        <?php if (!empty($nfse['data_autorizacao'])): ?>
                            <dt class="col-sm-2 text-muted">Autorizada em</dt>
                            <dd class="col-sm-4"><?= Format::datetimeBR((string) ($nfse['data_autorizacao'])) ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($nfse['data_cancelamento'])): ?>
                            <dt class="col-sm-2 text-muted">Cancelada em</dt>
                            <dd class="col-sm-4"><?= Format::datetimeBR((string) ($nfse['data_cancelamento'])) ?></dd>
                            <dt class="col-sm-2 text-muted">Motivo do cancelamento</dt>
                            <dd class="col-sm-4"><?= htmlspecialchars($nfse['motivo_cancelamento'] ?? '—', ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($nfse['conta_nome'])): ?>
                            <dt class="col-sm-2 text-muted">Conta vinculada</dt>
                            <dd class="col-sm-4"><?= htmlspecialchars($nfse['conta_nome'], ENT_QUOTES, 'UTF-8') ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($nfse['lancamento_descricao'])): ?>
                            <dt class="col-sm-2 text-muted">Lançamento vinculado</dt>
                            <dd class="col-sm-4">
                                <?= htmlspecialchars($nfse['lancamento_descricao'], ENT_QUOTES, 'UTF-8') ?>
                                <?php if (!empty($nfse['lancamento_id'])): ?>
                                    <a href="index.php?route=lancamentos/<?= (int) $nfse['lancamento_id'] ?>/editar" class="btn btn-sm btn-link p-0 align-baseline">
                                        (ver lançamento #<?= (int) $nfse['lancamento_id'] ?>)
                                    </a>
                                <?php endif; ?>
                            </dd>
                        <?php endif; ?>

                        <dt class="col-sm-2 text-muted">RPS</dt>
                        <dd class="col-sm-4">
                            Série <?= htmlspecialchars((string) ($nfse['serie_rps'] ?? '1'), ENT_QUOTES, 'UTF-8') ?>
                            / Nº <?= (int) ($nfse['numero_rps'] ?? 0) ?>
                            (tipo <?= htmlspecialchars((string) ($nfse['tipo_rps'] ?? 'RPS'), ENT_QUOTES, 'UTF-8') ?>)
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Cancelar NFSe -->
<?php if ($status === 'AUTORIZADA'): ?>
<div class="modal fade" id="modalCancelar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="index.php?route=nfse/<?= $id ?>/cancelar">
                <?= \App\Core\Csrf::field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-1"></i> Cancelar NFSe</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>
                <div class="modal-body">
                    <p>Tem certeza que deseja cancelar a NFSe <strong><?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?></strong>?</p>
                    <p class="text-muted small">O cancelamento é irreversível e será comunicado à prefeitura. O lançamento financeiro vinculado <em>não</em> será automaticamente estornado.</p>
                    <label for="motivo" class="form-label">Motivo do cancelamento <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="motivo" name="motivo" rows="3" required minlength="5" maxlength="500"
                              placeholder="Descreva o motivo (mínimo 5 caracteres)…"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-x-circle me-1"></i> Confirmar Cancelamento</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: XML Envio -->
<?php if (!empty($nfse['xml_envio'])): ?>
<div class="modal fade" id="modalXmlEnvio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-up me-1"></i> XML de Envio (assinado)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <pre class="small bg-dark text-light p-3 rounded" style="max-height:70vh;overflow:auto;white-space:pre-wrap;word-break:break-all;"><?= htmlspecialchars($nfse['xml_envio'], ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Modal: XML Retorno -->
<?php if (!empty($nfse['xml_retorno'])): ?>
<div class="modal fade" id="modalXmlRetorno" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title"><i class="bi bi-box-arrow-in-down me-1"></i> XML de Retorno (prefeitura)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <pre class="small bg-dark text-light p-3 rounded" style="max-height:70vh;overflow:auto;white-space:pre-wrap;word-break:break-all;"><?= htmlspecialchars($nfse['xml_retorno'], ENT_QUOTES, 'UTF-8') ?></pre>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
