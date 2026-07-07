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

session_start();

// Log auditoria se possível
if (file_exists(__DIR__ . '/config.php') && isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/app/Core/App.php';
        App\Core\App::boot();
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
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

header('Location: login.php?logout=1');
exit;
