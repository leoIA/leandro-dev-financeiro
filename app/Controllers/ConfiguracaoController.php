<?php
/**
 * @file    ConfiguracaoController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Configurações (ADMIN only).
 *
 * Rotas:
 *   GET  /configuracoes                  → index() — abas (empresa, seguranca, backup, sistema)
 *   POST /configuracoes/empresa          → empresa() — salvar aba empresa
 *   POST /configuracoes/seguranca        → seguranca() — salvar aba segurança
 *   POST /configuracoes/backup           → backup() — salvar aba backup
 *   POST /configuracoes/sistema          → sistema() — salvar aba sistema
 *   POST /configuracoes/restaurar-padroes→ restaurarPadroes()
 *
 * Upload de logo é tratado dentro de empresa().
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\Configuracao;

class ConfiguracaoController
{
    /**
     * GET /configuracoes — exibe todas as abas.
     *
     * @return void
     */
    public function index(): void
    {
        $this->requireAdmin();

        $config = (new Configuracao())->all();

        Response::view('configuracoes/index', [
            'pageTitle' => 'Configurações',
            'config'    => $config,
        ]);
    }

    /**
     * POST /configuracoes/empresa — salvar dados da empresa + upload de logo.
     *
     * @return void
     */
    public function empresa(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/configuracoes');
        }

        $cfg = new Configuracao();

        $fields = [
            'empresa_razao_social' => trim((string) Request::post('empresa_razao_social', '')),
            'empresa_cnpj'         => preg_replace('/\D/', '', (string) Request::post('empresa_cnpj', '')) ?: '',
            'empresa_telefone'     => trim((string) Request::post('empresa_telefone', '')) ?: '',
            'empresa_email'        => trim((string) Request::post('empresa_email', '')) ?: '',
            'empresa_endereco'     => trim((string) Request::post('empresa_endereco', '')) ?: '',
            'empresa_numero'       => trim((string) Request::post('empresa_numero', '')) ?: '',
            'empresa_bairro'       => trim((string) Request::post('empresa_bairro', '')) ?: '',
            'empresa_cidade'       => trim((string) Request::post('empresa_cidade', '')) ?: '',
            'empresa_uf'           => trim((string) Request::post('empresa_uf', '')) ?: '',
            'empresa_cep'          => preg_replace('/\D/', '', (string) Request::post('empresa_cep', '')) ?: '',
        ];

        foreach ($fields as $k => $v) {
            $cfg->set($k, $v);
        }

        // Upload de logo.
        if (isset($_FILES['empresa_logo']) && $_FILES['empresa_logo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $logoError = $this->handleLogoUpload($_FILES['empresa_logo']);
            if ($logoError !== null) {
                Flash::error($logoError);
            } else {
                Flash::success('Logo da empresa atualizada.');
            }
        }

        Flash::success('Configurações da empresa salvas com sucesso.');
        Response::redirect('/configuracoes');
    }

    /**
     * POST /configuracoes/seguranca — salvar configurações de segurança.
     *
     * @return void
     */
    public function seguranca(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/configuracoes');
        }

        $cfg = new Configuracao();
        $cfg->set('session_timeout_min', max(1, min(1440, (int) Request::post('session_timeout_min', 30))));
        $cfg->set('tentativas_login_max', max(1, min(20, (int) Request::post('tentativas_login_max', 5))));
        $cfg->set('bloqueio_login_min', max(1, min(1440, (int) Request::post('bloqueio_login_min', 15))));

        Flash::success('Configurações de segurança salvas com sucesso.');
        Response::redirect('/configuracoes');
    }

    /**
     * POST /configuracoes/backup — salvar configurações de backup.
     *
     * @return void
     */
    public function backup(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/configuracoes');
        }

        $cfg = new Configuracao();
        $cfg->set('backup_automatico', Request::post('backup_automatico') === '1');
        $cfg->set('backup_manter_ultimos', max(1, min(100, (int) Request::post('backup_manter_ultimos', 10))));
        $cfg->set('backup_dia_mes', max(1, min(28, (int) Request::post('backup_dia_mes', 1))));

        Flash::success('Configurações de backup salvas com sucesso.');
        Response::redirect('/configuracoes');
    }

    /**
     * POST /configuracoes/sistema — salvar (na prática: nada, pois sistema é fixo).
     *
     * @return void
     */
    public function sistema(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/configuracoes');
        }

        Flash::info('Configurações de sistema são fixas e não foram alteradas.');
        Response::redirect('/configuracoes');
    }

    /**
     * POST /configuracoes/restaurar-padroes — restaura todos os defaults.
     *
     * @return void
     */
    public function restaurarPadroes(): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('/configuracoes');
        }

        try {
            (new Configuracao())->resetDefaults();
            Flash::warning('Configurações restauradas para os padrões de fábrica.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao restaurar padrões: ' . $e->getMessage());
            Flash::error('Erro ao restaurar padrões: ' . $e->getMessage());
        }
        Response::redirect('/configuracoes');
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
     * Processa upload do logo da empresa.
     *
     * @param array<string,mixed> $file $_FILES['empresa_logo'].
     *
     * @return string|null Mensagem de erro ou null em caso de sucesso.
     */
    private function handleLogoUpload(array $file): ?string
    {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'Erro no upload do logo (código ' . $file['error'] . ').';
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            return 'Logo excede o tamanho máximo de 2MB.';
        }

        $allowed = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/svg+xml' => 'svg',
        ];
        $mime = (string) ($file['type'] ?? '');
        if (!isset($allowed[$mime])) {
            return 'Formato inválido. Use JPG, PNG ou SVG.';
        }
        $ext = $allowed[$mime];

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $fileName = 'logo_empresa.' . $ext;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return 'Falha ao salvar o logo.';
        }

        // Redimensiona 200x60 se for raster.
        if (function_exists('imagecreatetruecolor') && in_array($ext, ['jpg', 'png'], true)) {
            $this->redimensionarLogo($targetPath, $ext, 200, 60);
        }

        (new Configuracao())->set('empresa_logo_path', '/storage/uploads/' . $fileName);
        return null;
    }

    /**
     * Redimensiona logo preservando proporção dentro de W x H.
     *
     * @param string $path Caminho.
     * @param string $ext  Ext.
     * @param int    $maxW Largura máxima.
     * @param int    $maxH Altura máxima.
     *
     * @return void
     */
    private function redimensionarLogo(string $path, string $ext, int $maxW, int $maxH): void
    {
        $src = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if ($src === false) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        if ($sw <= $maxW && $sh <= $maxH) {
            imagedestroy($src);
            return;
        }
        $ratio = min($maxW / $sw, $maxH / $sh);
        $dw = (int) round($sw * $ratio);
        $dh = (int) round($sh * $ratio);

        $dst = imagecreatetruecolor($dw, $dh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dw, $dh, $sw, $sh);

        if ($ext === 'png') {
            imagepng($dst, $path, 9);
        } else {
            imagejpeg($dst, $path, 85);
        }
        imagedestroy($src);
        imagedestroy($dst);
    }
}
