<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $conta  Dados da conta sendo editada.
 * @var array  $old    Valores para repopular após erro.
 */
use App\Helpers\Format;

$conta = $conta ?? [];
$old   = $old   ?? [];

$val = static function (string $field, $default = '') use ($conta, $old) {
    return $old[$field] ?? $conta[$field] ?? $default;
};

$saldoInicialOriginal = (float)($conta['saldo_inicial'] ?? 0);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-bank me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Editar Conta', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="btn-toolbar gap-2">
            <a href="/contas/<?= (int)($conta['id'] ?? 0) ?>/extrato" class="btn btn-outline-info btn-sm">
                <i class="bi bi-list-ul"></i> Ver Extrato
            </a>
            <a href="/contas" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/contas/<?= (int)($conta['id'] ?? 0) ?>" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="saldo_inicial" value="<?= htmlspecialchars((string)$saldoInicialOriginal, ENT_QUOTES, 'UTF-8') ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Nome da conta" required maxlength="100"
                                   value="<?= htmlspecialchars($val('nome'), ENT_QUOTES, 'UTF-8') ?>">
                            <label for="nome">Nome da Conta <span class="text-danger">*</span></label>
                            <div class="invalid-feedback">Informe o nome da conta.</div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Selecione…</option>
                            <option value="BANCO"    <?= $val('tipo') === 'BANCO'    ? 'selected' : '' ?>>Banco</option>
                            <option value="CAIXA"    <?= $val('tipo') === 'CAIXA'    ? 'selected' : '' ?>>Caixa</option>
                            <option value="ASAAS"    <?= $val('tipo') === 'ASAAS'    ? 'selected' : '' ?>>Asaas</option>
                            <option value="CARTEIRA" <?= $val('tipo') === 'CARTEIRA' ? 'selected' : '' ?>>Carteira</option>
                            <option value="OUTRO"    <?= $val('tipo') === 'OUTRO'    ? 'selected' : '' ?>>Outro</option>
                        </select>
                        <div class="invalid-feedback">Selecione o tipo.</div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="instituicao" name="instituicao" placeholder="Instituição"
                                   value="<?= htmlspecialchars($val('instituicao'), ENT_QUOTES, 'UTF-8') ?>" maxlength="100">
                            <label for="instituicao">Instituição</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="agencia" name="agencia" placeholder="Agência"
                                   value="<?= htmlspecialchars($val('agencia'), ENT_QUOTES, 'UTF-8') ?>" maxlength="20">
                            <label for="agencia">Agência</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="conta_numero" name="conta_numero" placeholder="Conta"
                                   value="<?= htmlspecialchars($val('conta_numero'), ENT_QUOTES, 'UTF-8') ?>" maxlength="30">
                            <label for="conta_numero">Conta</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="saldo_inicial_display" class="form-label">Saldo Inicial</label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="saldo_inicial_display" readonly disabled
                                   value="<?= htmlspecialchars(Format::moneyBRLPositive($saldoInicialOriginal), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <small class="form-text text-muted">Readonly — só alterado via lançamentos.</small>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1"
                                   <?= ($val('ativo', '1') === '1' || $val('ativo', 1) == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3" maxlength="500"><?= htmlspecialchars($val('descricao'), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="/contas" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Alterações
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
