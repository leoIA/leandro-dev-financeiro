<?php
declare(strict_types=1);

/**
 * @file    install.php
 * @package Root
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Wizard de instalação em 4 etapas
 */

// Bloqueia se já instalado (exceto CLI --force)
$cliForce = PHP_SAPI === 'cli' && isset($argv[1]) && $argv[1] === '--force';
if (file_exists(__DIR__ . '/storage/.installed') && !$cliForce) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><title>Já instalado</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="d-flex align-items-center min-vh-100"><div class="container"><div class="card mx-auto" style="max-width:540px"><div class="card-body"><h3>Sistema já instalado</h3><p>Para reinstalar, remova os arquivos <code>config.php</code> e <code>storage/.installed</code> manualmente.</p><a href="login.php" class="btn btn-primary">Ir para o login</a></div></div></div></body></html>';
    exit;
}

session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
@ini_set('display_errors', '1'); // Installer mostra erros para diagnóstico

$step = isset($_REQUEST['step']) ? (int) $_REQUEST['step'] : 1;
if ($step < 1 || $step > 4) {
    $step = 1;
}

// Funções auxiliares
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function checkPhp(string $minVersion): array {
    return ['ok' => version_compare(PHP_VERSION, $minVersion, '>='), 'value' => PHP_VERSION];
}

function checkExt(string $ext): array {
    return ['ok' => extension_loaded($ext), 'value' => $ext ? 'Instalada' : 'Ausente'];
}

function checkWritable(string $path): array {
    $full = __DIR__ . '/' . $path;
    if (!is_dir($full)) {
        @mkdir($full, 0775, true);
    }
    return ['ok' => is_writable($full), 'value' => $path];
}

