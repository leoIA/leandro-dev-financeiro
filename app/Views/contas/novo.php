<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @var array  $old   Valores para repopular após erro de validação.
 */
$old = $old ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><i class="bi bi-bank me-2 text-primary"></i><?= htmlspecialchars($pageTitle ?? 'Nova Conta', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=contas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=contas" class="needs-validation" novalidate>
                <?= \App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="nome" name="nome" placeholder="Nome da conta" required maxlength="100"
                                   value="<?= htmlspecialchars($old['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <label for="nome">Nome da Conta <span class="text-danger">*</span></label>
                            <div class="invalid-feedback">Informe o nome da conta.</div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Selecione…</option>
                            <option value="BANCO"    <?= ($old['tipo'] ?? '') === 'BANCO'    ? 'selected' : '' ?>>Banco</option>
                            <option value="CAIXA"    <?= ($old['tipo'] ?? '') === 'CAIXA'    ? 'selected' : '' ?>>Caixa</option>
                            <option value="ASAAS"    <?= ($old['tipo'] ?? '') === 'ASAAS'    ? 'selected' : '' ?>>Asaas</option>
                            <option value="CARTEIRA" <?= ($old['tipo'] ?? '') === 'CARTEIRA' ? 'selected' : '' ?>>Carteira</option>
                            <option value="OUTRO"    <?= ($old['tipo'] ?? '') === 'OUTRO'    ? 'selected' : '' ?>>Outro</option>
                        </select>
                        <div class="invalid-feedback">Selecione o tipo.</div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="instituicao" name="instituicao" placeholder="Instituição"
                                   value="<?= htmlspecialchars($old['instituicao'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="100">
                            <label for="instituicao">Instituição</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="agencia" name="agencia" placeholder="Agência"
                                   value="<?= htmlspecialchars($old['agencia'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="20">
                            <label for="agencia">Agência</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="conta_numero" name="conta_numero" placeholder="Conta"
                                   value="<?= htmlspecialchars($old['conta_numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="30">
                            <label for="conta_numero">Conta</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="saldo_inicial" class="form-label">Saldo Inicial <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="saldo_inicial" name="saldo_inicial" required data-mask="money"
                                   value="<?= htmlspecialchars($old['saldo_inicial'] ?? '0,00', ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <div class="invalid-feedback">Informe um saldo inicial válido.</div>
                        <small class="form-text text-muted">Após criar a conta, o saldo só mudará via lançamentos.</small>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="ativo" name="ativo" value="1"
                                   <?= (($old['ativo'] ?? '1') === '1' || !isset($old['ativo'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa</label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="3" maxlength="500"><?= htmlspecialchars($old['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?route=contas" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Conta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
