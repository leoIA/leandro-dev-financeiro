<?php
/**
 * @file    AuthController.php
 * @package App\Controllers
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Controller de autenticação: login (com rate limit por IP e por email),
 * logout e verificação de CSRF. Não depende de Auth::require() na action login
 * (pré-login) mas usa nas demais.
 */
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Usuario;
use App\Models\LogTentativaLogin;
use App\Helpers\Sanitizer;

class AuthController
{
    private const MAX_TENTATIVAS = 5;
    private const JANELA_MINUTOS  = 15;
    private const BLOQUEIO_MINUTOS = 15;

    /**
     * Exibe form de login (GET) ou processa login (POST).
     */
    public function login(): void
    {
        if (Auth::check()) {
            Response::redirect('index.php?route=dashboard');
        }

        if (Request::isPost()) {
            $this->handleLoginPost();
            return;
        }

        Response::view('auth/login', [
            'pageTitle' => 'Acessar o Sistema',
            'hideLayout' => true,
        ]);
    }

    /**
     * Processa POST de login: CSRF, rate limit, validação, autenticação,
     * regeneração de sessão, log e redirect.
     */
    private function handleLoginPost(): void
    {
        if (!Csrf::verify(Request::post('_csrf', ''))) {
            Flash::error('Token CSRF inválido. Recarregue a página e tente novamente.');
            Response::redirect('index.php?route=login');
        }

        $email    = Sanitizer::string(Request::post('email', ''));
        $senha    = (string) Request::post('senha', '');
        $ip       = Request::ip();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $validator = (new Validator(['email' => $email, 'senha' => $senha]))
            ->rule('email', 'required')
            ->rule('email', 'email')
            ->rule('senha', 'required');

        if (!$validator->validate()) {
            Flash::error('Informe email e senha válidos.');
            Response::redirect('index.php?route=login');
        }

        $tentativaModel = new LogTentativaLogin();
        $tentativasIp = $tentativaModel->countRecentByIp($ip, self::JANELA_MINUTOS);
        $tentativasEmail = $tentativaModel->countRecentByEmail($email, self::JANELA_MINUTOS);

        if ($tentativasIp >= self::MAX_TENTATIVAS || $tentativasEmail >= self::MAX_TENTATIVAS) {
            $tentativaModel->registrar($email, $ip, 0);
            Flash::error('Muitas tentativas inválidas. Aguarde '
                . self::BLOQUEIO_MINUTOS . ' minutos e tente novamente.');
            Response::redirect('index.php?route=login');
        }

        $usuarioModel = new Usuario();
        $usuario = $usuarioModel->findByEmail($email);

        $credenciaisValidas = false;
        if ($usuario !== null) {
            if (!empty($usuario['bloqueado_ate']) && strtotime($usuario['bloqueado_ate']) > time()) {
                $tentativaModel->registrar($email, $ip, 0);
                Flash::error('Usuário bloqueado até '
                    . date('d/m/Y H:i', strtotime($usuario['bloqueado_ate']))
                    . '. Tente mais tarde.');
                Response::redirect('index.php?route=login');
            }
            $credenciaisValidas = password_verify($senha, $usuario['senha_hash']);
        }

        if (!$credenciaisValidas) {
            $tentativaModel->registrar($email, $ip, 0);
            $novasTentativas = $tentativaModel->countRecentByEmail($email, self::JANELA_MINUTOS);
            if ($novasTentativas >= self::MAX_TENTATIVAS && $usuario !== null) {
                $usuarioModel->bloquear($usuario['id'], self::BLOQUEIO_MINUTOS);
            }
            Flash::error('Credenciais inválidas.');
            Response::redirect('index.php?route=login');
        }

        if ((int) $usuario['ativo'] !== 1) {
            $tentativaModel->registrar($email, $ip, 0);
            Flash::error('Usuário desativado. Contate o administrador.');
            Response::redirect('index.php?route=login');
        }

        $tentativaModel->registrar($email, $ip, 1);
        $usuarioModel->resetarTentativas((int) $usuario['id']);
        $usuarioModel->atualizarUltimoAcesso((int) $usuario['id']);

        Auth::login($usuario);

        Flash::success('Bem-vindo(a), ' . $usuario['nome'] . '!');
        Response::redirect('index.php?route=dashboard');
    }

    /**
     * Logout: encerra sessão e redireciona para login.
     */
    public function logout(): void
    {
        if (Auth::check()) {
            Auth::logout();
            Flash::success('Logout realizado com sucesso.');
        }
        Response::redirect('index.php?route=login');
    }

    /**
     * Helper estático opcional — tenta autenticar email/senha e retorna o
     * usuário (array) ou false. Não registra sessão; apenas valida credenciais
     * e logs de tentativa. Útil para integrações CLI ou testes.
     *
     * @param string $email    Email.
     * @param string $password Senha em texto plano.
     *
     * @return array<string,mixed>|false
     */
    public static function attemptLogin(string $email, string $password): array|false
    {
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return false;
        }

        $usuario = (new Usuario())->findByEmail($email);
        if ($usuario === null) {
            return false;
        }

        // Bloqueado?
        if (!empty($usuario['bloqueado_ate']) && strtotime((string) $usuario['bloqueado_ate']) > time()) {
            return false;
        }
        // Inativo?
        if ((int) ($usuario['ativo'] ?? 0) !== 1) {
            return false;
        }

        if (!\App\Core\Hash::verify($password, (string) ($usuario['senha_hash'] ?? ''))) {
            return false;
        }

        return $usuario;
    }
}
