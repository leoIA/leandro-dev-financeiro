<?php declare(strict_types=1);
/**
 * Partial: Modal Bootstrap genérico para confirmar exclusão / desativação.
 *
 * Variáveis esperadas:
 *  - $modalId       (string) — id HTML do modal (default: confirmDelete)
 *  - $modalTitle    (string) — título do modal
 *  - $modalMessage  (string) — mensagem exibida no corpo
 *  - $actionUrl     (string) — URL POST alvo do form
 *  - $csrfToken     (string) — token CSRF (gerado via Csrf::token())
 *  - $btnLabel      (string) — rótulo do botão confirmar (default: "Confirmar")
 *  - $btnClass      (string) — classe do botão (default: "btn-danger")
 *  - $iconClass     (string) — ícone Bootstrap Icon (default: "bi-exclamation-triangle")
 */
$modalId      = $modalId      ?? 'confirmDelete';
$modalTitle   = $modalTitle   ?? 'Confirmar ação';
$modalMessage = $modalMessage ?? 'Tem certeza?';
$actionUrl    = $actionUrl    ?? '';
$csrfToken    = $csrfToken    ?? \App\Core\Csrf::token();
$btnLabel     = $btnLabel     ?? 'Confirmar';
$btnClass     = $btnClass     ?? 'btn-danger';
$iconClass    = $iconClass    ?? 'bi-exclamation-triangle';
?>
<div class="modal fade" id="<?= htmlspecialchars($modalId, ENT_QUOTES, 'UTF-8') ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi <?= htmlspecialchars($iconClass, ENT_QUOTES, 'UTF-8') ?>"></i>
                    <?= htmlspecialchars($modalTitle, ENT_QUOTES, 'UTF-8') ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <form method="post" action="<?= htmlspecialchars($actionUrl, ENT_QUOTES, 'UTF-8') ?>" data-modal-form>
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-body">
                    <p data-modal-message><?= htmlspecialchars($modalMessage, ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn <?= htmlspecialchars($btnClass, ENT_QUOTES, 'UTF-8') ?>">
                        <i class="bi bi-check-lg"></i> <?= htmlspecialchars($btnLabel, ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
