<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $nfses      Lista de NFSes no período com filtros aplicados.
 * @var array  $municipios Lista de municipios_nfse ativos (para select de filtro).
 * @var array  $filtros    Filtros aplicados (status, municipio_id, ambiente, data_inicio, data_fim).
 * @var string $ambiente   Ambiente atual (HOMOLOGACAO | PRODUCAO).
 */
use App\Helpers\Format;

$nfses      = $nfses      ?? [];
$municipios = $municipios ?? [];
$filtros    = $filtros    ?? ['status' => '', 'municipio_id' => '', 'ambiente' => '', 'data_inicio' => '', 'data_fim' => ''];
$ambiente   = $ambiente   ?? 'HOMOLOGACAO';

$statusBadge = [
    'AUTORIZADA'  => '<span class="badge bg-success">Autorizada</span>',
    'REJEITADA'   => '<span class="badge bg-danger">Rejeitada</span>',
    'CANCELADA'   => '<span class="badge bg-secondary">Cancelada</span>',
    'RASCUNHO'    => '<span class="badge bg-warning text-dark">Rascunho</span>',
    'PROCESSANDO' => '<span class="badge bg-info text-dark">Processando</span>',
];
$ambienteBadge = [
    'PRODUCAO'    => '<span class="badge bg-success">Produção</span>',
    'HOMOLOGACAO' => '<span class="badge bg-warning text-dark">Homologação</span>',
];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-list-check me-2 text-primary"></i><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <div>
            <a href="index.php?route=nfse/emitir" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i> Emitir NFSe</a>
            <a href="index.php?route=nfse" class="btn btn-outline-secondary btn-sm"><i class="bi bi-speedometer2 me-1"></i> Dashboard</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="index.php?route=nfse/lista" class="row g-2 align-items-end">
                <div class="col-md-2">
                    <label for="f_data_inicio" class="form-label">Data Início</label>
                    <input type="date" class="form-control form-control-sm" id="f_data_inicio" name="data_inicio"
                           value="<?= htmlspecialchars($filtros['data_inicio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label for="f_data_fim" class="form-label">Data Fim</label>
                    <input type="date" class="form-control form-control-sm" id="f_data_fim" name="data_fim"
                           value="<?= htmlspecialchars($filtros['data_fim'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label for="f_status" class="form-label">Status</label>
                    <select class="form-select form-select-sm" id="f_status" name="status">
                        <option value="">Todos</option>
                        <option value="AUTORIZADA"  <?= ($filtros['status'] ?? '') === 'AUTORIZADA'  ? 'selected' : '' ?>>Autorizada</option>
                        <option value="REJEITADA"   <?= ($filtros['status'] ?? '') === 'REJEITADA'   ? 'selected' : '' ?>>Rejeitada</option>
                        <option value="CANCELADA"   <?= ($filtros['status'] ?? '') === 'CANCELADA'   ? 'selected' : '' ?>>Cancelada</option>
                        <option value="RASCUNHO"    <?= ($filtros['status'] ?? '') === 'RASCUNHO'    ? 'selected' : '' ?>>Rascunho</option>
                        <option value="PROCESSANDO" <?= ($filtros['status'] ?? '') === 'PROCESSANDO' ? 'selected' : '' ?>>Processando</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="f_municipio" class="form-label">Município</label>
                    <select class="form-select form-select-sm" id="f_municipio" name="municipio_id">
                        <option value="">Todos</option>
                        <?php foreach ($municipios as $m): ?>
                            <option value="<?= (int) ($m['id'] ?? 0) ?>" <?= ((string) ($filtros['municipio_id'] ?? '') === (string) ($m['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="f_ambiente" class="form-label">Ambiente</label>
                    <select class="form-select form-select-sm" id="f_ambiente" name="ambiente">
                        <option value="">Todos</option>
                        <option value="PRODUCAO"    <?= ($filtros['ambiente'] ?? '') === 'PRODUCAO'    ? 'selected' : '' ?>>Produção</option>
                        <option value="HOMOLOGACAO" <?= ($filtros['ambiente'] ?? '') === 'HOMOLOGACAO' ? 'selected' : '' ?>>Homologação</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex gap-1">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1" title="Filtrar">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="index.php?route=nfse/lista" class="btn btn-outline-secondary btn-sm" title="Limpar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h2 class="h6 mb-0"><i class="bi bi-table me-1 text-primary"></i>NFSes (<?= count($nfses) ?>)</h2>
            <a href="index.php?route=nfse/lista?<?= htmlspecialchars(http_build_query(array_filter($filtros, fn($v) => $v !== '')) . '&export=csv', ENT_QUOTES, 'UTF-8') ?>"
               class="btn btn-sm btn-outline-success" id="btnExportCsv">
                <i class="bi bi-filetype-csv me-1"></i> Exportar CSV
            </a>
        </div>
        <div class="card-body">
            <?php if (empty($nfses)): ?>
                <?php $message = 'Nenhuma NFSe encontrada no período/filtros selecionados.'; include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblNfse" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nº NFSe</th>
                                <th>RPS</th>
                                <th>Data</th>
                                <th>Tomador</th>
                                <th>Município</th>
                                <th class="text-end">Valor</th>
                                <th>Ambiente</th>
                                <th>Status</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($nfses as $n):
                                $numero = (string) ($n['numero_nfse'] ?? '');
                                $valor  = (float) ($n['valor_servicos'] ?? 0);
                                $status = (string) ($n['status'] ?? '');
                                $amb    = (string) ($n['ambiente'] ?? $ambiente);
                                $id     = (int) ($n['id'] ?? 0);
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($numero !== ''): ?>
                                            <strong><?= htmlspecialchars($numero, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= htmlspecialchars((string) ($n['serie_rps'] ?? '1') . ' / ', ENT_QUOTES, 'UTF-8') ?><?= (int) ($n['numero_rps'] ?? 0) ?>
                                        </small>
                                    </td>
                                    <td><?= Format::dateBR((string) ($n['data_emissao'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars($n['tomador_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($n['municipio_nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end fw-semibold <?= $status === 'CANCELADA' ? 'text-muted' : 'text-success' ?>">
                                        <?= Format::moneyBRL($valor) ?>
                                    </td>
                                    <td><?= $ambienteBadge[$amb] ?? '<span class="badge bg-secondary">—</span>' ?></td>
                                    <td><?= $statusBadge[$status] ?? '<span class="badge bg-secondary">—</span>' ?></td>
                                    <td class="text-center no-print">
                                        <a href="index.php?route=nfse/<?= $id ?>/visualizar" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if ($status === 'AUTORIZADA' && !empty($n['protocolo'])): ?>
                                            <a href="index.php?route=nfse/consultar/<?= $id ?>" class="btn btn-sm btn-outline-info" title="Consultar na prefeitura">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </a>
                                        <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Exportar CSV (client-side) — gera arquivo a partir da tabela visível.
    document.getElementById('btnExportCsv')?.addEventListener('click', function(e) {
        e.preventDefault();
        const linhas = [];
        document.querySelectorAll('#tblNfse thead th').forEach(function(th) {
            linhas.push('"' + th.textContent.trim().replace(/"/g, '""') + '"');
        });
        let csv = linhas.join(';') + '\n';
        document.querySelectorAll('#tblNfse tbody tr').forEach(function(tr) {
            const cols = [];
            tr.querySelectorAll('td').forEach(function(td, i) {
                // Última coluna (ações) é ignorada.
                if (i === 8) return;
                cols.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
            });
            csv += cols.join(';') + '\n';
        });
        const blob = new Blob(["\ufeff" + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'nfse_' + new Date().toISOString().slice(0,10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    });

    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblNfse').DataTable({
            pageLength: 25,
            order: [[2, 'desc']],
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/pt-br.json'
            },
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }
});
</script>
