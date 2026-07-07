<?php
declare(strict_types=1);

/**
 * @file    MunicipiosCatalog.php
 * @package App\Nfse\Data
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Catálogo estático dos 10 municípios BA — fachada de acesso ao
 *          data file `municipios_ba.php`.
 *
 * Provê acesso tipado ao catálogo estático (sem dependência de DB) para uso
 * em adapters, validação client-side, testes unitários e bootstrapping do
 * installer. O cache interno evita releitura do arquivo a cada chamada.
 *
 * Provedores suportados (ativos): SALVADOR, WEBISS, BETHA, DSF.
 * Provedores marcados como NÃO SUPORTADOS: SIMPLISS, ISSNET, NAO_SUPORTADO.
 *
 * @see municipios_ba.php
 * @see db_nfse.sql (INSERT municipios_nfse)
 */

namespace App\Nfse\Data;

class MunicipiosCatalog
{
    /**
     * Cache interno do catálogo (lido uma única vez por execução).
     *
     * @var array<int,array<string,mixed>>|null
     */
    private static ?array $cache = null;

    /**
     * Retorna todos os 10 municípios BA catalogados.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = require __DIR__ . '/municipios_ba.php';
        }
        return self::$cache;
    }

    /**
     * Busca município por código IBGE (7 dígitos).
     *
     * @param  string $ibge Código IBGE com 7 dígitos.
     * @return array<string,mixed>|null Dados do município ou null se não encontrado.
     */
    public static function byIbge(string $ibge): ?array
    {
        foreach (self::all() as $m) {
            if ($m['codigo_ibge'] === $ibge) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Busca município por nome (case-insensitive, com normalização via mb_strtolower).
     *
     * @param  string $nome Nome do município (ex: "Salvador", "salvador", "SALVADOR").
     * @return array<string,mixed>|null
     */
    public static function byNome(string $nome): ?array
    {
        $nome = mb_strtolower(trim($nome));
        foreach (self::all() as $m) {
            if (mb_strtolower($m['nome']) === $nome) {
                return $m;
            }
        }
        return null;
    }

    /**
     * Lista todos os municípios cujo provedor corresponde ao informado.
     *
     * @param  string $provedor Um de: SALVADOR, WEBISS, BETHA, DSF, SIMPLISS, ISSNET, NAO_SUPORTADO.
     * @return array<int,array<string,mixed>>
     */
    public static function byProvedor(string $provedor): array
    {
        return array_values(array_filter(
            self::all(),
            static fn ($m): bool => $m['provedor'] === $provedor
        ));
    }

    /**
     * Indica se o município identificado pelo IBGE está efetivamente suportado
     * (ou seja, está no catálogo E o provedor não é um stub não-implementado).
     *
     * @param  string $ibge Código IBGE.
     * @return bool
     */
    public static function isSupported(string $ibge): bool
    {
        $m = self::byIbge($ibge);
        if ($m === null) {
            return false;
        }
        return !in_array(
            $m['provedor'],
            ['SIMPLISS', 'ISSNET', 'NAO_SUPORTADO'],
            true
        );
    }

    /**
     * Retorna o endpoint SOAP do município conforme ambiente solicitado.
     *
     * @param  string $ibge     Código IBGE.
     * @param  string $ambiente Um de: 'PRODUCAO' ou 'HOMOLOGACAO'.
     * @return string|null      Endpoint ou null se o município não existir.
     */
    public static function getEndpoint(string $ibge, string $ambiente): ?string
    {
        $m = self::byIbge($ibge);
        if ($m === null) {
            return null;
        }
        if ($ambiente === 'PRODUCAO') {
            return $m['endpoint_producao'];
        }
        return $m['endpoint_homologacao'] ?? $m['endpoint_producao'];
    }

    /**
     * Lista municípios no formato chave/valor para preenchimento de <select>.
     * Chave = código IBGE; valor = "Nome — PROVEDOR".
     *
     * @return array<string,string>
     */
    public static function forSelect(): array
    {
        $out = [];
        foreach (self::all() as $m) {
            $out[$m['codigo_ibge']] = $m['nome'] . ' — ' . $m['provedor'];
        }
        return $out;
    }

    /**
     * Reseta o cache interno — útil em testes que precisam simular
     * recarregamento do data file ou invalidar estado entre casos.
     *
     * @return void
     */
    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
