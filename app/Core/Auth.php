<?php
/**
 * @file Auth.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Sistema de autenticação e autorização baseado em sessão.
 *
 * - login(): define os dados de sessão e regenera o ID.
 * - check(): valida user_id + IP + UA + timeout.
 * - require(): redireciona para login ou aborta com 403 conforme permissão.
 * - hasPermission(): ADMIN tem todas implicitamente; demais usuarios consultam usuario_permissoes.
 */
final class Auth
{
    /**
     * Verifica se há usuário autenticado válido (IP + UA + timeout).
     *
     * @return bool
     */
    public static function check(): bool
    {
        $userId = self::id();
        if ($userId === null) {
            return false;
        }

        $sessionIp = Session::get('ip');
        $sessionUa = Session::get('user_agent');
        $currentIp = Request::ip();
        $currentUa = Request::userAgent();

        if ($sessionIp !== $currentIp || $sessionUa !== $currentUa) {
            // Sessão inválida — possivel hijack. Destroy.
            Session::destroy();
            return false;
        }

        self::checkTimeout();

        return true;
    }

    /**
     * Retorna o usuário autenticado completo ou null.
     *
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $id = self::id();
        if ($id === null) {
            return null;
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, nome, email, perfil, ativo, foto_path, ultimo_acesso FROM usuarios WHERE id = ? AND ativo = 1 LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Garante autenticação (e permissão se informada).
     *
     * @param string|null $modulo Módulo (ex.: "contas").
     * @param string|null $acao   Ação (ex.: "read", "create", "update", "delete").
     */
    public static function require(?string $modulo = null, ?string $acao = null): void
    {
        if (!self::check()) {
            Flash::warning('É necessário autenticar-se para continuar.');
            Response::redirect('/login.php');
        }

        if ($modulo !== null && $acao !== null) {
            if (!self::hasPermission($modulo, $acao)) {
                Response::abort(403, 'Você não tem permissão para acessar este recurso.');
            }
        }
    }

    /**
     * Realiza login do usuário — define sessão e regenera ID.
     *
     * @param array<string,mixed> $user Linha da tabela usuarios (deve conter id).
     */
    public static function login(array $user): void
    {
        Session::start();
        Session::regenerateId();

        Session::set('user_id', (int) $user['id']);
        Session::set('ip', Request::ip());
        Session::set('user_agent', Request::userAgent());
        Session::set('last_activity', time());

        // Atualiza último acesso no DB.
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_acesso = NOW(), tentativas_login = 0 WHERE id = ?');
            $stmt->execute([(int) $user['id']]);
        } catch (\Throwable $e) {
            Logger::error('Falha ao atualizar ultimo_acesso do usuario ' . $user['id'], ['exception' => $e->getMessage()]);
        }

        Logger::audit('LOGIN', 'auth', (int) $user['id']);
    }

    /**
     * Realiza logout do usuário atual.
     */
    public static function logout(): void
    {
        $id = self::id();
        if ($id !== null) {
            Logger::audit('LOGOUT', 'auth', $id);
        }
        Session::destroy();
    }

    /**
     * Verifica permissão do usuário atual para o módulo/ação informados.
     *
     * ADMIN tem todas implicitamente.
     *
     * @param string $modulo Módulo.
     * @param string $acao   Ação.
     *
     * @return bool
     */
    public static function hasPermission(string $modulo, string $acao): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $perfil = $user['perfil'] ?? null;
        if ($perfil === 'ADMIN') {
            return true;
        }

        $pdo = Database::getInstance();
        $sql = 'SELECT COUNT(*) AS total
                FROM usuario_permissoes up
                INNER JOIN permissoes p ON p.id = up.permissao_id
                WHERE up.usuario_id = ? AND p.modulo = ? AND p.acao = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([(int) $user['id'], $modulo, $acao]);
        $row = $stmt->fetch();

        return ((int) ($row['total'] ?? 0)) > 0;
    }

    /**
     * Retorna o ID do usuário autenticado ou null.
     *
     * @return int|null
     */
    public static function id(): ?int
    {
        $id = Session::get('user_id');
        if ($id === null) {
            return null;
        }
        return (int) $id;
    }

    /**
     * Verifica timeout de sessão e redireciona para login se expirado.
     *
     * Implementação: após o Session::checkTimeout() que destrói sessões
     * inativas, se ainda havia user_id mas a sessão foi destruída, redireciona.
     */
    public static function checkTimeout(): void
    {
        $hadUser = self::id() !== null;

        Session::checkTimeout();

        // Após Session::checkTimeout, se a sessão foi destruída por inatividade
        // e havia usuário logado, redireciona para login.
        if ($hadUser && self::id() === null) {
            Flash::warning('Sessão expirada por inatividade. Faça login novamente.');
            Response::redirect('/login.php');
        }
    }
}
