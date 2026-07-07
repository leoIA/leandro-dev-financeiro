<?php declare(strict_types=1);
/** @var array $backups */
/** @var string $pageTitle */
use App\Helpers\Format;
function formatBytes($bytes) {
    $bytes = (float)$bytes;
    if ($bytes < 1024) return number_format($bytes, 0, ',', '.') . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    if ($bytes < 1073741824) return number_format($bytes / 1048576, 2, ',', '.') . ' MB';
    return number_format($bytes / 1073741824, 2, ',', '.') . ' GB';
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Backup / Restore', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle-fill"></i>
        Backups são salvos em <code>/storage/backups/</code>. Mantenha cópias externas para segurança.
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Backup Manual</h5>
                <p class="text-muted small mb-0">Gere um arquivo SQL completo do banco de dados agora.</p>
            </div>
            <form method="post" action="/backups/gerar" class="d-inline">
                <?= App\Core\Csrf::field() ?>
                <button type="submit" class="btn btn-primary" data-confirm="Gerar um novo backup agora?">
                    <i class="bi bi-database-add"></i> Gerar Backup Agora
                </button>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <strong>Backups Disponíveis</strong>
        </div>
        <div class="card-body">
            <?php if (empty($backups ?? [])): ?>
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblBackups" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Arquivo</th>
                                <th>Tamanho</th>
                                <th>Data</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td>
                                        <i class="bi bi-file-earmark-zip text-secondary"></i>
                                        <?= htmlspecialchars($b['arquivo'] ?? $b['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><?= formatBytes($b['tamanho'] ?? 0) ?></td>
                                    <td>
                                        <?php
                                        $data = $b['data'] ?? $b['criado_em'] ?? '';
                                        echo $data ? Format::dateBR($data) . ' ' . date('H:i', strtotime($data)) : '';
                                        ?>
                                    </td>
                                    <td class="text-center no-print">
                                        <a href="/backups/<?= urlencode($b['arquivo'] ?? $b['nome'] ?? '') ?>/download"
                                           class="btn btn-sm btn-outline-primary" title="Download" data-confirm="Baixar este arquivo?">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Restaurar"
                                                data-bs-toggle="modal" data-bs-target="#modalRestore"
                                                data-arquivo="<?= htmlspecialchars($b['arquivo'] ?? $b['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <form method="post" action="/backups/<?= urlencode($b['arquivo'] ?? $b['nome'] ?? '') ?>/excluir" class="d-inline">
                                            <?= App\Core\Csrf::field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Excluir"
                                                    data-confirm="Excluir este backup permanentemente?">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<!-- Modal Restaurar (duplo) -->
<div class="modal fade" id="modalRestore" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Restaurar Backup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p class="text-danger"><strong>Atenção:</strong> restaurar um backup substituirá todos os dados atuais do banco.</p>
                <p>Arquivo selecionado: <code id="restoreFile"></code></p>
                <p>Para confirmar, digite <strong>RESTAURAR</strong> abaixo:</p>
                <input type="text" class="form-control" id="restoreConfirmText" placeholder="RESTAURAR">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <form method="post" action="/backups/restaurar" id="formRestore">
                    <?= App\Core\Csrf::field() ?>
                    <input type="hidden" name="arquivo" id="restoreArquivoInput" value="">
                    <button type="submit" class="btn btn-warning" id="btnRestoreConfirm" disabled>
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar Agora
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblBackups').DataTable({
            order: [[2, 'desc']],
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    const modalRestore = document.getElementById('modalRestore');
    if (modalRestore) {
        modalRestore.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const arquivo = btn.dataset.arquivo || '';
            document.getElementById('restoreFile').textContent = arquivo;
            document.getElementById('restoreArquivoInput').value = arquivo;
            document.getElementById('restoreConfirmText').value = '';
            document.getElementById('btnRestoreConfirm').disabled = true;
        });
        const txt = document.getElementById('restoreConfirmText');
        const btn = document.getElementById('btnRestoreConfirm');
        txt.addEventListener('input', function() {
            btn.disabled = (this.value.trim().toUpperCase() !== 'RESTAURAR');
        });
    }
});
</script>
