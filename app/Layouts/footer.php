<?php declare(strict_types=1);
/**
 * @file    footer.php
 * @package App\Layouts
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Fecha main-content e wrapper, inclui JS CDNs e locais,
 *          renderiza toasts Bootstrap a partir de Flash::get() e exibe
 *          o rodapé com copyright.
 */
use App\Core\Flash;

$flashMessages = Flash::get();
?>
    <footer class="bg-dark text-light py-3 mt-5">
        <div class="container-fluid">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                <div class="d-flex flex-column flex-md-row align-items-center gap-2">
                    <span class="small">Copyright © 2026 Leandro DEV — MM Construtora. Todos os direitos reservados.</span>
                    <span class="small text-muted">v1.0.0</span>
                </div>
                <div class="small">
                    <a href="mailto:leog3@live.com" class="text-light text-decoration-none">
                        <i class="bi bi-envelope me-1"></i>leog3@live.com
                    </a>
                    <span class="mx-2">|</span>
                    <a href="https://wa.me/5571991782319" target="_blank" rel="noopener" class="text-light text-decoration-none">
                        <i class="bi bi-whatsapp me-1"></i>(71) 99178-2319
                    </a>
                </div>
            </div>
        </div>
    </footer>
</div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- Toasts Bootstrap (flash messages) -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;" id="flashToastContainer">
    <?php foreach ($flashMessages as $idx => $msg):
        $type  = $msg['type']  ?? 'info';
        $text  = $msg['message'] ?? '';
        $bgMap = ['success' => 'bg-success', 'error' => 'bg-danger', 'warning' => 'bg-warning text-dark', 'info' => 'bg-info text-dark'];
        $iconMap = ['success' => 'bi-check-circle-fill', 'error' => 'bi-x-octagon-fill', 'warning' => 'bi-exclamation-triangle-fill', 'info' => 'bi-info-circle-fill'];
        $bg = $bgMap[$type] ?? 'bg-info text-dark';
        $ic = $iconMap[$type] ?? 'bi-info-circle-fill';
    ?>
        <div class="toast show align-items-center text-white <?= $bg ?> border-0" role="alert" aria-live="assertive" aria-atomic="true" data-flash-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi <?= $ic ?> me-2"></i><?= htmlspecialchars($text, ENT_QUOTES, 'UTF-8') ?>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fechar"></button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- JS CDNs -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.7/js/dataTables.bootstrap5.min.js"></script>

<!-- JS local -->
<script src="public/js/app.js"></script>
<script src="public/js/mask.js"></script>
<?php if (isset($useViacep) && $useViacep === true): ?>
    <script src="public/js/viacep.js"></script>
<?php endif; ?>

</body>
</html>
