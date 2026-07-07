<?php
declare(strict_types=1);

/**
 * @file    login.php
 * @package Root
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Tela de login com banner de instalação condicional
 */

session_start();

// Se já logado e sistema instalado, vai para dashboard
if (file_exists(__DIR__ . '/config.php') && isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Carrega App apenas se config existe
$appLoaded = false;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/app/Core/App.php';
    App\Core\App::boot();
    $appLoaded = true;
}

use App\Core\Hash;
use App\Core\Csrf;
use App\Core\Database;

$erro = '';
$mensagem = '';

if (isset($_GET['logout'])) {
    $mensagem = 'Logout realizado com sucesso.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $appLoaded) {
    $csrfOk = isset($_POST['_csrf']) && Csrf::verify($_POST['_csrf']);
    if (!$csrfOk) {
        $erro = 'Token CSRF inválido.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['senha'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        try {
            $pdo = Database::getInstance();

            // Rate limit por IP e por email (janela 15 min)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_tentativas_login WHERE ip = ? AND criado_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$ip]);
            $tentativasIp = (int) $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM logs_tentativas_login WHERE email = ? AND criado_em >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $stmt->execute([$email]);
            $tentativasEmail = (int) $stmt->fetchColumn();

            if ($tentativasIp >= 5 || $tentativasEmail >= 5) {
                $erro = 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND ativo = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && Hash::verify($senha, $user['senha_hash'])) {
                    // Verificar bloqueio
                    if ($user['bloqueado_ate'] && strtotime($user['bloqueado_ate']) > time()) {
                        $erro = 'Usuário bloqueado até ' . date('d/m/Y H:i', strtotime($user['bloqueado_ate'])) . '.';
                    } else {
                        // Sucesso
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = (int) $user['id'];
                        $_SESSION['user_nome'] = $user['nome'];
                        $_SESSION['user_perfil'] = $user['perfil'];
                        $_SESSION['ip'] = $ip;
                        $_SESSION['user_agent'] = $ua;
                        $_SESSION['last_activity'] = time();

                        $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acesso = NOW(), tentativas_login = 0, bloqueado_ate = NULL WHERE id = ?");
                        $stmt->execute([$user['id']]);

                        $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, acao, modulo, ip, user_agent) VALUES (?, 'LOGIN', 'auth', ?, ?)");
                        $stmt->execute([$user['id'], $ip, $ua]);

                        $stmt = $pdo->prepare("INSERT INTO logs_tentativas_login (email, ip, sucesso) VALUES (?, ?, 1)");
                        $stmt->execute([$email, $ip]);

                        header('Location: index.php');
                        exit;
                    }
                } else {
                    // Falha
                    $stmt = $pdo->prepare("INSERT INTO logs_tentativas_login (email, ip, sucesso) VALUES (?, ?, 0)");
                    $stmt->execute([$email, $ip]);

                    if ($user) {
                        $tentativas = $user['tentativas_login'] + 1;
                        $bloqueadoAte = null;
                        if ($tentativas >= 5) {
                            $bloqueadoAte = date('Y-m-d H:i:s', time() + 900);
                        }
                        $stmt = $pdo->prepare("UPDATE usuarios SET tentativas_login = ?, bloqueado_ate = ? WHERE id = ?");
                        $stmt->execute([$tentativas, $bloqueadoAte, $user['id']]);
                    }
                    $erro = 'Credenciais inválidas.';
                }
            }
        } catch (Throwable $e) {
            $erro = 'Erro ao processar login. Verifique se o banco está acessível.';
            if (file_exists(__DIR__ . '/storage/logs')) {
                @file_put_contents(
                    __DIR__ . '/storage/logs/error_' . date('Ymd') . '.log',
                    '[' . date('Y-m-d H:i:s') . '] LOGIN ERROR: ' . $e->getMessage() . PHP_EOL,
                    FILE_APPEND
                );
            }
        }
    }
}

$showInstallBanner = !$appLoaded || isset($_GET['install_pending']);
$csrfToken = $appLoaded ? Csrf::token() : bin2hex(random_bytes(32));
$emailValue = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
$erroHtml = $erro ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> ' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>' : '';
$msgHtml = $mensagem ? '<div class="alert alert-info"><i class="bi bi-info-circle me-1"></i> ' . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') . '</div>' : '';
$installBannerHtml = $showInstallBanner ? '<div class="alert alert-warning border-warning border-2 mb-4 rounded-3 d-flex align-items-center justify-content-between">
        <div><i class="bi bi-exclamation-triangle-fill fs-4 me-2"></i>
            <strong>Sistema ainda não instalado.</strong> Clique no botão para iniciar a instalação.
        </div>
        <a href="install.php" class="btn btn-success"><i class="bi bi-download me-1"></i> Instalar agora</a>
    </div>' : '';
$notInstalledAlert = !$appLoaded ? '<div class="alert alert-warning"><i class="bi bi-info-circle me-1"></i> O sistema precisa ser instalado antes do primeiro acesso.</div>' : '';
$btnDisabled = !$appLoaded ? 'disabled' : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Leandro DEV Financeiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh; }
        .login-card { max-width: 420px; margin: 0 auto; }
        .brand-logo { font-size: 1.5rem; font-weight: 700; color: #0d6efd; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
<div class="container">
    <?= $installBannerHtml ?>

    <div class="login-card">
        <div class="text-center mb-4">
            <i class="bi bi-cash-coin fs-1 text-primary"></i>
            <h1 class="h3 mt-2 brand-logo">Leandro DEV</h1>
            <p class="text-muted">Sistema Financeiro — MM Construtora</p>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-4">
                <?= $erroHtml ?>
                <?= $msgHtml ?>
                <?= $notInstalledAlert ?>

                <form method="post" action="login.php">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label for="email" class="form-label">E-mail</label>
                        <input type="email" class="form-control" id="email" name="email" required autofocus value="<?= $emailValue ?>">
                    </div>
                    <div class="mb-3">
                        <label for="senha" class="form-label">Senha</label>
                        <input type="password" class="form-control" id="senha" name="senha" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary" <?= $btnDisabled ?>>
                            <i class="bi bi-box-arrow-in-right me-1"></i> Entrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <p class="text-center text-muted mt-3 small">
            Copyright © 2026 Leandro DEV — MM Construtora. Todos os direitos reservados.
        </p>
    </div>
</div>
</body>
</html>
