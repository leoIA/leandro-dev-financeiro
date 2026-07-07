<?php
declare(strict_types=1);

/**
 * @file    logout.php
 * @package Root
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Destroi sessão e redireciona para login
 */

// CORREÇÃO BUG #1: Carregar App::boot() PRIMEIRO (define nome do cookie LEANDRODEVSESSID),
// só então acessar $_SESSION. Se session_start() antes do App::boot(), a sessão
// seria iniciada com PHPSESSID e user_id estaria no cookie errado.
$appLoaded = false;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/app/Core/App.php';
    App\Core\App::boot();
    $appLoaded = true;
} else {
    session_start();
}

// Log auditoria se possível
if ($appLoaded && isset($_SESSION['user_id'])) {
    try {
        $pdo = App\Core\Database::getInstance();
        $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, acao, modulo, ip, user_agent) VALUES (?, 'LOGOUT', 'auth', ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
        ]);
    } catch (Throwable $e) {
        // Silencioso — apenas logamos se possível
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
}
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

header('Location: login.php?logout=1');
exit;
