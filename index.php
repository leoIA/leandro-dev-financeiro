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
use App\Controllers\SobreController;
use App\Controllers\NfseController;

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
Router::add('sobre', SobreController::class);
Router::add('nfse', NfseController::class);

// Rota padrão: se raiz, vai para dashboard (Auth::require cuidará do redirect)
// CORREÇÃO BUG #4: usar URLs relativas para funcionar em subdiretório do host
// CORREÇÃO BUG #6: NÃO comparar $path com 'index.php' pois quando o app está na raiz
// do document root, $path de 'index.php?route=dashboard' é 'index.php' → loop infinito.
// Solução: usar APENAS $route (da query string ou path) como critério.
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = trim((string) parse_url($uri, PHP_URL_PATH), '/');

// CORREÇÃO BUG #1: extrair route via query string OU path
// Suporta tanto /index.php?route=dashboard quanto /dashboard (via .htaccess rewrite)
$route = $_GET['route'] ?? '';
if ($route === '' && $path !== '' && $path !== 'index.php') {
    $route = $path;
}

// Limpar prefixo de subdiretório do $path (ex: 'subdir/index.php' → 'index.php')
// para que a comparação funcione em ambos root e subdir
$pathBasename = basename($path);

// Se route está vazio (raiz pura) ou path é apenas 'index.php' sem query route
// então redireciona conforme auth
if ($route === '' && ($path === '' || $pathBasename === 'index.php')) {
    if (Auth::check()) {
        // Logado — vai para dashboard
        Response::redirect('index.php?route=dashboard');
    } else {
        // Não logado — vai para login
        Response::redirect('login.php');
    }
    exit;
}

// Se route veio via query string, simular URI para o router
if ($route !== '' && $route !== $path && $route !== $pathBasename) {
    $uri = '/' . $route;
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
