<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $certificados Lista de certificados cadastrados (com dias_restantes e expirado).
 */
use App\Helpers\Format;

$certificados = $certificados ?? [];

// Encontra o certificado ativo (apenas 1 ativo por vez).
$certificadoAtivo = null;
foreach ($certificados as $c) {
    if ((int) ($c['ativo'] ?? 0) === 1) {
        $certificadoAtivo = $c;
        break;
    }
}
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h3 mb-0"><i class="bi bi-key me-2 text-primary"></i><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=nfse" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Voltar</a>
    </div>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-1"></i>
        Apenas <strong>1 certificado</strong> pode estar ativo. O upload de um novo certificado desativa o anterior automaticamente.
    </div>
    <div class="alert alert-warning">
        <i class="bi bi-shield-lock me-1"></i>
        O arquivo <code>.pfx</code> é armazenado em <code>storage/certificados/</code> (fora da web root). A senha é criptografada com <strong>AES-256-CBC</strong> usando chave derivada do próprio sistema.
    </div>

    <div class="row g-3">
        <!-- Upload -->
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light">
                    <h2 class="h6 mb-0"><i class="bi bi-cloud-upload me-1 text-primary"></i>Upload de Certificado</h2>
                </div>
                <div class="card-body">
                    <form method="post" action="index.php?route=nfse/certificado" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?= \App\Core\Csrf::field() ?>

                        <div class="mb-3">
                            <label for="certificado" class="form-label">Arquivo .pfx / .p12 <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="certificado" name="certificado" accept=".pfx,.p12" required>
                            <small class="form-text text-muted">Apenas arquivos <code>.pfx</code> ou <code>.p12</code> — máximo 100 KB.</small>
                        </div>

                        <div class="mb-3">
                            <label for="senha" class="form-label">Senha do certificado <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="senha" name="senha" required autocomplete="off"
                                       placeholder="Senha do .pfx">
                                <button type="button" class="btn btn-outline-secondary" id="btnToggleSenha" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">A senha <em>nunca</em> é armazenada em texto plano — apenas criptografada.</small>
                        </div>

                        <div class="mb-3">
                            <label for="nome" class="form-label">Nome amigável (opcional)</label>
                            <input type="text" class="form-control" id="nome" name="nome" maxlength="100"
                                   placeholder="Ex: MM Construtora — A1 2026">
                            <small class="form-text text-muted">Se vazio, usa o nome do arquivo.</small>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button type="reset" class="btn btn-outline-secondary btn-sm">Limpar</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-upload me-1"></i> Enviar Certificado
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista -->
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0"><i class="bi bi-list me-1 text-primary"></i>Certificados Cadastrados</h2>
                    <span class="badge bg-secondary"><?= count($certificados) ?> total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($certificados)): ?>
                        <div class="p-4">
                            <?php $message = 'Nenhum certificado cadastrado. Faça upload do primeiro .pfx ao lado.'; include __DIR__ . '/../partials/empty_state.php'; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Nome</th>
                                        <th>CNPJ Titular</th>
                                        <th>Validade</th>
                                        <th>Status</th>
                                        <th class="text-end">Dias restantes</th>
                                        <th class="text-center no-print">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($certificados as $c):
                                        $dias = $c['dias_restantes'] ?? null;
                                        $expirado = (bool) ($c['expirado'] ?? false);
                                        $ativo = (int) ($c['ativo'] ?? 0) === 1;

                                        if ($expirado) {
                                            $diasClass = 'text-danger fw-bold';
                                            $diasText  = 'Expirado';
                                        } elseif ($dias === null) {
                                            $diasClass = 'text-muted';
                                            $diasText  = '—';
                                        } elseif ($dias > 30) {
                                            $diasClass = 'text-success';
                                            $diasText  = (int) $dias . ' dias';
                                        } elseif ($dias >= 7) {
                                            $diasClass = 'text-warning fw-bold';
                                            $diasText  = (int) $dias . ' dias';
                                        } else {
                                            $diasClass = 'text-danger fw-bold';
                                            $diasText  = (int) $dias . ' dias';
                                        }
                                    ?>
                                        <tr>
                                            <td>
                                                <?= htmlspecialchars($c['nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                                                <?php if (!empty($c['titular_nome'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($c['titular_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><code><?= htmlspecialchars($c['cnpj_titular'] ?? '—', ENT_QUOTES, 'UTF-8') ?></code></td>
                                            <td><?= Format::dateBR((string) ($c['validade'] ?? '')) ?></td>
                                            <td>
                                                <?php if ($ativo): ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Ativo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Inativo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end <?= htmlspecialchars($diasClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($diasText, ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-center no-print">
                                                <?php if ($ativo): ?>
                                                    <form method="post" action="index.php?route=nfse/certificado-desativar" class="d-inline"
                                                          data-confirm="Desativar este certificado? Nenhuma NFSe poderá ser emitida até novo upload.">
                                                        <?= \App\Core\Csrf::field() ?>
                                                        <input type="hidden" name="certificado_id" value="<?= (int) ($c['id'] ?? 0) ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Desativar">
                                                            <i class="bi bi-x-circle"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($certificadoAtivo !== null): ?>
                    <div class="card-footer bg-success-subtle small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Certificado ativo: <strong><?= htmlspecialchars($certificadoAtivo['nome'] ?? '—', ENT_QUOTES, 'UTF-8') ?></strong>
                        — validade <?= Format::dateBR((string) ($certificadoAtivo['validade'] ?? '')) ?>
                    </div>
                <?php elseif (!empty($certificados)): ?>
                    <div class="card-footer bg-danger-subtle small">
                        <i class="bi bi-exclamation-triangle text-danger me-1"></i>
                        Nenhum certificado ativo. Faça upload de um novo .pfx para reativar.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle de senha.
    const btnToggle = document.getElementById('btnToggleSenha');
    const senhaInput = document.getElementById('senha');
    if (btnToggle && senhaInput) {
        btnToggle.addEventListener('click', function() {
            const icon = this.querySelector('i');
            if (senhaInput.type === 'password') {
                senhaInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                senhaInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    }

    // Confirmação para desativar certificado.
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const msg = this.getAttribute('data-confirm');
            if (!confirm(msg)) {
                e.preventDefault();
            }
        });
    });
});
</script>
