<?php
declare(strict_types=1);

/**
 * @file    index.php
 * @package Root
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Front controller e router principal
 */

// Se config.php não existe, redireciona para login com flag de instalação pendente
if (!file_exists(__DIR__ . '/config.php')) {
    header('Location: login.php?install_pending=1');
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core/App.php';

use App\Core\App;
use App\Core\Auth;
use App\Core\Response;
use App\Core\Router;
use App\Controllers\DashboardController;
use App\Controllers\ContaController;
use App\Controllers\PlanoContasController;
use App\Controllers\LancamentoController;
use App\Controllers\ContaProgramadaController;
use App\Controllers\TransferenciaController;
use App\Controllers\ClienteFornecedorController;
use App\Controllers\RelatorioController;
use App\Controllers\UsuarioController;
use App\Controllers\ConfiguracaoController;
use App\Controllers\BackupController;

App::boot();

// Registrar rotas (alinhado com T06 views — kebab-case)
Router::add('dashboard', DashboardController::class);
Router::add('contas', ContaController::class);
Router::add('plano-contas', PlanoContasController::class);
Router::add('lancamentos', LancamentoController::class);
Router::add('contas-programadas', ContaProgramadaController::class);
Router::add('transferencias', TransferenciaController::class);
Router::add('clientes-fornecedores', ClienteFornecedorController::class);
Router::add('relatorios', RelatorioController::class);
Router::add('usuarios', UsuarioController::class);
Router::add('perfil', UsuarioController::class, 'perfil');
Router::add('configuracoes', ConfiguracaoController::class);
Router::add('backups', BackupController::class);

// Rota padrão: se raiz, vai para dashboard (Auth::require cuidará do redirect)
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = trim((string) parse_url($uri, PHP_URL_PATH), '/');
if ($path === '' || $path === 'index.php') {
    if (Auth::check()) {
        Response::redirect('/dashboard');
    } else {
        Response::redirect('/login.php');
    }
    exit;
}

try {
    Router::dispatch($uri);
} catch (Throwable $e) {
    if (class_exists(\App\Core\Logger::class)) {
        \App\Core\Logger::error('Router dispatch failed: ' . $e->getMessage(), [
            'uri' => $uri,
            'trace' => $e->getTraceAsString(),
        ]);
    }
    Response::abort(500, 'Erro interno do servidor');
}
