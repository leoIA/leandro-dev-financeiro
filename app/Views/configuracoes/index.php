<?php declare(strict_types=1);
/** @var array $config */
/** @var string $pageTitle */
use App\Core\Auth;
use App\Core\Csrf;
$cfg = $config ?? [];
$g = function($key, $default = '') use ($cfg) { return $cfg[$key] ?? $default; };
$logoPath = $g('empresa_logo_path');
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Configurações', ENT_QUOTES, 'UTF-8') ?></h1>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <ul class="nav nav-pills mb-3" id="cfgTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-empresa" data-bs-toggle="pill" data-bs-target="#pnl-empresa" type="button" role="tab" aria-selected="true">
                        <i class="bi bi-building"></i> Empresa
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-seguranca" data-bs-toggle="pill" data-bs-target="#pnl-seguranca" type="button" role="tab">
                        <i class="bi bi-shield-lock"></i> Segurança
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-backup" data-bs-toggle="pill" data-bs-target="#pnl-backup" type="button" role="tab">
                        <i class="bi bi-database-check"></i> Backup
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-sistema" data-bs-toggle="pill" data-bs-target="#pnl-sistema" type="button" role="tab">
                        <i class="bi bi-gear"></i> Sistema
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="cfgTabsContent">

                <!-- EMPRESA -->
                <div class="tab-pane fade show active" id="pnl-empresa" role="tabpanel">
                    <form method="post" action="/configuracoes/empresa" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <?= App\Core\Csrf::field() ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="empresa_razao_social" class="form-label">Razão Social</label>
                                <input type="text" class="form-control" id="empresa_razao_social" name="empresa_razao_social" maxlength="200"
                                       value="<?= htmlspecialchars($g('empresa_razao_social'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="empresa_cnpj" class="form-label">CNPJ</label>
                                <input type="text" class="form-control" id="empresa_cnpj" name="empresa_cnpj" data-mask="cnpj"
                                       value="<?= htmlspecialchars($g('empresa_cnpj'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="empresa_telefone" class="form-label">Telefone</label>
                                <input type="text" class="form-control" id="empresa_telefone" name="empresa_telefone" data-mask="phone"
                                       value="<?= htmlspecialchars($g('empresa_telefone'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="empresa_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="empresa_email" name="empresa_email"
                                       value="<?= htmlspecialchars($g('empresa_email'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>

                            <div class="col-md-12">
                                <label for="empresa_logo" class="form-label">Logo da Empresa</label>
                                <input type="file" class="form-control" id="empresa_logo" name="empresa_logo" accept="image/png,image/jpeg,image/jpg,image/svg+xml">
                                <small class="form-text text-muted">JPG, PNG ou SVG — máximo 2MB. Será redimensionada para 200x60.</small>
                                <?php if (!empty($logoPath)): ?>
                                    <div class="mt-2">
                                        <span class="text-muted small">Logo atual:</span><br>
                                        <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" style="max-height:60px;max-width:200px" class="border rounded p-1">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="empresa_endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="empresa_endereco" name="empresa_endereco" maxlength="200"
                                       value="<?= htmlspecialchars($g('empresa_endereco'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="empresa_numero" class="form-label">Número</label>
                                <input type="text" class="form-control" id="empresa_numero" name="empresa_numero" maxlength="20"
                                       value="<?= htmlspecialchars($g('empresa_numero'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="empresa_bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="empresa_bairro" name="empresa_bairro" maxlength="100"
                                       value="<?= htmlspecialchars($g('empresa_bairro'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="empresa_cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="empresa_cidade" name="empresa_cidade" maxlength="100"
                                       value="<?= htmlspecialchars($g('empresa_cidade'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-md-2">
                                <label for="empresa_uf" class="form-label">UF</label>
                                <select class="form-select" id="empresa_uf" name="empresa_uf">
                                    <option value="">—</option>
                                    <?php
                                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                    foreach ($ufs as $uf):
                                    ?>
                                        <option value="<?= $uf ?>" <?= ($g('empresa_uf') === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="empresa_cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="empresa_cep" name="empresa_cep" data-mask="cep"
                                       value="<?= htmlspecialchars($g('empresa_cep'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar Empresa</button>
                        </div>
                    </form>
                </div>

                <!-- SEGURANÇA -->
                <div class="tab-pane fade" id="pnl-seguranca" role="tabpanel">
                    <form method="post" action="/configuracoes/seguranca" class="needs-validation" novalidate>
                        <?= App\Core\Csrf::field() ?>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="session_timeout_min" class="form-label">Timeout de Sessão (minutos)</label>
                                <input type="number" class="form-control" id="session_timeout_min" name="session_timeout_min" min="1" max="1440"
                                       value="<?= htmlspecialchars($g('session_timeout_min', '30'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Tempo de inatividade antes do logout automático.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="tentativas_login_max" class="form-label">Tentativas Máximas de Login</label>
                                <input type="number" class="form-control" id="tentativas_login_max" name="tentativas_login_max" min="1" max="20"
                                       value="<?= htmlspecialchars($g('tentativas_login_max', '5'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Antes do bloqueio temporário.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="bloqueio_login_min" class="form-label">Bloqueio de Login (minutos)</label>
                                <input type="number" class="form-control" id="bloqueio_login_min" name="bloqueio_login_min" min="1" max="1440"
                                       value="<?= htmlspecialchars($g('bloqueio_login_min', '15'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Duração do bloqueio após exceder tentativas.</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar Segurança</button>
                        </div>
                    </form>
                </div>

                <!-- BACKUP -->
                <div class="tab-pane fade" id="pnl-backup" role="tabpanel">
                    <form method="post" action="/configuracoes/backup" class="needs-validation" novalidate>
                        <?= App\Core\Csrf::field() ?>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="backup_automatico" name="backup_automatico" value="1"
                                           <?= ($g('backup_automatico', '0') === '1' || $g('backup_automatico', 0) == 1) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="backup_automatico">Backup Automático Mensal</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label for="backup_manter_ultimos" class="form-label">Manter Últimos</label>
                                <input type="number" class="form-control" id="backup_manter_ultimos" name="backup_manter_ultimos" min="1" max="100"
                                       value="<?= htmlspecialchars($g('backup_manter_ultimos', '10'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Quantidade de backups a preservar.</small>
                            </div>
                            <div class="col-md-4">
                                <label for="backup_dia_mes" class="form-label">Dia do Mês</label>
                                <input type="number" class="form-control" id="backup_dia_mes" name="backup_dia_mes" min="1" max="28"
                                       value="<?= htmlspecialchars($g('backup_dia_mes', '1'), ENT_QUOTES, 'UTF-8') ?>">
                                <small class="form-text text-muted">Dia do mês para backup automático (1 a 28).</small>
                            </div>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i> Salvar Backup</button>
                        </div>
                    </form>
                </div>

                <!-- SISTEMA -->
                <div class="tab-pane fade" id="pnl-sistema" role="tabpanel">
                    <form method="post" action="/configuracoes/sistema" class="needs-validation" novalidate>
                        <?= App\Core\Csrf::field() ?>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="moeda" class="form-label">Moeda</label>
                                <input type="text" class="form-control" id="moeda" name="moeda" value="BRL" readonly>
                                <small class="form-text text-muted">Somente leitura.</small>
                            </div>
                            <div class="col-md-3">
                                <label for="locale" class="form-label">Locale</label>
                                <input type="text" class="form-control" id="locale" name="locale" value="pt_BR" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="fuso_horario" class="form-label">Fuso Horário</label>
                                <input type="text" class="form-control" id="fuso_horario" name="fuso_horario" value="America/Sao_Paulo" readonly>
                            </div>
                            <div class="col-md-3">
                                <label for="versao" class="form-label">Versão</label>
                                <input type="text" class="form-control" id="versao" name="versao" value="1.0.0" readonly>
                            </div>
                        </div>
                        <hr>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            As configurações de sistema são fixas e não podem ser alteradas.
                        </div>
                    </form>
                </div>

            </div>

            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <form method="post" action="/configuracoes/restaurar-padroes" class="d-inline" id="formReset">
                    <?= App\Core\Csrf::field() ?>
                    <button type="button" class="btn btn-outline-danger" id="btnRestaurar">
                        <i class="bi bi-arrow-counterclockwise"></i> Restaurar Padrões
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal duplo de confirmação -->
<div class="modal fade" id="modalReset1" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirmar Restauração</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Tem certeza que deseja restaurar <strong>todas</strong> as configurações para os valores padrão?</p>
                <p class="text-danger">Esta ação não pode ser desfeita. Configurações de Empresa, Segurança e Backup serão redefinidas.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnResetContinue">Sim, continuar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReset2" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-shield-exclamation"></i> Confirmação Final</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <p>Digite <strong>RESTAURAR</strong> abaixo para confirmar:</p>
                <input type="text" class="form-control" id="resetConfirmText" placeholder="RESTAURAR">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnResetConfirm" disabled>Restaurar Padrões</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnRestaurar = document.getElementById('btnRestaurar');
    const modal1 = new bootstrap.Modal(document.getElementById('modalReset1'));
    const modal2 = new bootstrap.Modal(document.getElementById('modalReset2'));
    const btnContinue = document.getElementById('btnResetContinue');
    const btnConfirm = document.getElementById('btnResetConfirm');
    const txtConfirm = document.getElementById('resetConfirmText');
    const formReset = document.getElementById('formReset');

    btnRestaurar.addEventListener('click', function() { modal1.show(); });
    btnContinue.addEventListener('click', function() {
        modal1.hide();
        modal2.show();
    });
    txtConfirm.addEventListener('input', function() {
        btnConfirm.disabled = (this.value.trim().toUpperCase() !== 'RESTAURAR');
    });
    btnConfirm.addEventListener('click', function() {
        formReset.submit();
    });
});
</script>
