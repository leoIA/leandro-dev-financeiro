<?php declare(strict_types=1);
/**
 * Partial: Paginação Bootstrap manual (uso quando não há DataTables).
 *
 * Variáveis:
 *  - $currentPage  (int)    — página atual (1-indexed)
 *  - $totalPages   (int)    — total de páginas
 *  - $baseUrl      (string) — URL base (sem query string de página)
 *
 * Exemplo de uso:
 *   include __DIR__ . '/partials/pagination.php';
 *   // com: $currentPage = 3; $totalPages = 10; $baseUrl = '/lancamentos';
 */
$currentPage = $currentPage ?? 1;
$totalPages  = $totalPages  ?? 1;
$baseUrl     = $baseUrl     ?? '?';

if ($totalPages <= 1) return;

function buildPageUrl(string $base, int $page): string {
    $sep = str_contains($base, '?') ? '&' : '?';
    return $base . $sep . 'page=' . $page;
}

$start = max(1, $currentPage - 2);
$end   = min($totalPages, $currentPage + 2);
if ($start <= 3) $start = 1;
if ($end >= $totalPages - 2) $end = $totalPages;
?>
<nav aria-label="Paginação" class="mt-3">
    <ul class="pagination justify-content-center">
        <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($baseUrl, max(1, $currentPage - 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Anterior">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>

        <?php if ($start > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($baseUrl, 1), ENT_QUOTES, 'UTF-8') ?>">1</a>
            </li>
            <?php if ($start > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>" <?= $p === $currentPage ? 'aria-current="page"' : '' ?>>
                <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($baseUrl, $p), ENT_QUOTES, 'UTF-8') ?>"><?= $p ?></a>
            </li>
        <?php endfor; ?>

        <?php if ($end < $totalPages): ?>
            <?php if ($end < $totalPages - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item">
                <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($baseUrl, $totalPages), ENT_QUOTES, 'UTF-8') ?>"><?= $totalPages ?></a>
            </li>
        <?php endif; ?>

        <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= htmlspecialchars(buildPageUrl($baseUrl, min($totalPages, $currentPage + 1)), ENT_QUOTES, 'UTF-8') ?>" aria-label="Próxima">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
    </ul>
</nav>
