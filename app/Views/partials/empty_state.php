<?php declare(strict_types=1);
/**
 * Partial: Empty state reutilizável.
 * Variáveis:
 *  - $message (string|null): mensagem a exibir.
 */
$message = $message ?? 'Nenhum registro encontrado.';
?>
<div class="text-center py-5 text-muted">
    <i class="bi bi-inbox fs-1"></i>
    <p class="mt-3 mb-0"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
</div>
