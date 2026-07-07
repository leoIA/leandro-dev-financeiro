<?php declare(strict_types=1);
/** @var array $programada */
/** @var array $contas */
/** @var array $planos */
/** @var array $clientesFornecedores */
/** @var string $pageTitle */
/** @var bool $bloquearTipo */
use App\Helpers\Format;
use App\Core\Auth;
$p = $programada ?? [];
$old = $old ?? [];
$bloquearTipo = $bloquearTipo ?? (!empty($p['parcelas_geradas']) && (int)$p['parcelas_geradas'] > 0);
$val = function($field, $default = '') use ($p, $old) {
    return $old[$field] ?? $p[$field] ?? $default;
};
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Editar Programação', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="/contas-programadas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if ($bloquearTipo): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    Esta programação já gerou lançamentos. O <strong>Tipo</strong> não pode mais ser alterado.
                </div>
            <?php endif; ?>

            <form method="post" action="/contas-programadas/<?= (int)($p['id'] ?? 0) ?>" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>
                <input type="hidden" name="_method" value="PUT">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required maxlength="200"
                               value="<?= htmlspecialchars($val('descricao'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a descrição.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="conta_id" class="form-label">Conta <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach (($contas ?? []) as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)$val('conta_id') === (int)$c['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione a conta.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="plano_conta_id" class="form-label">Plano de Contas <span class="text-danger">*</span></label>
                        <select class="form-select" id="plano_conta_id" name="plano_conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach (($planos ?? []) as $pc): ?>
                                <?php $indent = str_repeat('— ', (int)($pc['nivel'] ?? 0)); ?>
                                <option value="<?= (int)$pc['id'] ?>" <?= ((int)$val('plano_conta_id') === (int)$pc['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($indent . ($pc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Selecione o plano de contas.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="cliente_fornecedor_id" class="form-label">Cliente / Fornecedor</label>
                        <select class="form-select" id="cliente_fornecedor_id" name="cliente_fornecedor_id">
                            <option value="">(opcional)</option>
                            <?php foreach (($clientesFornecedores ?? []) as $cf): ?>
                                <option value="<?= (int)$cf['id'] ?>" <?= ((int)$val('cliente_fornecedor_id') === (int)$cf['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($cf['nome_razao_social'] ?? '') . ' — ' . ($cf['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label d-block">Tipo <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_receita" value="RECEITA" required
                                   <?= ($val('tipo', 'DESPESA') === 'RECEITA') ? 'checked' : '' ?>
                                   <?= $bloquearTipo ? 'disabled' : '' ?>>
                            <label class="form-check-label text-success" for="tipo_receita">Receita</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_despesa" value="DESPESA" required
                                   <?= ($val('tipo', 'DESPESA') === 'DESPESA') ? 'checked' : '' ?>
                                   <?= $bloquearTipo ? 'disabled' : '' ?>>
                            <label class="form-check-label text-danger" for="tipo_despesa">Despesa</label>
                        </div>
                        <?php if ($bloquearTipo): ?>
                            <input type="hidden" name="tipo" value="<?= htmlspecialchars($val('tipo', 'DESPESA'), ENT_QUOTES, 'UTF-8') ?>">
                        <?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor" name="valor" required data-mask="money"
                                   value="<?= htmlspecialchars($val('valor') ? Format::moneyBRL((float)$val('valor')) : '', ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <div class="invalid-feedback">Informe um valor válido.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data de Início <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" required
                               value="<?= htmlspecialchars($val('data_inicio'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a data de início.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_termino" class="form-label">Data de Término</label>
                        <input type="date" class="form-control" id="data_termino" name="data_termino"
                               value="<?= htmlspecialchars($val('data_termino'), ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Opcional. Em branco = indefinido.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="frequencia" class="form-label">Frequência <span class="text-danger">*</span></label>
                        <select class="form-select" id="frequencia" name="frequencia" required>
                            <?php
                            $freqs = ['DIARIO' => 'Diária', 'SEMANAL' => 'Semanal', 'MENSAL' => 'Mensal', 'ANUAL' => 'Anual', 'UNICO' => 'Único (uma vez)'];
                            foreach ($freqs as $k => $v):
                            ?>
                                <option value="<?= $k ?>" <?= ($val('frequencia', 'MENSAL') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="dia_referencia" class="form-label">Dia de Referência</label>
                        <input type="number" class="form-control" id="dia_referencia" name="dia_referencia" min="1" max="31"
                               value="<?= htmlspecialchars($val('dia_referencia', '1'), ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted" id="dia_referencia_help">Dia do mês (1 a 31).</small>
                    </div>

                    <div class="col-md-3">
                        <label for="parcelas_total" class="form-label">Total de Parcelas</label>
                        <input type="number" class="form-control" id="parcelas_total" name="parcelas_total" min="1"
                               value="<?= htmlspecialchars($val('parcelas_total'), ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Opcional. Em branco = ilimitado.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                            <?php
                            $formas = ['PIX' => 'PIX', 'BOLETO' => 'Boleto', 'CARTAO' => 'Cartão', 'DINHEIRO' => 'Dinheiro', 'TRANSFERENCIA' => 'Transferência', 'DEBITO' => 'Débito Automático'];
                            foreach ($formas as $k => $v):
                            ?>
                                <option value="<?= $k ?>" <?= ($val('forma_pagamento', 'BOLETO') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-center">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?= ($val('ativo', '1') === '1' || $val('ativo', 1) == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">
                        Parcelas geradas: <strong><?= (int)$val('parcelas_geradas', 0) ?></strong>
                        <?php if (!empty($p['ultima_geracao'])): ?>
                            | Última geração: <strong><?= Format::dateBR($p['ultima_geracao']) ?></strong>
                        <?php endif; ?>
                        <?php if (!empty($p['proxima_geracao'])): ?>
                            | Próxima: <strong><?= Format::dateBR($p['proxima_geracao']) ?></strong>
                        <?php endif; ?>
                    </small>
                    <div class="d-flex gap-2">
                        <a href="/contas-programadas" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const freq = document.getElementById('frequencia');
    const diaLabel = document.querySelector('label[for="dia_referencia"]');
    const diaHelp = document.getElementById('dia_referencia_help');
    const diaInput = document.getElementById('dia_referencia');
    function updateDiaLabel() {
        const v = freq.value;
        const labels = {
            DIARIO: 'Dia do mês',
            SEMANAL: 'Dia da semana (1=Dom … 7=Sáb)',
            MENSAL: 'Dia do mês (1 a 31)',
            ANUAL: 'Dia do mês',
            UNICO: 'Não se aplica'
        };
        const helpText = {
            DIARIO: 'Recorrência diária.',
            SEMANAL: 'Informe 1 (domingo) a 7 (sábado).',
            MENSAL: 'Dia do mês (1 a 31).',
            ANUAL: 'Dia do mês — combinado com o mês de início.',
            UNICO: 'Será gerado um único lançamento na data de início.'
        };
        if (diaLabel) diaLabel.textContent = labels[v] || 'Dia de Referência';
        if (diaHelp) diaHelp.textContent = helpText[v] || '';
        if (diaInput) diaInput.disabled = (v === 'UNICO');
    }
    freq.addEventListener('change', updateDiaLabel);
    updateDiaLabel();
});
</script>
