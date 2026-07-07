<?php declare(strict_types=1);
/** @var array $contas */
/** @var array $planos */
/** @var array $clientesFornecedores */
/** @var string $pageTitle */
/** @var array $old */
use App\Helpers\Format;
use App\Core\Auth;
$old = $old ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Nova Programação', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=contas-programadas" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="index.php?route=contas-programadas" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="descricao" class="form-label">Descrição <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required maxlength="200"
                               value="<?= htmlspecialchars($old['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a descrição.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="conta_id" class="form-label">Conta <span class="text-danger">*</span></label>
                        <select class="form-select" id="conta_id" name="conta_id" required>
                            <option value="">Selecione…</option>
                            <?php foreach (($contas ?? []) as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= ((int)($old['conta_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
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
                                <option value="<?= (int)$pc['id'] ?>" <?= ((int)($old['plano_conta_id'] ?? 0) === (int)$pc['id']) ? 'selected' : '' ?>>
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
                                <option value="<?= (int)$cf['id'] ?>" <?= ((int)($old['cliente_fornecedor_id'] ?? 0) === (int)$cf['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(($cf['nome_razao_social'] ?? '') . ' — ' . ($cf['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label d-block">Tipo <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_receita" value="RECEITA" required
                                   <?= (($old['tipo'] ?? 'DESPESA') === 'RECEITA') ? 'checked' : '' ?>>
                            <label class="form-check-label text-success" for="tipo_receita">Receita</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo_despesa" value="DESPESA" required
                                   <?= (($old['tipo'] ?? 'DESPESA') === 'DESPESA') ? 'checked' : '' ?>>
                            <label class="form-check-label text-danger" for="tipo_despesa">Despesa</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label for="valor" class="form-label">Valor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="text" class="form-control text-end" id="valor" name="valor" required data-mask="money"
                                   value="<?= htmlspecialchars($old['valor'] ?? '', ENT_QUOTES, 'UTF-8') ?>" inputmode="decimal">
                        </div>
                        <div class="invalid-feedback">Informe um valor válido.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_inicio" class="form-label">Data de Início <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="data_inicio" name="data_inicio" required
                               value="<?= htmlspecialchars($old['data_inicio'] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe a data de início.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="data_termino" class="form-label">Data de Término</label>
                        <input type="date" class="form-control" id="data_termino" name="data_termino"
                               value="<?= htmlspecialchars($old['data_termino'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Opcional. Em branco = indefinido.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="frequencia" class="form-label">Frequência <span class="text-danger">*</span></label>
                        <select class="form-select" id="frequencia" name="frequencia" required>
                            <?php
                            $freqs = ['DIARIO' => 'Diária', 'SEMANAL' => 'Semanal', 'MENSAL' => 'Mensal', 'ANUAL' => 'Anual', 'UNICO' => 'Único (uma vez)'];
                            foreach ($freqs as $k => $v):
                            ?>
                                <option value="<?= $k ?>" <?= (($old['frequencia'] ?? 'MENSAL') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="dia_referencia" class="form-label">Dia de Referência</label>
                        <input type="number" class="form-control" id="dia_referencia" name="dia_referencia" min="1" max="31"
                               value="<?= htmlspecialchars($old['dia_referencia'] ?? '1', ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted" id="dia_referencia_help">Dia do mês (1 a 31).</small>
                    </div>

                    <div class="col-md-3">
                        <label for="parcelas_total" class="form-label">Total de Parcelas</label>
                        <input type="number" class="form-control" id="parcelas_total" name="parcelas_total" min="1"
                               value="<?= htmlspecialchars($old['parcelas_total'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <small class="form-text text-muted">Opcional. Em branco = ilimitado.</small>
                    </div>

                    <div class="col-md-3">
                        <label for="forma_pagamento" class="form-label">Forma de Pagamento</label>
                        <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                            <?php
                            $formas = ['PIX' => 'PIX', 'BOLETO' => 'Boleto', 'CARTAO' => 'Cartão', 'DINHEIRO' => 'Dinheiro', 'TRANSFERENCIA' => 'Transferência', 'DEBITO' => 'Débito Automático'];
                            foreach ($formas as $k => $v):
                            ?>
                                <option value="<?= $k ?>" <?= (($old['forma_pagamento'] ?? 'BOLETO') === $k) ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1" <?= (($old['ativo'] ?? '1') === '1' || !isset($old['ativo'])) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativa (gerar lançamentos automaticamente)</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="index.php?route=contas-programadas" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar Programação
                    </button>
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
