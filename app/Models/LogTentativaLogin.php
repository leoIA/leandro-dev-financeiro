<?php
/**
 * @file LogTentativaLogin.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de logs de tentativas de login (rate limiting).
 *
 * Suporta duas assinaturas de registrar():
 *   registrar(string $email, bool $sucesso)              — spec T03 (2 args).
 *   registrar(string $email, string $ip, int $sucesso)   — AuthController (3 args).
 */

declare(strict_types=1);

namespace App\Models;

use App\Core\Request;
use PDO;
use RuntimeException;

class LogTentativaLogin extends BaseModel
{
    protected string $table = 'logs_tentativas_login';
    protected string $modulo = 'auth';

    /**
     * Registra uma tentativa de login.
     *
     * Aceita 2 args (spec: email + sucesso) ou 3 args (AuthController: email + ip + sucesso).
     *
     * @param string          $email         Email.
     * @param string|bool|int $ipOrSucesso   IP (string) quando 3 args, sucesso (bool/int) quando 2 args.
     * @param int|null        $sucesso       Sucesso (0/1) quando 3 args.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function registrar(string $email, string|bool|int $ipOrSucesso, ?int $sucesso = null): void
    {
        if (is_string($ipOrSucesso)) {
            // 3 args: email, ip, sucesso(int 0|1).
            $ip = $ipOrSucesso;
            $success = $sucesso !== null ? ((bool) $sucesso) : false;
        } else {
            // 2 args: email, sucesso(bool|int).
            $ip = Request::ip();
            $success = (bool) $ipOrSucesso;
        }

        try {
            $sql = 'INSERT INTO logs_tentativas_login (email, ip, sucesso, criado_em)
                    VALUES (?, ?, ?, NOW())';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $email,
                $ip,
                $success ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao registrar tentativa de login: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Conta tentativas falhas de um IP nos últimos N minutos.
     *
     * @param string $ip      IP.
     * @param int    $minutos Janela em minutos.
     *
     * @return int
     */
    public function tentativasPorIp(string $ip, int $minutos = 15): int
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM logs_tentativas_login
                WHERE ip = ?
                  AND sucesso = 0
                  AND criado_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $ip);
        $stmt->bindValue(2, $minutos, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Alias (compatibilidade com AuthController).
     *
     * @param string $ip      IP.
     * @param int    $minutos Janela em minutos.
     *
     * @return int
     */
    public function countRecentByIp(string $ip, int $minutos = 15): int
    {
        return $this->tentativasPorIp($ip, $minutos);
    }

    /**
     * Conta tentativas falhas de um email nos últimos N minutos.
     *
     * @param string $email   Email.
     * @param int    $minutos Janela em minutos.
     *
     * @return int
     */
    public function tentativasPorEmail(string $email, int $minutos = 15): int
    {
        $sql = 'SELECT COUNT(*) AS total
                FROM logs_tentativas_login
                WHERE email = ?
                  AND sucesso = 0
                  AND criado_em >= DATE_SUB(NOW(), INTERVAL ? MINUTE)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $email);
        $stmt->bindValue(2, $minutos, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Alias (compatibilidade com AuthController).
     *
     * @param string $email   Email.
     * @param int    $minutos Janela em minutos.
     *
     * @return int
     */
    public function countRecentByEmail(string $email, int $minutos = 15): int
    {
        return $this->tentativasPorEmail($email, $minutos);
    }

    /**
     * Limpa tentativas antigas (default 30 dias).
     *
     * @param int $dias Dias a manter.
     *
     * @return int Linhas removidas.
     */
    public function limparAntigos(int $dias = 30): int
    {
        $sql = 'DELETE FROM logs_tentativas_login WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(1, $dias, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
