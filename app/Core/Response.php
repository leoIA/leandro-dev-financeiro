<?php
/**
 * @file Response.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Helpers de resposta HTTP: view, redirect, json, download, abort.
 *
 * view() renderiza app/Views/<template>.php envolvendo os layouts
 * header, sidebar, topbar, <template>, footer (nesta ordem).
 */
final class Response
{
    /**
     * Renderiza uma view PHP com layouts.
     *
     * @param string                $template    Caminho relativo em app/Views (ex.: "contas/index").
     * @param array<string,mixed>   $data        Variáveis extraídas para o escopo da view.
     * @param int                   $statusCode  Código HTTP.
     */
    public static function view(string $template, array $data = [], int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $viewFile = dirname(__DIR__) . '/Views/' . $template . '.php';
        if (!is_file($viewFile)) {
            self::abort(500, 'View não encontrada: ' . $template);
            return;
        }

        extract($data, EXTR_SKIP);

        $layoutDir = dirname(__DIR__) . '/Layouts/';

        // Layouts opcionais — só inclui se existirem.
        $headerFile  = $layoutDir . 'header.php';
        $sidebarFile = $layoutDir . 'sidebar.php';
        $topbarFile  = $layoutDir . 'topbar.php';
        $footerFile  = $layoutDir . 'footer.php';

        if (is_file($headerFile)) {
            require $headerFile;
        }
        if (is_file($sidebarFile)) {
            require $sidebarFile;
        }
        if (is_file($topbarFile)) {
            require $topbarFile;
        }

        require $viewFile;

        if (is_file($footerFile)) {
            require $footerFile;
        }
    }

    /**
     * Redireciona (Location header) e encerra a execução.
     *
     * @param string $url  URL absoluta ou relativa.
     * @param int    $code Código HTTP (302 default).
     */
    public static function redirect(string $url, int $code = 302): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Location: ' . $url);
        }
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
            . '<title>Redirecionando…</title></head><body>'
            . '<script>window.location.href=' . json_encode($url) . ';</script>'
            . '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Continuar…</a>'
            . '</body></html>';
        exit;
    }

    /**
     * Resposta JSON.
     *
     * @param array<string,mixed>|list<mixed> $data        Payload.
     * @param int                             $statusCode  Código HTTP.
     */
    public static function json(array $data, int $statusCode = 200): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * Envia um arquivo para download.
     *
     * @param string      $filePath Caminho absoluto do arquivo.
     * @param string|null $fileName Nome sugerido ao browser.
     * @param string|null $mime     MIME type (auto-detect se null).
     */
    public static function download(string $filePath, ?string $fileName = null, ?string $mime = null): void
    {
        $realPath = realpath($filePath);
        if ($realPath === false || !is_file($realPath)) {
            self::abort(404, 'Arquivo não encontrado.');
            return;
        }

        $fileName = $fileName ?? basename($realPath);
        $mime = $mime ?? (function_exists('mime_content_type') ? (string) mime_content_type($realPath) : 'application/octet-stream');

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($realPath));
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $fileName) . '"');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        readfile($realPath);
        exit;
    }

    /**
     * Aborta com código HTTP e exibe a view de erro correspondente se houver.
     *
     * @param int    $code    Código HTTP (404, 403, 500).
     * @param string $message Mensagem de fallback.
     */
    public static function abort(int $code, string $message = ''): void
    {
        if (!headers_sent()) {
            http_response_code($code);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $viewFile = dirname(__DIR__) . '/Views/errors/' . $code . '.php';
        if (is_file($viewFile)) {
            require $viewFile;
            exit;
        }

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<title>Erro ' . $code . '</title></head><body>'
            . '<h1>Erro ' . $code . '</h1>'
            . '<p>' . htmlspecialchars($message !== '' ? $message : 'Ocorreu um erro.', ENT_QUOTES, 'UTF-8') . '</p>'
            . '</body></html>';
        exit;
    }
}
