<?php
/**
 * @file    BackupController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Backup / Restore (ADMIN only).
 *
 * Rotas:
 *   GET  /backups                    → index() — lista /storage/backups/*.sql
 *   POST /backups/gerar              → gerar() — gera novo backup
 *   GET  /backups/{filename}/download→ download($filename)
 *   POST /backups/restaurar          → restaurar() — recebe arquivo via POST (não URL)
 *   POST /backups/{filename}/excluir → excluir($filename)
 *
 * Estratégia de dump:
 *   1. Tenta mysqldump via exec() se disponível.
 *   2. Fallback: itera SHOW TABLES e gera INSERTs via PDO.
 *   Mantém apenas N últimos backups (Configuracao::get('backup_manter_ultimos', 10)).
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\Configuracao;
use App\Models\LogAuditoria;
use PDO;

class BackupController
{
    /** Diretório base de backups. */
    private string $backupDir;

    /**
     * Construtor — define diretório de backups.
     */
    public function __construct()
    {
        $this->backupDir = dirname(__DIR__, 2) . '/storage/backups';
        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
    }

    /**
     * GET /backups — lista arquivos .sql com tamanho e data.
     *
     * @return void
     */
    public function index(): void
    {
        $this->requireAdmin();

        $backups = [];
        $files = is_dir($this->backupDir) ? scandir($this->backupDir) : [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $this->backupDir . '/' . $f;
            if (!is_file($path)) {
                continue;
            }
            $stat = stat($path);
            if ($stat === false) {
                continue;
            }
            $backups[] = [
                'arquivo' => $f,
                'nome'    => $f,
                'tamanho' => (int) $stat['size'],
                'data'    => date('Y-m-d H:i:s', (int) $stat['mtime']),
                'criado_em' => date('Y-m-d H:i:s', (int) $stat['mtime']),
            ];
        }

        // Ordena por data decrescente.
        usort($backups, static fn (array $a, array $b): int => strcmp($b['data'] ?? '', $a['data'] ?? ''));

        Response::view('backups/index', [
            'pageTitle' => 'Backup / Restore',
            'backups'   => $backups,
        ]);
    }

    /**
     * POST /backups/gerar — gera novo backup.
     *
     * @return void
     */
    public function gerar(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/backups');
        }

        $fileName = 'backup_' . date('Ymd_His') . '.sql';
        $targetPath = $this->backupDir . '/' . $fileName;

        $ok = false;
        $errorMsg = null;

        // 1. Tenta mysqldump.
        if ($this->mysqldumpAvailable()) {
            $ok = $this->gerarViaMysqldump($targetPath, $errorMsg);
        }

        // 2. Fallback PDO.
        if (!$ok) {
            try {
                $this->gerarViaPdo($targetPath);
                $ok = true;
            } catch (\Throwable $e) {
                $errorMsg = $e->getMessage();
                \App\Core\Logger::error('Falha ao gerar backup via PDO: ' . $errorMsg);
            }
        }

        if (!$ok) {
            Flash::error('Falha ao gerar backup: ' . (string) $errorMsg);
            Response::redirect('/backups');
        }

        // Limpa mantendo apenas os N últimos.
        $this->limparAntigos();

        // Auditoria.
        try {
            (new LogAuditoria())->registrar('BACKUP', 'backups', null, null, ['arquivo' => $fileName]);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao registrar auditoria de backup: ' . $e->getMessage());
        }

        Flash::success('Backup gerado com sucesso: ' . $fileName);
        Response::redirect('/backups');
    }

    /**
     * GET /backups/{filename}/download — força download do arquivo.
     *
     * @param string $filename Nome do arquivo (URL-encoded).
     *
     * @return void
     */
    public function download(string $filename): void
    {
        $this->requireAdmin();

        $safe = $this->safeguardFilename($filename);
        if ($safe === null) {
            Flash::error('Nome de arquivo inválido.');
            Response::redirect('/backups');
        }

        $path = $this->backupDir . '/' . $safe;
        if (!is_file($path)) {
            Response::abort(404, 'Backup não encontrado.');
        }

        Response::download($path, $safe, 'application/sql');
    }

    /**
     * POST /backups/restaurar — restaura backup (arquivo via POST field 'arquivo').
     *
     * @return void
     */
    public function restaurar(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/backups');
        }

        $filename = (string) Request::post('arquivo', '');
        $safe = $this->safeguardFilename($filename);
        if ($safe === null) {
            Flash::error('Nome de arquivo inválido.');
            Response::redirect('/backups');
        }

        $path = $this->backupDir . '/' . $safe;
        if (!is_file($path)) {
            Flash::error('Backup não encontrado: ' . $safe);
            Response::redirect('/backups');
        }

        $sql = file_get_contents($path);
        if ($sql === false || $sql === '') {
            Flash::error('Arquivo de backup vazio ou ilegível.');
            Response::redirect('/backups');
        }

        try {
            // Database::execSqlFile já faz split seguro respeitando aspas/comentários.
            Database::execSqlFile($sql);

            try {
                (new LogAuditoria())->registrar('RESTORE', 'backups', null, null, ['arquivo' => $safe]);
            } catch (\Throwable $e) {
                \App\Core\Logger::error('Falha ao registrar auditoria de restore: ' . $e->getMessage());
            }

            Flash::success('Backup restaurado com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao restaurar backup: ' . $e->getMessage());
            Flash::error('Erro ao restaurar backup: ' . $e->getMessage());
        }
        Response::redirect('/backups');
    }

    /**
     * POST /backups/{filename}/excluir — exclui backup.
     *
     * @param string $filename Nome do arquivo (URL-encoded).
     *
     * @return void
     */
    public function excluir(string $filename): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/backups');
        }

        $safe = $this->safeguardFilename($filename);
        if ($safe === null) {
            Flash::error('Nome de arquivo inválido.');
            Response::redirect('/backups');
        }

        $path = $this->backupDir . '/' . $safe;
        if (!is_file($path)) {
            Flash::error('Backup não encontrado.');
            Response::redirect('/backups');
        }

        if (!@unlink($path)) {
            Flash::error('Falha ao excluir o arquivo de backup.');
            Response::redirect('/backups');
        }

        try {
            (new LogAuditoria())->registrar('DELETE', 'backups', null, ['arquivo' => $safe], null);
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao registrar auditoria de exclusão: ' . $e->getMessage());
        }

        Flash::success('Backup excluído com sucesso.');
        Response::redirect('/backups');
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Exige perfil ADMIN.
     *
     * @return void
     */
    private function requireAdmin(): void
    {
        Auth::require();
        $user = Auth::user();
        if (($user['perfil'] ?? '') !== 'ADMIN') {
            Response::abort(403, 'Acesso restrito a administradores.');
        }
    }

    /**
     * Valida nome de arquivo (basename, sem path traversal).
     *
     * @param string $filename Nome (possivelmente URL-encoded).
     *
     * @return string|null Nome seguro ou null se inválido.
     */
    private function safeguardFilename(string $filename): ?string
    {
        $decoded = urldecode($filename);
        $basename = basename($decoded);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return null;
        }
        if (str_contains($basename, '/') || str_contains($basename, '\\') || str_contains($basename, '..')) {
            return null;
        }
        if (!preg_match('/^backup_\d{8}_\d{6}\.sql$/i', $basename)) {
            return null;
        }
        return $basename;
    }

    /**
     * Verifica se mysqldump está disponível.
     *
     * @return bool
     */
    private function mysqldumpAvailable(): bool
    {
        $cmd = 'which mysqldump 2>/dev/null';
        $out = @shell_exec($cmd);
        return $out !== null && trim($out) !== '';
    }

    /**
     * Gera backup via mysqldump (exec).
     *
     * @param string  $targetPath Caminho destino.
     * @param string|null $errorMsg Saída de erro.
     *
     * @return bool
     */
    private function gerarViaMysqldump(string $targetPath, ?string &$errorMsg): bool
    {
        // Lê constantes globais definidas em /config.php.
        $host = defined('DB_HOST') ? DB_HOST : '127.0.0.1';
        $port = defined('DB_PORT') ? (int) DB_PORT : 3306;
        $name = defined('DB_NAME') ? DB_NAME : '';
        $user = defined('DB_USER') ? DB_USER : '';
        $pass = defined('DB_PASS') ? DB_PASS : '';

        $hostEsc = escapeshellarg((string) $host);
        $portEsc = ' -P ' . escapeshellarg((string) $port);
        $userEsc = escapeshellarg((string) $user);
        $passEsc = $pass !== '' ? ' -p' . escapeshellarg((string) $pass) : '';
        $dbEsc   = escapeshellarg((string) $name);
        $out     = escapeshellarg($targetPath);

        // Comando mysqldump.
        $cmd = "mysqldump -h {$hostEsc}{$portEsc} -u {$userEsc}{$passEsc} {$dbEsc} --routines --triggers --single-transaction > {$out} 2>&1";
        @exec($cmd, $output, $exitCode);

        if ($exitCode !== 0) {
            $errorMsg = 'mysqldump exit code ' . $exitCode . ': ' . implode("\n", $output);
            return false;
        }
        if (!is_file($targetPath) || filesize($targetPath) === 0) {
            $errorMsg = 'mysqldump gerou arquivo vazio.';
            return false;
        }
        return true;
    }

    /**
     * Gera backup iterando SHOW TABLES via PDO.
     *
     * @param string $targetPath Caminho destino.
     *
     * @return void
     *
     * @throws \Throwable Em caso de falha.
     */
    private function gerarViaPdo(string $targetPath): void
    {
        $pdo = Database::getInstance();

        $out = fopen($targetPath, 'w');
        if ($out === false) {
            throw new \RuntimeException('Não foi possível abrir arquivo de destino para escrita.');
        }

        fwrite($out, "-- Backup gerado em " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "-- Método: PDO fallback\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE='';\n\n");

        $stmt = $pdo->query('SHOW TABLES');
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $table = (string) $table;
            $tableEsc = '`' . str_replace('`', '``', $table) . '`';

            // CREATE TABLE.
            $createStmt = $pdo->query("SHOW CREATE TABLE {$tableEsc}");
            $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
            if ($createRow !== false) {
                fwrite($out, "\n-- Estrutura da tabela: {$table}\n");
                fwrite($out, "DROP TABLE IF EXISTS {$tableEsc};\n");
                fwrite($out, (string) ($createRow['Create Table'] ?? '') . ";\n\n");
            }

            // Dados.
            $rowsStmt = $pdo->query("SELECT * FROM {$tableEsc}");
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) > 0) {
                fwrite($out, "-- Dados da tabela: {$table}\n");
                $columns = array_keys($rows[0]);
                $colList = implode(', ', array_map(static fn (string $c): string => '`' . str_replace('`', '``', $c) . '`', $columns));

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($columns as $col) {
                        $val = $row[$col] ?? null;
                        if ($val === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote((string) $val);
                        }
                    }
                    fwrite($out, "INSERT INTO {$tableEsc} ({$colList}) VALUES (" . implode(', ', $values) . ");\n");
                }
                fwrite($out, "\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($out);
    }

    /**
     * Remove backups antigos mantendo apenas N últimos.
     *
     * @return void
     */
    private function limparAntigos(): void
    {
        $manter = (int) (new Configuracao())->get('backup_manter_ultimos', 10);
        $manter = max(1, $manter);

        $files = [];
        $scan = is_dir($this->backupDir) ? scandir($this->backupDir) : [];
        foreach ($scan as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $this->backupDir . '/' . $f;
            if (!is_file($path)) {
                continue;
            }
            $files[$f] = filemtime($path);
        }

        arsort($files); // mais recentes primeiro.
        $i = 0;
        foreach ($files as $f => $mtime) {
            $i++;
            if ($i > $manter) {
                @unlink($this->backupDir . '/' . $f);
            }
        }
    }
}
