<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var array $old */
use App\Core\Auth;
$old = $old ?? [];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Novo Cliente / Fornecedor', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="/clientes-fornecedores" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Voltar
        </a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post" action="/clientes-fornecedores" class="needs-validation" novalidate>
                <?= App\Core\Csrf::field() ?>

                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Selecione…</option>
                            <option value="CLIENTE" <?= ($old['tipo'] ?? '') === 'CLIENTE' ? 'selected' : '' ?>>Cliente</option>
                            <option value="FORNECEDOR" <?= ($old['tipo'] ?? '') === 'FORNECEDOR' ? 'selected' : '' ?>>Fornecedor</option>
                            <option value="AMBOS" <?= ($old['tipo'] ?? '') === 'AMBOS' ? 'selected' : '' ?>>Ambos</option>
                        </select>
                        <div class="invalid-feedback">Selecione o tipo.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label d-block">Tipo de Pessoa <span class="text-danger">*</span></label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tp_fisica" value="FISICA" required
                                   <?= (($old['tipo_pessoa'] ?? 'FISICA') === 'FISICA') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tp_fisica">Pessoa Física</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo_pessoa" id="tp_juridica" value="JURIDICA" required
                                   <?= (($old['tipo_pessoa'] ?? '') === 'JURIDICA') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tp_juridica">Pessoa Jurídica</label>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label for="cpf_cnpj" class="form-label">
                            <span data-label-cpf>CPF</span> / <span data-label-cnpj>CNPJ</span>
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="cpf_cnpj" name="cpf_cnpj" data-mask="cpf-cnpj" required
                               value="<?= htmlspecialchars($old['cpf_cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="000.000.000-00">
                        <div class="invalid-feedback">Informe um CPF/CNPJ válido.</div>
                    </div>

                    <div class="col-md-12">
                        <label for="nome_razao_social" class="form-label">
                            <span data-label-nome>Nome / Razão Social</span> <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" id="nome_razao_social" name="nome_razao_social" required maxlength="200"
                               value="<?= htmlspecialchars($old['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="invalid-feedback">Informe o nome ou razão social.</div>
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email"
                               value="<?= htmlspecialchars($old['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="telefone" class="form-label">Telefone</label>
                        <input type="text" class="form-control" id="telefone" name="telefone" data-mask="phone"
                               value="<?= htmlspecialchars($old['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="(00) 0000-0000">
                    </div>

                    <div class="col-md-3">
                        <label for="celular" class="form-label">Celular</label>
                        <input type="text" class="form-control" id="celular" name="celular" data-mask="phone"
                               value="<?= htmlspecialchars($old['celular'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="(00) 00000-0000">
                    </div>

                    <hr class="my-2">

                    <div class="col-md-3">
                        <label for="cep" class="form-label">CEP</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="cep" name="cep" data-mask="cep" maxlength="9"
                                   value="<?= htmlspecialchars($old['cep'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                   placeholder="00000-000">
                            <button type="button" class="btn btn-outline-secondary" id="btnBuscarCep">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <small class="form-text text-muted">Digite o CEP e saia do campo para buscar.</small>
                    </div>

                    <div class="col-md-6">
                        <label for="endereco" class="form-label">Endereço</label>
                        <input type="text" class="form-control" id="endereco" name="endereco" maxlength="200"
                               value="<?= htmlspecialchars($old['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="numero" class="form-label">Número</label>
                        <input type="text" class="form-control" id="numero" name="numero" maxlength="20"
                               value="<?= htmlspecialchars($old['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="complemento" class="form-label">Complemento</label>
                        <input type="text" class="form-control" id="complemento" name="complemento" maxlength="100"
                               value="<?= htmlspecialchars($old['complemento'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-3">
                        <label for="bairro" class="form-label">Bairro</label>
                        <input type="text" class="form-control" id="bairro" name="bairro" maxlength="100"
                               value="<?= htmlspecialchars($old['bairro'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-4">
                        <label for="cidade" class="form-label">Cidade</label>
                        <input type="text" class="form-control" id="cidade" name="cidade" maxlength="100"
                               value="<?= htmlspecialchars($old['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-md-2">
                        <label for="uf" class="form-label">UF</label>
                        <select class="form-select" id="uf" name="uf" maxlength="2">
                            <option value="">—</option>
                            <?php
                            $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                            foreach ($ufs as $uf):
                            ?>
                                <option value="<?= $uf ?>" <?= (($old['uf'] ?? '') === $uf) ? 'selected' : '' ?>><?= $uf ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="observacao" class="form-label">Observação</label>
                        <textarea class="form-control" id="observacao" name="observacao" rows="2" maxlength="500"><?= htmlspecialchars($old['observacao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                   <?= (!isset($old['ativo']) || $old['ativo'] === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativo">Ativo</label>
                        </div>
                    </div>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="/clientes-fornecedores" class="btn btn-outline-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="/public/js/viacep.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const radios = document.querySelectorAll('input[name="tipo_pessoa"]');
    const cpfCnpj = document.getElementById('cpf_cnpj');
    const labelCpf = document.querySelector('[data-label-cpf]');
    const labelCnpj = document.querySelector('[data-label-cnpj]');
    const labelNome = document.querySelector('[data-label-nome]');

    function aplicarTipoPessoa() {
        const fisica = document.querySelector('input[name="tipo_pessoa"]:checked')?.value === 'FISICA';
        if (cpfCnpj) {
            cpfCnpj.value = '';
            cpfCnpj.placeholder = fisica ? '000.000.000-00' : '00.000.000/0000-00';
        }
        if (labelCpf) labelCpf.style.display = fisica ? '' : 'none';
        if (labelCnpj) labelCnpj.style.display = fisica ? 'none' : '';
        if (labelNome) labelNome.textContent = fisica ? 'Nome Completo' : 'Razão Social';
    }
    radios.forEach(function(r) { r.addEventListener('change', aplicarTipoPessoa); });
    aplicarTipoPessoa();
});
</script>
