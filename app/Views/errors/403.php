<?php declare(strict_types=1);
/** @var string $pageTitle */
/** @var string $mensagem */
$mensagem = $mensagem ?? 'Você não tem permissão para acessar esta página.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 — Acesso negado — Leandro DEV Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/public/css/app.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); }
        .error-card { max-width: 540px; margin: 0 auto; }
    </style>
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh">
    <div class="container">
        <div class="card shadow error-card border-0">
            <div class="card-body text-center py-5">
                <i class="bi bi-shield-lock fs-1 text-danger"></i>
                <h1 class="display-5 fw-bold mt-3 mb-2">403</h1>
                <h2 class="h5 text-muted mb-3">Acesso negado</h2>
                <p class="text-muted mb-4"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></p>
                <a href="/dashboard" class="btn btn-primary">
                    <i class="bi bi-house-door"></i> Voltar para Dashboard
                </a>
            </div>
            <div class="card-footer text-center text-muted small py-2">
                Copyright © 2026 Leandro DEV — MM Construtora. Todos os direitos reservados.
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
