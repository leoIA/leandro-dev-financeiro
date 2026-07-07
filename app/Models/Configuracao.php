<?php
/**
 * @file Configuracao.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de configurações (tabela configuracoes — key/value global).
 *
 * Cache em array estática para evitar queries repetidas.
 */

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

class Configuracao extends BaseModel
{
    protected string $table = 'configuracoes';
    protected string $modulo = 'configuracoes';

    /** @var array<string,mixed> */
    private static array $cache = [];

    private static bool $loadedAll = false;

    /**
     * Lê uma configuração pela chave (com cache).
     *
     * @param string $chave   Chave.
     * @param mixed  $default Default.
     *
     * @return mixed
     */
    public function get(string $chave, mixed $default = null): mixed
    {
        $this->loadAll();
        if (array_key_exists($chave, self::$cache)) {
            return self::$cache[$chave];
        }
        return $default;
    }

    /**
     * UPSERT de uma configuração. Invalida cache.
     *
     * @param string $chave Chave.
     * @param mixed  $valor Valor.
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function set(string $chave, mixed $valor): void
    {
        $valorStr = is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE) : (string) $valor;
        $tipo = $this->detectType($valor);

        $sql = 'INSERT INTO configuracoes (chave, valor, tipo)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor), tipo = VALUES(tipo), atualizado_em = NOW()';

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$chave, $valorStr, $tipo]);
            self::$cache[$chave] = $valor;
        } catch (\Throwable $e) {
            throw new RuntimeException('Falha ao salvar configuração: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Retorna todas as configurações como [chave => valor_tipado].
     *
     * @param array<string,string> $order Ignorado (compatibilidade de assinatura com BaseModel).
     *
     * @return array<string,mixed>
     */
    public function all(array $order = []): array
    {
        $this->loadAll();
        return self::$cache;
    }

    /**
     * Defaults hardcoded (espelham os INSERTs do db.sql).
     *
     * @return array<string,mixed>
     */
    public function defaults(): array
    {
        return [
            'empresa_razao_social'  => 'MM Construtora',
            'empresa_cnpj'          => '',
            'empresa_logo_path'     => '',
            'sistema_nome'          => 'Leandro DEV Financeiro',
            'moeda'                 => 'BRL',
            'locale'                => 'pt_BR',
            'fuso_horario'          => 'America/Sao_Paulo',
            'session_timeout_min'   => 30,
            'tentativas_login_max'  => 5,
            'bloqueio_login_min'    => 15,
            'backup_automatico'     => false,
            'backup_manter_ultimos' => 10,
            'backup_dia_mes'        => 1,
            'schema_version'        => '1.0.0',
        ];
    }

    /**
     * Restaura os defaults (reescreve todas as chaves com valor default).
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function resetDefaults(): void
    {
        try {
            $this->pdo->beginTransaction();
            foreach ($this->defaults() as $chave => $valor) {
                $this->set($chave, $valor);
            }
            $this->pdo->commit();
            self::$cache = [];
            self::$loadedAll = false;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new RuntimeException('Falha ao restaurar defaults: ' . $e->getMessage(), 0, $e);
        }
    }

    // -----------------------------------------------------------------
    // Helpers.
    // -----------------------------------------------------------------

    /**
     * Carrega todas as configurações do DB uma única vez.
     */
    private function loadAll(): void
    {
        if (self::$loadedAll === true) {
            return;
        }
        self::$loadedAll = true;

        try {
            $stmt = $this->pdo->query('SELECT chave, valor, tipo FROM configuracoes');
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            // Banco ainda não configurado — falha silenciosa.
            return;
        }

        foreach ($rows as $row) {
            self::$cache[(string) $row['chave']] = $this->castValue((string) $row['valor'], (string) $row['tipo']);
        }
    }

    /**
     * Detecta o tipo ENUM.
     *
     * @param mixed $value Valor.
     *
     * @return string
     */
    private function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_array($value)) {
            return 'json';
        }
        return 'string';
    }

    /**
     * Converte valor bruto para o tipo declarado.
     *
     * @param string $valor Valor.
     * @param string $tipo  Tipo ENUM.
     *
     * @return mixed
     */
    private function castValue(string $valor, string $tipo): mixed
    {
        return match ($tipo) {
            'int'      => (int) $valor,
            'bool'     => in_array(strtolower($valor), ['1', 'true', 'on', 'yes', 'sim'], true),
            'json'     => json_decode($valor, true) ?? [],
            'date'     => $valor,
            'datetime' => $valor,
            default    => $valor,
        };
    }
}
