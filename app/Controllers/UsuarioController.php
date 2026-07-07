<?php
/**
 * @file    UsuarioController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de Usuários e Perfil.
 *
 * Rotas:
 *   GET  /usuarios                  → index() — listar (ADMIN)
 *   POST /usuarios                  → index() delega novo() — criar (ADMIN)
 *   GET  /usuarios/novo             → novo() — form (ADMIN)
 *   GET  /usuarios/{id}/editar      → editar($id) — form (ADMIN)
 *   POST /usuarios/{id}             → index($id) delega editar() — atualizar (_method=PUT, ADMIN)
 *   POST /usuarios/{id}/desativar   → desativar($id) (ADMIN)
 *   POST /usuarios/{id}/ativar      → ativar($id) (ADMIN)
 *   GET  /perfil                    → perfil() — form próprio
 *   POST /perfil                    → perfil() — atualizar próprios dados
 *
 * Regras:
 *   - ADMIN-only em index/novo/editar/desativar.
 *   - Perfil é acessível por qualquer usuário autenticado.
 *   - Não pode desativar a si mesmo.
 *   - Não pode mudar próprio perfil.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Hash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Permissao;
use App\Models\Usuario;

class UsuarioController
{
    /**
     * GET  /usuarios — listar (ADMIN).
     * POST /usuarios — criar (delega para novo()).
     * POST /usuarios/{id} — atualizar (delega para editar()).
     *
     * @param string $id ID opcional.
     *
     * @return void
     */
    public function index(string $id = ''): void
    {
        if (Request::isPost() && $id === '') {
            $this->novo();
            return;
        }
        if (Request::isPost() && $id !== '') {
            $this->editar($id);
            return;
        }
        if ($id !== '') {
            Response::redirect('index.php?route=usuarios/' . urlencode($id) . '/editar');
            return;
        }

        $this->requireAdmin();

        $usuarios = (new Usuario())->all(['nome' => 'ASC']);

        Response::view('usuarios/index', [
            'pageTitle' => 'Usuários',
            'usuarios'  => $usuarios,
        ]);
    }

    /**
     * GET  /usuarios/novo — form (ADMIN).
     * POST /usuarios      — valida e cria com permissões.
     *
     * @return void
     */
    public function novo(): void
    {
        $this->requireAdmin();

        if (!Request::isPost()) {
            Response::view('usuarios/create', [
                'pageTitle' => 'Novo Usuário',
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=usuarios/novo');
        }

        $nome  = trim((string) Request::post('nome', ''));
        $email = strtolower(trim((string) Request::post('email', '')));
        $senha = (string) Request::post('senha', '');
        $confirmar = (string) Request::post('confirmar_senha', '');
        $perfil = (string) Request::post('perfil', '');
        $ativo  = Request::post('ativo') === '1' ? 1 : 0;
        $permissoes = $this->collectPermissoesFromRequest();

        $errors = [];
        if (mb_strlen($nome) < 3) {
            $errors[] = 'Nome deve ter no mínimo 3 caracteres.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Email inválido.';
        } else {
            // Unicidade.
            $v = (new Validator(['email' => $email]))
                ->rule('email', 'unique', ['table' => 'usuarios', 'column' => 'email']);
            if (!$v->validate()) {
                $errors[] = 'Email já cadastrado.';
            }
        }
        if (mb_strlen($senha) < 8) {
            $errors[] = 'Senha deve ter no mínimo 8 caracteres.';
        }
        if (!$this->senhaForte($senha)) {
            $errors[] = 'Senha deve conter letras, números e símbolos.';
        }
        if ($senha !== $confirmar) {
            $errors[] = 'As senhas não conferem.';
        }
        if (!in_array($perfil, ['ADMIN', 'OPERADOR', 'VISUALIZADOR'], true)) {
            $errors[] = 'Perfil inválido.';
        }

        if (count($errors) > 0) {
            Flash::error(implode(' ', $errors));
            Response::view('usuarios/create', [
                'pageTitle' => 'Novo Usuário',
                'old'       => array_merge(Request::all(), ['permissoes' => $permissoes]),
            ]);
            return;
        }

        $data = [
            'nome'         => $nome,
            'email'        => $email,
            'senha_hash'   => Hash::make($senha),
            'perfil'       => $perfil,
            'ativo'        => $ativo,
            'tentativas_login' => 0,
        ];

        try {
            $usuarioId = (new Usuario())->createWithPermissoes($data, $this->resolvePermissaoIds($permissoes));
            Flash::success('Usuário criado com sucesso.');
            Response::redirect('index.php?route=usuarios/' . $usuarioId . '/editar');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao criar usuário: ' . $e->getMessage());
            Flash::error('Erro ao criar usuário: ' . $e->getMessage());
            Response::view('usuarios/create', [
                'pageTitle' => 'Novo Usuário',
                'old'       => array_merge(Request::all(), ['permissoes' => $permissoes]),
            ]);
        }
    }

    /**
     * GET  /usuarios/{id}/editar — form (ADMIN).
     * POST /usuarios/{id}        — valida e atualiza.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function editar(string $id): void
    {
        $this->requireAdmin();

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->find((int) $id);

        if ($usuario === null) {
            Flash::error('Usuário não encontrado.');
            Response::redirect('index.php?route=usuarios');
        }

        $permissaoModel = new Permissao();
        $permissoesUsuario = $permissaoModel->idsForUser((int) $id);

        // Converte IDs para o formato "modulo.acao" para a view marcar checkboxes.
        $catalog = $permissaoModel->allCatalog();
        $permsUsuarioFormat = [];
        foreach ($catalog as $p) {
            if (in_array((int) $p['id'], $permissoesUsuario, true)) {
                $permsUsuarioFormat[] = (string) $p['modulo'] . '.' . (string) $p['acao'];
            }
        }

        if (!Request::isPost()) {
            Response::view('usuarios/edit', [
                'pageTitle'         => 'Editar Usuário',
                'usuario'           => $usuario,
                'permissoesUsuario' => $permsUsuarioFormat,
                'old'               => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=usuarios/' . $id . '/editar');
        }

        $authId = Auth::id();
        $isSelf = $authId !== null && $authId === (int) $id;

        // Preserva perfil e ativo se for self-edit.
        $perfil = $isSelf ? (string) ($usuario['perfil'] ?? '') : (string) Request::post('perfil', '');
        $ativo  = $isSelf ? 1 : (Request::post('ativo') === '1' ? 1 : 0);

        $nome  = trim((string) Request::post('nome', ''));
        $email = strtolower(trim((string) Request::post('email', '')));
        $senha = (string) Request::post('senha', '');
        $confirmar = (string) Request::post('confirmar_senha', '');

        $errors = [];
        if (mb_strlen($nome) < 3) {
            $errors[] = 'Nome deve ter no mínimo 3 caracteres.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Email inválido.';
        } else {
            $v = (new Validator(['email' => $email]))
                ->rule('email', 'unique', ['table' => 'usuarios', 'column' => 'email', 'except' => (int) $id]);
            if (!$v->validate()) {
                $errors[] = 'Email já cadastrado para outro usuário.';
            }
        }
        if ($senha !== '') {
            if (mb_strlen($senha) < 8) {
                $errors[] = 'Senha deve ter no mínimo 8 caracteres.';
            }
            if (!$this->senhaForte($senha)) {
                $errors[] = 'Senha deve conter letras, números e símbolos.';
            }
            if ($senha !== $confirmar) {
                $errors[] = 'As senhas não conferem.';
            }
        }

        if (count($errors) > 0) {
            Flash::error(implode(' ', $errors));
            Response::view('usuarios/edit', [
                'pageTitle'         => 'Editar Usuário',
                'usuario'           => $usuario,
                'permissoesUsuario' => $permsUsuarioFormat,
                'old'               => array_merge(Request::all(), ['permissoes' => $this->collectPermissoesFromRequest()]),
            ]);
            return;
        }

        $data = [
            'nome'   => $nome,
            'email'  => $email,
            'perfil' => $perfil,
            'ativo'  => $ativo,
        ];
        if ($senha !== '') {
            $data['senha_hash'] = Hash::make($senha);
        }

        try {
            $usuarioModel->update((int) $id, $data);

            // Atualiza permissões (apenas se não for ADMIN e não for self-edit de perfil).
            if ($perfil !== 'ADMIN' && !$isSelf) {
                $permissoes = $this->collectPermissoesFromRequest();
                $permissaoModel->vincularAoUsuario((int) $id, $this->resolvePermissaoIds($permissoes));
            }

            Flash::success('Usuário atualizado com sucesso.');
            Response::redirect('index.php?route=usuarios');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar usuário: ' . $e->getMessage());
            Flash::error('Erro ao atualizar usuário: ' . $e->getMessage());
            Response::view('usuarios/edit', [
                'pageTitle'         => 'Editar Usuário',
                'usuario'           => $usuario,
                'permissoesUsuario' => $permsUsuarioFormat,
                'old'               => array_merge(Request::all(), ['permissoes' => $this->collectPermissoesFromRequest()]),
            ]);
        }
    }

    /**
     * POST /usuarios/{id}/desativar — soft-delete (não pode desativar a si).
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function desativar(string $id): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=usuarios');
        }

        $authId = Auth::id();
        if ($authId !== null && $authId === (int) $id) {
            Flash::error('Não é possível desativar o próprio usuário.');
            Response::redirect('index.php?route=usuarios');
        }

        try {
            (new Usuario())->softDelete((int) $id);
            Flash::success('Usuário desativado com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao desativar usuário: ' . $e->getMessage());
            Flash::error('Erro ao desativar usuário.');
        }
        Response::redirect('index.php?route=usuarios');
    }

    /**
     * POST /usuarios/{id}/ativar — reativa.
     *
     * @param string $id ID.
     *
     * @return void
     */
    public function ativar(string $id): void
    {
        $this->requireAdmin();

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=usuarios');
        }

        try {
            (new Usuario())->update((int) $id, ['ativo' => 1, 'tentativas_login' => 0, 'bloqueado_ate' => null]);
            Flash::success('Usuário reativado com sucesso.');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao ativar usuário: ' . $e->getMessage());
            Flash::error('Erro ao ativar usuário.');
        }
        Response::redirect('index.php?route=usuarios');
    }

    /**
     * GET  /perfil — form de perfil próprio.
     * POST /perfil — atualizar próprios dados (nome, email, senha, foto).
     *
     * @return void
     */
    public function perfil(): void
    {
        Auth::require();

        $authId = Auth::id();
        $usuarioModel = new Usuario();
        $usuario = $authId !== null ? $usuarioModel->find($authId) : null;

        if ($usuario === null) {
            Flash::error('Usuário não encontrado.');
            Response::redirect('login.php');
        }

        if (!Request::isPost()) {
            Response::view('usuarios/perfil', [
                'pageTitle' => 'Meu Perfil',
                'usuario'   => $usuario,
                'old'       => [],
            ]);
            return;
        }

        if (!Csrf::verify((string) Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido.');
            Response::redirect('index.php?route=perfil');
        }

        $nome  = trim((string) Request::post('nome', ''));
        $email = strtolower(trim((string) Request::post('email', '')));
        $senha = (string) Request::post('senha', '');
        $confirmar = (string) Request::post('confirmar_senha', '');

        $errors = [];
        if (mb_strlen($nome) < 3) {
            $errors[] = 'Nome deve ter no mínimo 3 caracteres.';
        }
        if (!Validator::email($email)) {
            $errors[] = 'Email inválido.';
        } else {
            $v = (new Validator(['email' => $email]))
                ->rule('email', 'unique', ['table' => 'usuarios', 'column' => 'email', 'except' => (int) $usuario['id']]);
            if (!$v->validate()) {
                $errors[] = 'Email já cadastrado para outro usuário.';
            }
        }
        if ($senha !== '') {
            if (mb_strlen($senha) < 8) {
                $errors[] = 'Senha deve ter no mínimo 8 caracteres.';
            }
            if (!$this->senhaForte($senha)) {
                $errors[] = 'Senha deve conter letras, números e símbolos.';
            }
            if ($senha !== $confirmar) {
                $errors[] = 'As senhas não conferem.';
            }
        }

        // Upload de foto (opcional).
        $fotoPath = (string) ($usuario['foto_path'] ?? '');
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $result = $this->handleFotoUpload($_FILES['foto']);
            if ($result['error'] !== null) {
                $errors[] = $result['error'];
            } elseif ($result['path'] !== null) {
                $fotoPath = $result['path'];
            }
        }

        if (count($errors) > 0) {
            Flash::error(implode(' ', $errors));
            Response::view('usuarios/perfil', [
                'pageTitle' => 'Meu Perfil',
                'usuario'   => $usuario,
                'old'       => Request::all(),
            ]);
            return;
        }

        $data = [
            'nome' => $nome,
            'email' => $email,
            'foto_path' => $fotoPath !== '' ? $fotoPath : null,
        ];
        if ($senha !== '') {
            $data['senha_hash'] = Hash::make($senha);
        }

        try {
            $usuarioModel->update((int) $usuario['id'], $data);
            Flash::success('Perfil atualizado com sucesso.');
            Response::redirect('index.php?route=perfil');
        } catch (\Throwable $e) {
            \App\Core\Logger::error('Falha ao atualizar perfil: ' . $e->getMessage());
            Flash::error('Erro ao atualizar perfil: ' . $e->getMessage());
            Response::view('usuarios/perfil', [
                'pageTitle' => 'Meu Perfil',
                'usuario'   => $usuario,
                'old'       => Request::all(),
            ]);
        }
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Exige perfil ADMIN ou aborta 403.
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
     * Coleta permissões do POST (formato: permissoes[modulo.acao] = 1).
     *
     * @return list<string>
     */
    private function collectPermissoesFromRequest(): array
    {
        $raw = Request::post('permissoes', []);
        if (!is_array($raw)) {
            return [];
        }
        return array_keys($raw);
    }

    /**
     * Converte lista de "modulo.acao" em IDs de permissões via catálogo.
     *
     * @param list<string> $permissoes Chaves "modulo.acao".
     *
     * @return list<int>
     */
    private function resolvePermissaoIds(array $permissoes): array
    {
        $catalog = (new Permissao())->allCatalog();
        $map = [];
        foreach ($catalog as $p) {
            $key = (string) $p['modulo'] . '.' . (string) $p['acao'];
            $map[$key] = (int) $p['id'];
        }
        $ids = [];
        foreach ($permissoes as $key) {
            if (isset($map[$key])) {
                $ids[] = $map[$key];
            }
        }
        return $ids;
    }

    /**
     * Verifica se a senha é "forte": >=8 chars, com letra, número e símbolo.
     *
     * @param string $senha Senha.
     *
     * @return bool
     */
    private function senhaForte(string $senha): bool
    {
        if (mb_strlen($senha) < 8) {
            return false;
        }
        if (!preg_match('/[A-Za-z]/', $senha)) {
            return false;
        }
        if (!preg_match('/\d/', $senha)) {
            return false;
        }
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) {
            return false;
        }
        return true;
    }

    /**
     * Processa upload de foto do perfil.
     *
     * @param array<string,mixed> $file $_FILES['foto'].
     *
     * @return array{error: ?string, path: ?string} Tupla erro/path.
     */
    private function handleFotoUpload(array $file): array
    {
        if ($file['error'] === UPLOAD_ERR_NO_FILE) {
            return ['error' => null, 'path' => null];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Erro no upload da foto (código ' . $file['error'] . ').', 'path' => null];
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            return ['error' => 'Foto excede o tamanho máximo de 2MB.', 'path' => null];
        }

        $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg'];
        $mime = (string) ($file['type'] ?? '');
        if (!isset($allowed[$mime])) {
            return ['error' => 'Formato de foto inválido. Use JPG ou PNG.', 'path' => null];
        }
        $ext = $allowed[$mime];

        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $fileName = 'user_' . (string) (Auth::id() ?? 0) . '_' . date('Ymd_His') . '.' . $ext;
        $targetPath = $uploadDir . '/' . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['error' => 'Falha ao salvar a foto.', 'path' => null];
        }

        // Redimensiona para 200x200 se for imagem raster e GD disponível.
        if (function_exists('imagecreatetruecolor') && in_array($ext, ['jpg', 'png'], true)) {
            $this->redimensionarFoto($targetPath, $ext, 200, 200);
        }

        return ['error' => null, 'path' => '/storage/uploads/' . $fileName];
    }

    /**
     * Redimensiona imagem quadrada para N x N pixels (crop central).
     *
     * @param string $path Caminho absoluto.
     * @param string $ext  Extensão (jpg|png).
     * @param int    $w    Largura final.
     * @param int    $h    Altura final.
     *
     * @return void
     */
    private function redimensionarFoto(string $path, string $ext, int $w, int $h): void
    {
        $src = $ext === 'png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);
        if ($src === false) {
            return;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $size = min($sw, $sh);
        $x = (int) (($sw - $size) / 2);
        $y = (int) (($sh - $size) / 2);

        $dst = imagecreatetruecolor($w, $h);
        imagecopyresampled($dst, $src, 0, 0, $x, $y, $w, $h, $size, $size);

        if ($ext === 'png') {
            imagepng($dst, $path, 9);
        } else {
            imagejpeg($dst, $path, 85);
        }
        imagedestroy($src);
        imagedestroy($dst);
    }
}