// Processar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = true; // Installer não usa CSRF (pré-instalação)
    if ($step === 1) {
        // Avança para step 2
        header('Location: install.php?step=2');
        exit;
    } elseif ($step === 2) {
        // Dados do banco
        $dbHost = trim($_POST['db_host'] ?? 'localhost');
        $dbPort = (int) ($_POST['db_port'] ?? 3306);
        $dbName = trim($_POST['db_name'] ?? 'leandro_dev_fin');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = $_POST['db_pass'] ?? '';
        $criarDb = isset($_POST['criar_db']);
        $executarSql = isset($_POST['executar_sql']);

        $erro = '';
        try {
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Verificar versão MySQL
            $version = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
            $isMariaDB = stripos($version, 'mariadb') !== false;
            $versionOk = false;
            if ($isMariaDB) {
                preg_match('/(\d+\.\d+\.\d+)/', $version, $m);
                $versionOk = version_compare($m[1] ?? '0', '10.6', '>=');
            } else {
                $versionOk = version_compare($version, '8.0', '>=');
            }
            if (!$versionOk) {
                $erro = "Versão do MySQL/MariaDB insuficiente: {$version}. Requer MySQL 8.0+ ou MariaDB 10.6+.";
            }

            // Criar DB se marcado
            if (!$erro && $criarDb) {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }

            // Selecionar DB
            if (!$erro) {
                $pdo->exec("USE `{$dbName}`");
            }

            // Executar db.sql
            if (!$erro && $executarSql) {
                $sql = file_get_contents(__DIR__ . '/db.sql');
                if ($sql === false) {
                    $erro = 'Não foi possível ler o arquivo db.sql.';
                } else {
                    // Substitui nome do DB se diferente do default
                    if ($dbName !== 'leandro_dev_fin') {
                        $sql = str_replace('leandro_dev_fin', $dbName, $sql);
                    }
                    // Executar statement por statement (split respeitando strings)
                    executeSqlStatements($pdo, $sql);
                }
            }
        } catch (Throwable $e) {
            $erro = 'Erro: ' . $e->getMessage();
        }

        if ($erro) {
            renderHeader('Etapa 2 de 4: Banco de Dados');
            echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> ' . h($erro) . '</div>';
            renderStep2Form($dbHost, $dbPort, $dbName, $dbUser, $criarDb, $executarSql);
            renderFooter();
            exit;
        }

        // Salvar em session e avançar
        $_SESSION['install_db'] = [
            'host' => $dbHost,
            'port' => $dbPort,
            'name' => $dbName,
            'user' => $dbUser,
            'pass' => $dbPass,
        ];
        header('Location: install.php?step=3');
        exit;
    } elseif ($step === 3) {
        // Empresa + admin
        $empresa = trim($_POST['empresa_razao_social'] ?? 'MM Construtora');
        $cnpj = trim($_POST['empresa_cnpj'] ?? '');
        $fuso = trim($_POST['fuso_horario'] ?? 'America/Sao_Paulo');
        $adminNome = trim($_POST['admin_nome'] ?? 'Administrador');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminSenha = $_POST['admin_senha'] ?? '';
        $adminSenhaConfirma = $_POST['admin_senha_confirma'] ?? '';

        $erros = [];
        if ($empresa === '') $erros[] = 'Razão social obrigatória.';
        if ($adminNome === '') $erros[] = 'Nome do admin obrigatório.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail do admin inválido.';
        if (strlen($adminSenha) < 8) $erros[] = 'Senha deve ter ao menos 8 caracteres.';
        if (!preg_match('/[A-Z]/', $adminSenha)) $erros[] = 'Senha deve conter ao menos 1 letra maiúscula.';
        if (!preg_match('/[0-9]/', $adminSenha)) $erros[] = 'Senha deve conter ao menos 1 número.';
        if ($adminSenha !== $adminSenhaConfirma) $erros[] = 'Senhas não conferem.';

        // Upload de logo (opcional)
        $logoPath = '';
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'svg'];
            $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) {
                $erros[] = 'Logo deve ser JPG, PNG ou SVG.';
            } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                $erros[] = 'Logo deve ter no máximo 2MB.';
            } else {
                $logoPath = '/storage/uploads/logo_empresa.' . $ext;
            }
        }

        if ($erros) {
            renderHeader('Etapa 3 de 4: Empresa + Admin');
            echo '<div class="alert alert-danger"><ul><li>' . implode('</li><li>', array_map('h', $erros)) . '</li></ul></div>';
            renderStep3Form($empresa, $cnpj, $fuso, $adminNome, $adminEmail);
            renderFooter();
            exit;
        }

        $_SESSION['install_empresa'] = [
            'razao_social' => $empresa,
            'cnpj' => $cnpj,
            'fuso' => $fuso,
            'logo_path' => $logoPath,
        ];
        $_SESSION['install_admin'] = [
            'nome' => $adminNome,
            'email' => $adminEmail,
            'senha' => $adminSenha,
        ];
        header('Location: install.php?step=4');
        exit;
    } elseif ($step === 4) {
        // Confirmar e instalar
        $aceito = isset($_POST['aceito']);
        if (!$aceito) {
            renderHeader('Etapa 4 de 4: Confirmar');
            echo '<div class="alert alert-warning">Você deve marcar a caixa de aceitação.</div>';
            renderStep4Form($_SESSION['install_db'] ?? [], $_SESSION['install_empresa'] ?? [], $_SESSION['install_admin'] ?? []);
            renderFooter();
            exit;
        }

        $db = $_SESSION['install_db'] ?? null;
        $empresa = $_SESSION['install_empresa'] ?? null;
        $admin = $_SESSION['install_admin'] ?? null;

        if (!$db || !$empresa || !$admin) {
            die('Dados de instalação ausentes. <a href="install.php?step=1">Reiniciar</a>.');
        }

        $erroFinal = '';
        try {
            // 1. Gerar config.php
            $configContent = '<?php
/**
 * @file    config.php
 * @package Root
 * @since   ' . date('Y.m.d') . '
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * GERADO PELO INSTALLER — NÃO EDITE MANUALMENTE.
 */

define(\'DB_HOST\', ' . var_export($db['host'], true) . ');
define(\'DB_PORT\', ' . var_export($db['port'], true) . ');
define(\'DB_NAME\', ' . var_export($db['name'], true) . ');
define(\'DB_USER\', ' . var_export($db['user'], true) . ');
define(\'DB_PASS\', ' . var_export($db['pass'], true) . ');
define(\'DB_CHARSET\', \'utf8mb4\');

define(\'APP_NAME\', \'Leandro DEV Financeiro\');
define(\'APP_VERSION\', \'1.0.0\');
define(\'APP_TIMEZONE\', ' . var_export($empresa['fuso'], true) . ');
define(\'APP_LOCALE\', \'pt_BR\');

define(\'BASE_PATH\', __DIR__);
define(\'APP_PATH\', BASE_PATH . \'/app\');
define(\'PUBLIC_PATH\', BASE_PATH . \'/public\');
define(\'STORAGE_PATH\', BASE_PATH . \'/storage\');
define(\'LOG_PATH\', STORAGE_PATH . \'/logs\');
define(\'BACKUP_PATH\', STORAGE_PATH . \'/backups\');
define(\'UPLOAD_PATH\', STORAGE_PATH . \'/uploads\');
define(\'SESSION_PATH\', STORAGE_PATH . \'/sessions\');

define(\'SESSION_LIFETIME\', 30);
define(\'LOGIN_MAX_TENTATIVAS\', 5);
define(\'LOGIN_BLOQUEIO_MINUTOS\', 15);

if (!defined(\'BASE_URL\')) {
    $https = (!empty($_SERVER[\'HTTPS\']) && $_SERVER[\'HTTPS\'] !== \'off\');
    $scheme = $https ? \'https\' : \'http\';
    $host = $_SERVER[\'HTTP_HOST\'] ?? \'localhost\';
    $script = $_SERVER[\'SCRIPT_NAME\'] ?? \'\';
    $dir = str_replace(\'\\\\\', \'/\', dirname($script));
    $dir = ($dir === \'/\' || $dir === \'\\\\\') ? \'\' : $dir;
    define(\'BASE_URL\', $scheme . \'://\' . $host . $dir);
}
';
            $configWriteOk = file_put_contents(__DIR__ . '/config.php', $configContent);
            if ($configWriteOk === false) {
                throw new RuntimeException('Falha ao gravar config.php. Verifique permissões da raiz.');
            }

            // 2. Carregar App e conectar
            require_once __DIR__ . '/config.php';
            require_once __DIR__ . '/app/Core/App.php';
            App\Core\App::boot();
            $pdo = App\Core\Database::getInstance();

            // 3. Atualizar configuracoes com dados da empresa
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'empresa_razao_social'");
            $stmt->execute([$empresa['razao_social']]);
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'empresa_cnpj'");
            $stmt->execute([$empresa['cnpj']]);
            $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'fuso_horario'");
            $stmt->execute([$empresa['fuso']]);
            if (!empty($empresa['logo_path'])) {
                $stmt = $pdo->prepare("UPDATE configuracoes SET valor = ? WHERE chave = 'empresa_logo_path'");
                $stmt->execute([$empresa['logo_path']]);
            }

            // 4. Inserir admin
            $senhaHash = password_hash($admin['senha'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil, ativo) VALUES (?, ?, ?, 'ADMIN', 1)");
            $stmt->execute([$admin['nome'], $admin['email'], $senhaHash]);
            $adminId = (int) $pdo->lastInsertId();

            // 5. Vincular todas permissoes ao admin
            $stmt = $pdo->prepare("INSERT INTO usuario_permissoes (usuario_id, permissao_id) SELECT ?, id FROM permissoes");
            $stmt->execute([$adminId]);

            // 6. Mover logo se enviada
            if (!empty($empresa['logo_path']) && !empty($_FILES['logo']['tmp_name']) && file_exists($_FILES['logo']['tmp_name'])) {
                $dest = __DIR__ . $empresa['logo_path'];
                $destDir = dirname($dest);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0775, true);
                }
                move_uploaded_file($_FILES['logo']['tmp_name'], $dest);
            }

            // 7. Log INSTALL
            $stmt = $pdo->prepare("INSERT INTO logs_auditoria (usuario_id, acao, modulo, ip, user_agent, dados_novos) VALUES (?, 'INSTALL', 'sistema', ?, ?, ?)");
            $stmt->execute([
                $adminId,
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                json_encode(['empresa' => $empresa['razao_social'], 'admin_email' => $admin['email']]),
            ]);

            // 8. Marker .installed
            file_put_contents(__DIR__ . '/storage/.installed', date('Y-m-d H:i:s'));

            // Limpar session de instalação
            unset($_SESSION['install_db'], $_SESSION['install_empresa'], $_SESSION['install_admin']);

            // Sucesso
            renderHeader('Instalação concluída!');
            echo '<div class="text-center py-5">
                <i class="bi bi-check-circle-fill text-success" style="font-size:5rem"></i>
                <h2 class="mt-3">Sistema instalado com sucesso!</h2>
                <p class="text-muted">Use suas credenciais de administrador para acessar o sistema.</p>
                <p class="text-muted small"><strong>Recomendado:</strong> delete o arquivo <code>install.php</code> após o primeiro acesso por segurança.</p>
                <a href="login.php" class="btn btn-primary btn-lg mt-3"><i class="bi bi-box-arrow-in-right me-1"></i> Ir para o Login</a>
            </div>';
            renderFooter();
            exit;
        } catch (Throwable $e) {
            $erroFinal = $e->getMessage();
        }

        if ($erroFinal) {
            renderHeader('Erro na instalação');
            echo '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-1"></i> Erro: ' . h($erroFinal) . '</div>';
            echo '<p>Verifique os dados e tente novamente.</p>';
            echo '<a href="install.php?step=2" class="btn btn-secondary">Voltar</a>';
            renderFooter();
            exit;
        }
    }
}

// === RENDERIZAÇÃO DAS ETAPAS ===

if ($step === 1) {
    $checks = [
        'PHP >= 8.2' => checkPhp('8.2.0'),
        'Extensão pdo_mysql' => checkExt('pdo_mysql'),
        'Extensão mbstring' => checkExt('mbstring'),
        'Extensão openssl' => checkExt('openssl'),
        'Extensão gd' => checkExt('gd'),
        'Extensão json' => checkExt('json'),
        'Extensão session' => checkExt('session'),
        'Permissão storage/logs' => checkWritable('storage/logs'),
        'Permissão storage/backups' => checkWritable('storage/backups'),
        'Permissão storage/uploads' => checkWritable('storage/uploads'),
        'Permissão storage/sessions' => checkWritable('storage/sessions'),
    ];
    $allOk = true;
    foreach ($checks as $c) {
        if (!$c['ok']) { $allOk = false; break; }
    }
    renderHeader('Etapa 1 de 4: Pré-requisitos');
    echo '<p>Verificando os requisitos do sistema:</p>';
    echo '<table class="table table-bordered"><thead><tr><th>Requisito</th><th>Status</th><th>Valor</th></tr></thead><tbody>';
    foreach ($checks as $nome => $c) {
        $badge = $c['ok'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Falha</span>';
        echo "<tr><td>" . h($nome) . "</td><td>{$badge}</td><td><code>" . h($c['value']) . "</code></td></tr>";
    }
    echo '</tbody></table>';
    echo '<form method="post" action="install.php?step=1">';
    echo '<input type="hidden" name="step" value="1">';
    echo '<button type="submit" class="btn btn-primary" ' . ($allOk ? '' : 'disabled') . '>Continuar <i class="bi bi-arrow-right ms-1"></i></button>';
    if (!$allOk) {
        echo ' <span class="text-muted">Corrija as falhas acima antes de continuar.</span>';
    }
    echo '</form>';
    renderFooter();
    exit;
}

if ($step === 2) {
    renderHeader('Etapa 2 de 4: Banco de Dados');
    renderStep2Form('localhost', 3306, 'leandro_dev_fin', '', true, true);
    renderFooter();
    exit;
}

if ($step === 3) {
    renderHeader('Etapa 3 de 4: Empresa + Admin');
    renderStep3Form('MM Construtora', '', 'America/Sao_Paulo', 'Administrador', '');
    renderFooter();
    exit;
}

if ($step === 4) {
    $db = $_SESSION['install_db'] ?? [];
    $empresa = $_SESSION['install_empresa'] ?? [];
    $admin = $_SESSION['install_admin'] ?? [];

    if (!$db || !$empresa || !$admin) {
        header('Location: install.php?step=1');
        exit;
    }

    renderHeader('Etapa 4 de 4: Confirmar');
    renderStep4Form($db, $empresa, $admin);
    renderFooter();
    exit;
}

// === FUNÇÕES DE RENDERIZAÇÃO ===

function renderHeader(string $title): void {
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . h($title) . ' — Instalação Leandro DEV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); min-height: 100vh; }
        .install-card { max-width: 720px; margin: 0 auto; }
        .brand-logo { font-size: 1.5rem; font-weight: 700; color: #0d6efd; }
        .step-indicator { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
        .step-indicator .step { flex: 1; height: 6px; background: #e9ecef; border-radius: 3px; }
        .step-indicator .step.active { background: #0d6efd; }
        .step-indicator .step.done { background: #198754; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100 py-4">
<div class="container">
<div class="install-card">
    <div class="text-center mb-4">
        <i class="bi bi-cash-coin fs-1 text-primary"></i>
        <h1 class="h3 mt-2 brand-logo">Leandro DEV</h1>
        <p class="text-muted">Sistema Financeiro — MM Construtora</p>
    </div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="step-indicator">';
    global $step;
    for ($i = 1; $i <= 4; $i++) {
        $class = 'step';
        if ($i < $step) $class .= ' done';
        elseif ($i === $step) $class .= ' active';
        echo "<div class=\"{$class}\"></div>";
    }
    echo '</div>
            <h2 class="h4 mb-3">' . h($title) . '</h2>';
}

function renderFooter(): void {
    echo '        </div>
    </div>
    <p class="text-center text-muted mt-3 small">Copyright © 2026 Leandro DEV — MM Construtora. Todos os direitos reservados.</p>
</div>
</div>
</body>
</html>';
}

function renderStep2Form(string $host, int $port, string $name, string $user, bool $criarDb, bool $executarSql): void {
    echo '<form method="post" action="install.php?step=2" autocomplete="off">
        <input type="hidden" name="step" value="2">
        <div class="row">
            <div class="col-md-8 mb-3">
                <label for="db_host" class="form-label">Host *</label>
                <input type="text" class="form-control" id="db_host" name="db_host" required value="' . h($host) . '">
            </div>
            <div class="col-md-4 mb-3">
                <label for="db_port" class="form-label">Porta *</label>
                <input type="number" class="form-control" id="db_port" name="db_port" required value="' . h((string)$port) . '">
            </div>
        </div>
        <div class="mb-3">
            <label for="db_name" class="form-label">Nome do banco *</label>
            <input type="text" class="form-control" id="db_name" name="db_name" required value="' . h($name) . '">
        </div>
        <div class="mb-3">
            <label for="db_user" class="form-label">Usuário *</label>
            <input type="text" class="form-control" id="db_user" name="db_user" required value="' . h($user) . '">
        </div>
        <div class="mb-3">
            <label for="db_pass" class="form-label">Senha</label>
            <input type="password" class="form-control" id="db_pass" name="db_pass" autocomplete="new-password">
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="criar_db" name="criar_db" ' . ($criarDb ? 'checked' : '') . '>
            <label class="form-check-label" for="criar_db">Criar banco se não existir</label>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="executar_sql" name="executar_sql" ' . ($executarSql ? 'checked' : '') . '>
            <label class="form-check-label" for="executar_sql">Executar db.sql automaticamente (cria tabelas)</label>
        </div>
        <button type="submit" class="btn btn-primary">Continuar <i class="bi bi-arrow-right ms-1"></i></button>
        <a href="install.php?step=1" class="btn btn-secondary">Voltar</a>
    </form>';
}

function renderStep3Form(string $empresa, string $cnpj, string $fuso, string $adminNome, string $adminEmail): void {
    echo '<h5 class="mt-2">Dados da Empresa</h5>
    <form method="post" action="install.php?step=3" enctype="multipart/form-data" autocomplete="off">
        <input type="hidden" name="step" value="3">
        <div class="mb-3">
            <label for="empresa_razao_social" class="form-label">Razão Social *</label>
            <input type="text" class="form-control" id="empresa_razao_social" name="empresa_razao_social" required value="' . h($empresa) . '">
        </div>
        <div class="mb-3">
            <label for="empresa_cnpj" class="form-label">CNPJ</label>
            <input type="text" class="form-control" id="empresa_cnpj" name="empresa_cnpj" value="' . h($cnpj) . '" placeholder="00.000.000/0000-00">
        </div>
        <div class="mb-3">
            <label for="logo" class="form-label">Logo (opcional — JPG/PNG/SVG, máx 2MB)</label>
            <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg,image/png,image/svg+xml">
        </div>
        <div class="mb-3">
            <label for="fuso_horario" class="form-label">Fuso Horário</label>
            <select class="form-select" id="fuso_horario" name="fuso_horario">
                <option value="America/Sao_Paulo" ' . ($fuso === 'America/Sao_Paulo' ? 'selected' : '') . '>America/Sao_Paulo (Brasília)</option>
                <option value="America/Manaus" ' . ($fuso === 'America/Manaus' ? 'selected' : '') . '>America/Manaus (Amazonas)</option>
                <option value="America/Recife" ' . ($fuso === 'America/Recife' ? 'selected' : '') . '>America/Recife (Pernambuco)</option>
                <option value="America/Fortaleza" ' . ($fuso === 'America/Fortaleza' ? 'selected' : '') . '>America/Fortaleza (Ceará)</option>
                <option value="America/Bahia" ' . ($fuso === 'America/Bahia' ? 'selected' : '') . '>America/Bahia (Bahia)</option>
            </select>
        </div>

        <h5 class="mt-4">Dados do Administrador</h5>
        <div class="mb-3">
            <label for="admin_nome" class="form-label">Nome do Admin *</label>
            <input type="text" class="form-control" id="admin_nome" name="admin_nome" required value="' . h($adminNome) . '">
        </div>
        <div class="mb-3">
            <label for="admin_email" class="form-label">E-mail do Admin *</label>
            <input type="email" class="form-control" id="admin_email" name="admin_email" required value="' . h($adminEmail) . '">
        </div>
        <div class="mb-3">
            <label for="admin_senha" class="form-label">Senha * (mín. 8 caracteres, 1 maiúscula, 1 número)</label>
            <input type="password" class="form-control" id="admin_senha" name="admin_senha" required minlength="8">
            <div class="progress mt-2" style="height:6px">
                <div id="senha-meter" class="progress-bar" role="progressbar" style="width:0%"></div>
            </div>
            <small id="senha-feedback" class="text-muted">Digite a senha...</small>
        </div>
        <div class="mb-3">
            <label for="admin_senha_confirma" class="form-label">Confirmar Senha *</label>
            <input type="password" class="form-control" id="admin_senha_confirma" name="admin_senha_confirma" required minlength="8">
        </div>
        <button type="submit" class="btn btn-primary">Continuar <i class="bi bi-arrow-right ms-1"></i></button>
        <a href="install.php?step=2" class="btn btn-secondary">Voltar</a>
    </form>
    <script>
    document.getElementById("admin_senha").addEventListener("input", function() {
        var v = this.value;
        var score = 0;
        if (v.length >= 8) score += 25;
        if (/[A-Z]/.test(v)) score += 25;
        if (/[0-9]/.test(v)) score += 25;
        if (/[^A-Za-z0-9]/.test(v)) score += 25;
        var meter = document.getElementById("senha-meter");
        meter.style.width = score + "%";
        meter.className = "progress-bar " + (score < 50 ? "bg-danger" : score < 75 ? "bg-warning" : "bg-success");
        document.getElementById("senha-feedback").textContent = score < 50 ? "Fraca" : score < 75 ? "Média" : "Forte";
    });
    </script>';
}

function renderStep4Form(array $db, array $empresa, array $admin): void {
    echo '<form method="post" action="install.php?step=4">
        <input type="hidden" name="step" value="4">
        <input type="hidden" name="logo_temp" value="">
        <h5>Banco de Dados</h5>
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between"><span>Host:</span><code>' . h($db['host'] ?? '') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Porta:</span><code>' . h((string)($db['port'] ?? '')) . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Banco:</span><code>' . h($db['name'] ?? '') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Usuário:</span><code>' . h($db['user'] ?? '') . '</code></li>
        </ul>

        <h5>Empresa</h5>
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between"><span>Razão Social:</span><code>' . h($empresa['razao_social'] ?? '') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>CNPJ:</span><code>' . h($empresa['cnpj'] ?? '—') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Fuso Horário:</span><code>' . h($empresa['fuso'] ?? '') . '</code></li>
        </ul>

        <h5>Administrador</h5>
        <ul class="list-group mb-3">
            <li class="list-group-item d-flex justify-content-between"><span>Nome:</span><code>' . h($admin['nome'] ?? '') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>E-mail:</span><code>' . h($admin['email'] ?? '') . '</code></li>
            <li class="list-group-item d-flex justify-content-between"><span>Senha:</span><code>(oculta)</code></li>
        </ul>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="aceito" name="aceito" required>
            <label class="form-check-label" for="aceito">Li e aceito que o sistema será instalado com estes dados.</label>
        </div>
        <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-download me-1"></i> Instalar agora</button>
        <a href="install.php?step=3" class="btn btn-secondary">Voltar</a>
    </form>';
}

function executeSqlStatements(PDO $pdo, string $sql): void {
    // Remove comentários de linha
    $sql = preg_replace('/--.*$/m', '', $sql);
    // Remove comentários de bloco
    $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $next = $i < $len - 1 ? $sql[$i + 1] : '';

        if (!$inString && $char === "'" ) {
            $inString = true;
            $stringChar = "'";
        } elseif (!$inString && $char === '"') {
            $inString = true;
            $stringChar = '"';
        } elseif ($inString && $char === $stringChar) {
            // Checa escaped
            if ($next === $stringChar) {
                $current .= $char . $next;
                $i++;
                continue;
            }
            $inString = false;
            $stringChar = '';
        } elseif (!$inString && $char === ';') {
            $stmt = trim($current);
            if ($stmt !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            continue;
        }
        $current .= $char;
    }

    $last = trim($current);
    if ($last !== '') {
        $statements[] = $last;
    }

    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
}
