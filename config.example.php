<?php declare(strict_types=1);
/**
 * @file    config.example.php
 * @package Root
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Este arquivo é um TEMPLATE. O installer gera config.php real
 * com credenciais. NÃO edite manualmente após instalação.
 */

// Credenciais do banco (preenchidas pelo installer)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'leandro_dev_fin');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Aplicação
define('APP_NAME', 'Leandro DEV Financeiro');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'America/Sao_Paulo');
define('APP_LOCALE', 'pt_BR');

// Caminhos absolutos
define('BASE_PATH', __DIR__);
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');
define('BACKUP_PATH', STORAGE_PATH . '/backups');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');
define('SESSION_PATH', STORAGE_PATH . '/sessions');

// Segurança
define('SESSION_LIFETIME', 30);
define('LOGIN_MAX_TENTATIVAS', 5);
define('LOGIN_BLOQUEIO_MINUTOS', 15);

// Base URL (auto-detect se não definido)
if (!defined('BASE_URL')) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = ($dir === '/' || $dir === '\\') ? '' : $dir;
    define('BASE_URL', $scheme . '://' . $host . $dir);
}
