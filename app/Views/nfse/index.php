<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $stats          Estatísticas do mês (total, autorizadas, rejeitadas, canceladas, rascunho, processando).
 * @var array  $ultimas        Últimas 5 NFSes emitidas.
 * @var array|null $certificado Info do certificado ativo (ou null).
 * @var string $ambiente       HOMOLOGACAO | PRODUCAO.
 * @var array|null $municipioAtivo Registro de municipios_nfse ativo (ou null).
 */
use App\Helpers\Format;

$stats         = $stats         ?? ['total' => 0, 'autorizadas' => 0, 'rejeitadas' => 0, 'canceladas' => 0, 'rascunho' => 0, 'processando' => 0];
$ultimas       = $ultimas       ?? [];
$certificado   = $certificado   ?? null;
$ambiente      = $ambiente      ?? 'HOMOLOGACAO';
$municipioAtivo = $municipioAtivo ?? null;

$statusBadge = [
    'AUTORIZADA'  => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Autorizada</span>',
    'REJEITADA'   => '<span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i>Rejeitada</span>',
    'CANCELADA'   => '<span class="badge bg-secondary"><i class="bi bi-slash-circle me-1"></i>Cancelada</span>',
    'RASCUNHO'    => '<span class="badge bg-warning text-dark"><i class="bi bi-pencil me-1"></i>Rascunho</span>',
    'PROCESSANDO' => '<span class="badge bg-info text-dark"><i class="bi bi-hourglass-split me-1"></i>Processando</span>',
];

