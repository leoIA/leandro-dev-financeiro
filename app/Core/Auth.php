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
     * Verifica se há usuário autenticado válido.
     *
     * CORREÇÃO BUG #3: Antes destruíamos a sessão se IP/UA não batessem exatamente.
     * Em shared hosting (Hostinger, etc.) com load balancer ou proxy, o IP pode
     * mudar entre requests, destruindo sessões legítimas e causando loop de redirect.
     *
     * Nova estratégia:
     * - user_id deve estar na sessão
     * - last_activity deve estar dentro do timeout
     * - IP/UA: logar warning se divergir, mas NÃO destruir sessão (apenas invalidar)
     *   Apenas se o IP mudar E user_id não existir no DB → destruir
     *
     * @return bool
     */
    public static function check(): bool
    {
        $userId = self::id();
        if ($userId === null) {
            return false;
        }

        // Verifica timeout (destrói sessão se expirou)
        $lifetime = defined('SESSION_LIFETIME') ? (int) SESSION_LIFETIME * 60 : 1800;
        $lastActivity = Session::get('last_activity');
        if ($lastActivity !== null && (time() - (int) $lastActivity) > $lifetime) {
            Session::destroy();
            return false;
        }
        // Atualiza last_activity
        Session::set('last_activity', time());

        // Validação leve de IP/UA — loga mas não destrói (compatibilidade shared hosting)
        $sessionIp = Session::get('ip');
        $sessionUa = Session::get('user_agent');
        $currentIp = Request::ip();
        $currentUa = Request::userAgent();
        if ($sessionIp !== null && $sessionIp !== $currentIp) {
            // IP mudou — pode ser load balancer. Logar warning mas manter sessão.
            if (class_exists(Logger::class)) {
                Logger::error('IP divergente na sessão', [
                    'session_ip' => $sessionIp,
                    'current_ip' => $currentIp,
                    'user_id' => $userId,
                ]);
            }
        }
        if ($sessionUa !== null && $sessionUa !== $currentUa) {
            // UA mudou drasticamente — possivel hijack. Aqui sim, destruir.
            Session::destroy();
            return false;
        }

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
     * CORREÇÃO BUG #4: usar URL relativa (login.php) em vez de absoluta (/login.php)
     * para funcionar em subdiretório do host (Hostinger, etc.)
     *
     * @param string|null $modulo Módulo (ex.: "contas").
     * @param string|null $acao   Ação (ex.: "read", "create", "update", "delete").
     */
    public static function require(?string $modulo = null, ?string $acao = null): void
    {
        if (!self::check()) {
            Flash::warning('É necessário autenticar-se para continuar.');
            Response::redirect('login.php');
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
            Response::redirect('login.php');
        }
    }
}
