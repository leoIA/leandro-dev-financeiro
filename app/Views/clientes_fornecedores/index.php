<?php declare(strict_types=1);
/** @var array $clientesFornecedores */
/** @var string $pageTitle */
/** @var array $filtros */
use App\Helpers\Format;
$filtros = $filtros ?? ['tipo' => '', 'nome' => ''];
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h3 mb-0"><?= htmlspecialchars($pageTitle ?? 'Clientes / Fornecedores', ENT_QUOTES, 'UTF-8') ?></h1>
        <a href="index.php?route=clientes-fornecedores/novo" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Novo
        </a>
    </div>

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="index.php?route=clientes-fornecedores" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label for="f_tipo" class="form-label">Tipo</label>
                    <select class="form-select form-select-sm" id="f_tipo" name="tipo">
                        <option value="">Todos</option>
                        <option value="CLIENTE" <?= ($filtros['tipo'] ?? '') === 'CLIENTE' ? 'selected' : '' ?>>Cliente</option>
                        <option value="FORNECEDOR" <?= ($filtros['tipo'] ?? '') === 'FORNECEDOR' ? 'selected' : '' ?>>Fornecedor</option>
                        <option value="AMBOS" <?= ($filtros['tipo'] ?? '') === 'AMBOS' ? 'selected' : '' ?>>Ambos</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="f_nome" class="form-label">Nome / Razão Social</label>
                    <input type="text" class="form-control form-control-sm" id="f_nome" name="nome"
                           value="<?= htmlspecialchars($filtros['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Busca por nome ou CPF/CNPJ">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary btn-sm flex-grow-1">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="index.php?route=clientes-fornecedores" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-lg"></i> Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <?php if (empty($clientesFornecedores ?? [])): ?>
                <?php include __DIR__ . '/../partials/empty_state.php'; ?>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="tblClientes" class="table table-striped table-hover" style="width:100%">
                        <thead>
                            <tr>
                                <th>Nome / Razão Social</th>
                                <th>Tipo Pessoa</th>
                                <th>CPF / CNPJ</th>
                                <th>Tipo</th>
                                <th>Telefone</th>
                                <th>Email</th>
                                <th class="text-center no-print">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientesFornecedores as $cf): ?>
                                <tr>
                                    <td>
                                        <?= htmlspecialchars($cf['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        <?php if (empty($cf['ativo'])): ?>
                                            <span class="badge bg-secondary ms-1">Inativo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($cf['tipo_pessoa'] ?? '') === 'FISICA'): ?>
                                            <span class="badge bg-info text-dark">Pessoa Física</span>
                                        <?php else: ?>
                                            <span class="badge bg-primary">Pessoa Jurídica</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($cf['cpf_cnpj'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php
                                        $tipo = $cf['tipo'] ?? '';
                                        $badges = [
                                            'CLIENTE' => '<span class="badge bg-success">Cliente</span>',
                                            'FORNECEDOR' => '<span class="badge bg-warning text-dark">Fornecedor</span>',
                                            'AMBOS' => '<span class="badge bg-secondary">Cliente + Fornecedor</span>',
                                        ];
                                        echo $badges[$tipo] ?? htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($cf['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($cf['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center no-print">
                                        <a href="index.php?route=clientes-fornecedores/<?= (int)$cf['id'] ?>/editar" class="btn btn-sm btn-outline-primary" title="Editar">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if (!empty($cf['ativo'])): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Desativar"
                                                    data-bs-toggle="modal" data-bs-target="#confirmDelete"
                                                    data-action-url="/clientes-fornecedores/<?= (int)$cf['id'] ?>/desativar"
                                                    data-item-name="<?= htmlspecialchars($cf['nome_razao_social'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
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

<?php
$modalId = 'confirmDelete';
$modalTitle = 'Desativar Cliente / Fornecedor';
$modalMessage = 'Tem certeza que deseja desativar este registro? Os lançamentos vinculados permanecerão.';
$actionUrl = '';
$csrfToken = \App\Core\Csrf::token();
include __DIR__ . '/../partials/confirm_delete.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.jQuery && jQuery.fn.DataTable) {
        jQuery('#tblClientes').DataTable({
            columnDefs: [{ orderable: false, targets: [-1] }]
        });
    }

    // Modal de desativar
    const modal = document.getElementById('confirmDelete');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(event) {
            const btn = event.relatedTarget;
            if (!btn) return;
            const url = btn.dataset.actionUrl || '';
            const name = btn.dataset.itemName || '';
            modal.querySelector('form').action = url;
            const msg = modal.querySelector('[data-modal-message]');
            if (msg) msg.textContent = 'Tem certeza que deseja desativar "' + name + '"? Os lançamentos vinculados permanecerão.';
        });
    }
});
</script>