// Cor do certificado: verde > 30 dias, amarelo 7-30, vermelho < 7 ou expirado.
$certClass  = 'danger';
$certIcon   = 'bi-x-circle-fill';
$certText   = 'Expirado ou ausente';
$certDias   = null;
if ($certificado !== null) {
    $certDias = $certificado['dias_restant'] ?? $certificado['dias_restantes'] ?? null;
    $expirado = (bool) ($certificado['expirado'] ?? false);
    if (!$expirado) {
        if ($certDias === null) {
            $certClass = 'secondary';
            $certIcon  = 'bi-question-circle-fill';
            $certText  = 'Validade desconhecida';
        } elseif ($certDias > 30) {
            $certClass = 'success';
            $certIcon  = 'bi-shield-check';
            $certText  = 'Válido — ' . $certDias . ' dias restantes';
        } elseif ($certDias >= 7) {
            $certClass = 'warning';
            $certIcon  = 'bi-shield-exclamation';
            $certText  = 'Vence em ' . $certDias . ' dias';
        } else {
            $certClass = 'danger';
            $certIcon  = 'bi-shield-x';
            $certText  = 'Vence em ' . $certDias . ' dia(s) — renovar!';
        }
    }
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-receipt-cutoff me-2 text-primary"></i><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div>
            <a href="index.php?route=nfse/emitir" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i> Emitir NFSe</a>
            <a href="index.php?route=nfse/lista" class="btn btn-outline-secondary btn-sm"><i class="bi bi-list me-1"></i> Lista</a>
        </div>
    </div>

    <?php if (($stats['total'] ?? 0) === 0): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <?php $message = 'Nenhuma NFSe emitida no mês atual. Clique em "Emitir NFSe" para começar.'; include __DIR__ . '/../partials/empty_state.php'; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- 4 cards de estatísticas -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card text-white bg-primary shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-uppercase opacity-75">Total no mês</div>
                                <div class="fs-2 fw-bold mt-1"><?= (int) ($stats['total'] ?? 0) ?></div>
                            </div>
                            <i class="bi bi-receipt-cutoff fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-white bg-success shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-uppercase opacity-75">Autorizadas</div>
                                <div class="fs-2 fw-bold mt-1"><?= (int) ($stats['autorizadas'] ?? 0) ?></div>
                            </div>
                            <i class="bi bi-check-circle fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-white bg-danger shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-uppercase opacity-75">Rejeitadas</div>
                                <div class="fs-2 fw-bold mt-1"><?= (int) ($stats['rejeitadas'] ?? 0) ?></div>
                            </div>
                            <i class="bi bi-x-octagon fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="card text-white bg-warning shadow-sm h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="small text-uppercase opacity-75">Canceladas</div>
                                <div class="fs-2 fw-bold mt-1"><?= (int) ($stats['canceladas'] ?? 0) ?></div>
                            </div>
                            <i class="bi bi-slash-circle fs-1 opacity-75"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3">
        <!-- Status do Sistema -->
        <div class="col-lg-4">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-shield-check me-1 text-primary"></i>Status do Sistema</h2>
                </div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Ambiente</dt>
                        <dd class="col-7">
                            <?php if ($ambiente === 'PRODUCAO'): ?>
                                <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>Produção</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><i class="bi bi-flask me-1"></i>Homologação</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-5 text-muted">Município ativo</dt>
                        <dd class="col-7">
                            <?php if ($municipioAtivo !== null): ?>
                                <strong><?= htmlspecialchars($municipioAtivo['nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong>
                                <div class="small text-muted">Provedor: <?= htmlspecialchars($municipioAtivo['provedor'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                            <?php else: ?>
                                <span class="text-danger">Não configurado</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-5 text-muted">Certificado</dt>
                        <dd class="col-7">
                            <?php if ($certificado === null): ?>
                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Nenhum ativo</span>
                                <div class="small text-muted mt-1">Faça upload de um .pfx</div>
                            <?php else: ?>
                                <span class="badge bg-<?= htmlspecialchars($certClass, ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="bi <?= htmlspecialchars($certIcon, ENT_QUOTES, 'UTF-8') ?> me-1"></i>
                                    <?= htmlspecialchars($certText, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <div class="small text-muted mt-1">
                                    Validade: <?= Format::dateBR((string) ($certificado['validade'] ?? '')) ?>
                                </div>
                                <div class="small text-muted">
                                    Titular: <?= htmlspecialchars($certificado['nome'] ?? $certificado['titular_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="small text-muted">
                                    CNPJ: <?= htmlspecialchars($certificado['cnpj_titular'] ?? $certificado['cnpj'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>
                        </dd>
                    </dl>
                </div>
                <div class="card-footer bg-white d-flex gap-2">
                    <a href="index.php?route=nfse/configurar" class="btn btn-sm btn-outline-primary flex-grow-1">
                        <i class="bi bi-gear me-1"></i> Configurar
                    </a>
                    <a href="index.php?route=nfse/certificado" class="btn btn-sm btn-outline-secondary flex-grow-1">
                        <i class="bi bi-key me-1"></i> Certificado
                    </a>
                </div>
            </div>
        </div>

        <!-- Últimas 5 NFSe -->
        <div class="col-lg-8">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0"><i class="bi bi-clock-history me-1 text-primary"></i>Últimas 5 NFSe Emitidas</h2>
                    <a href="index.php?route=nfse/lista" class="btn btn-sm btn-link text-decoration-none">Ver todas <i class="bi bi-arrow-right"></i></a>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($ultimas)): ?>
                        <div class="p-4">
                            <?php $message = 'Nenhuma NFSe emitida ainda.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nº NFSe</th>
                                        <th>Data</th>
                                        <th>Tomador</th>
                                        <th>Município</th>
                                        <th class="text-end">Valor</th>
                                        <th>Status</th>
                                        <th class="text-center no-print"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimas as $n): ?>
                                        <tr>
                                            <td>
                                                <?php $numero = (string) ($n['numero_nfse'] ?? ''); ?>
                                                <?php if ($numero !== ''): ?>
                                                    <strong><?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <?php else: ?>
                                                    <span class="text-muted">RPS #<?= (int) ($n['numero_rps'] ?? 0) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= Format::dateBR((string) ($n['data_emissao'] ?? '')) ?></td>
                                            <td><?= htmlspecialchars($n['tomador_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($n['municipio_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end"><?= Format::moneyBRL((float) ($n['valor_servicos'] ?? 0)) ?></td>
                                            <td><?= $statusBadge[$n['status'] ?? ''] ?? '<span class="badge bg-secondary">—</span>' ?></td>
                                            <td class="text-center no-print">
                                                <a href="index.php?route=nfse/<?= (int) ($n['id'] ?? 0) ?>/visualizar" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
