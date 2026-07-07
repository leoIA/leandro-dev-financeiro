<?php declare(strict_types=1);
/**
 * @var string $pageTitle
 * @file    Views/sobre/index.php
 * @package App\Views\Sobre
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Página "Sobre / Contato" — informações do sistema, módulos,
 *          contato do desenvolvedor e licença.
 */
$pageTitle = $pageTitle ?? 'Sobre / Contato';

$modulos = [
    ['icon' => 'bi-speedometer2',      'nome' => 'Dashboard'],
    ['icon' => 'bi-bank',              'nome' => 'Contas'],
    ['icon' => 'bi-diagram-3',         'nome' => 'Plano de Contas'],
    ['icon' => 'bi-receipt',           'nome' => 'Lançamentos'],
    ['icon' => 'bi-calendar-event',    'nome' => 'Contas Programadas'],
    ['icon' => 'bi-arrow-left-right',  'nome' => 'Transferências'],
    ['icon' => 'bi-people',            'nome' => 'Clientes / Fornecedores'],
    ['icon' => 'bi-bar-chart-line',    'nome' => 'Relatórios'],
    ['icon' => 'bi-person-gear',       'nome' => 'Usuários'],
    ['icon' => 'bi-gear',              'nome' => 'Configurações'],
    ['icon' => 'bi-database-check',    'nome' => 'Backup'],
];
?>
<div class="container-fluid">

    <!-- Cabeçalho -->
    <div class="d-flex align-items-center mb-4">
        <i class="bi bi-info-circle-fill text-primary me-2 fs-3"></i>
        <h1 class="h3 mb-0">Sobre o Sistema</h1>
    </div>

    <div class="row g-4">

        <!-- Card 1: Informações do sistema -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-info-circle me-2"></i>Informações do Sistema
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Nome</span>
                            <strong>Leandro DEV Financeiro</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Versão</span>
                            <strong>1.0.0</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Cliente</span>
                            <strong>MM Construtora</strong>
                        </li>
                        <li class="list-group-item">
                            <span class="text-muted d-block mb-1">Stack</span>
                            <span class="badge bg-secondary me-1 mb-1">PHP 8.2+ puro</span>
                            <span class="badge bg-secondary me-1 mb-1">MySQL 8+</span>
                            <span class="badge bg-secondary me-1 mb-1">Bootstrap 5.3</span>
                            <span class="badge bg-secondary mb-1">Chart.js 4</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Card 2: Módulos disponíveis -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-grid-3x3-gap me-2"></i>Módulos Disponíveis
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($modulos as $mod): ?>
                            <div class="col-6 col-lg-4">
                                <div class="d-flex align-items-center p-2 border rounded">
                                    <i class="bi <?= htmlspecialchars($mod['icon'], ENT_QUOTES, 'UTF-8') ?> text-primary me-2"></i>
                                    <span class="small"><?= htmlspecialchars($mod['nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Card 3: Contato do Desenvolvedor -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-headset me-2"></i>Contato do Desenvolvedor
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Para suporte, dúvidas ou solicitação de novas funcionalidades, entre em contato pelos canais abaixo.
                    </p>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-envelope-fill text-primary me-3 fs-5"></i>
                            <div>
                                <div class="text-muted small">E-mail</div>
                                <a href="mailto:leog3@live.com" class="text-decoration-none fw-semibold">
                                    leog3@live.com
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-whatsapp text-success me-3 fs-5"></i>
                            <div>
                                <div class="text-muted small">WhatsApp</div>
                                <a href="https://wa.me/5571991782319" target="_blank" rel="noopener" class="text-decoration-none fw-semibold">
                                    (71) 99178-2319
                                </a>
                            </div>
                        </li>
                        <li class="list-group-item d-flex align-items-center">
                            <i class="bi bi-clock-fill text-primary me-3 fs-5"></i>
                            <div>
                                <div class="text-muted small">Horário de atendimento</div>
                                <span class="fw-semibold">Segunda a Sexta, 08h às 18h (Bahia)</span>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Card 4: Licença -->
        <div class="col-md-6">
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-shield-lock me-2"></i>Licença
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Tipo</span>
                            <strong>Proprietary — MM Construtora</strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between">
                            <span class="text-muted">Copyright</span>
                            <strong>© 2026 Leandro DEV</strong>
                        </li>
                        <li class="list-group-item">
                            <span class="text-muted small d-block mb-2">
                                Este software é de uso exclusivo da MM Construtora. A reprodução, distribuição ou
                                modificação não autorizada deste código, no todo ou em parte, é estritamente proibida.
                            </span>
                            <div class="alert alert-warning small mb-0 py-2" role="alert">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Em caso de dúvidas sobre a licença, entre em contato com o desenvolvedor.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div><!-- /.row -->

</div><!-- /.container-fluid -->
