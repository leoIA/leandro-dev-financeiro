<?php
declare(strict_types=1);

/**
 * @file MunicipioNfse.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model do catálogo de municípios BA habilitados para NFSe (tabela municipios_nfse).
 *
 * Fornece lookups por IBGE, nome, provedor e lista para <select>.
 * O catálogo é populado pelo installer N09 (INSERTs do db_nfse.sql) com as
 * 10 maiores cidades da Bahia.
 */

/** @file MunicipioNfse.php | @package App\Models | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Models;

use PDO;

class MunicipioNfse extends BaseModel
{
    protected string $table = 'municipios_nfse';
    protected string $modulo = 'municipios_nfse';

    /**
     * Busca município por código IBGE (7 dígitos).
     *
     * @param string $ibge Código IBGE.
     *
     * @return array<string,mixed>|null
     */
    public function byIbge(string $ibge): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM municipios_nfse WHERE codigo_ibge = ? LIMIT 1'
        );
        $stmt->execute([$ibge]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Busca município por nome (case-insensitive via LOWER()).
     *
     * @param string $nome Nome do município (ex: "Salvador", "salvador").
     *
     * @return array<string,mixed>|null
     */
    public function byNome(string $nome): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM municipios_nfse WHERE LOWER(nome) = LOWER(?) LIMIT 1'
        );
        $stmt->execute([$nome]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Lista municípios por provedor (ordenados por nome).
     *
     * @param string $provedor Um de: SALVADOR, WEBISS, BETHA, DSF, SIMPLISS, ISSNET, NAO_SUPORTADO.
     *
     * @return list<array<string,mixed>>
     */
    public function byProvedor(string $provedor): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM municipios_nfse WHERE provedor = ? ORDER BY nome ASC'
        );
        $stmt->execute([$provedor]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todos os municípios ativos (ativo=1) ordenados por nome.
     *
     * @return list<array<string,mixed>>
     */
    public function ativos(): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM municipios_nfse WHERE ativo = 1 ORDER BY nome ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista municípios no formato chave/valor para preenchimento de <select>.
     * Chave = código IBGE; valor = "Nome — PROVEDOR".
     *
     * @return array<string,string>
     */
    public function forSelect(): array
    {
        $out = [];
        foreach ($this->ativos() as $row) {
            $out[(string) $row['codigo_ibge']] = $row['nome'] . ' — ' . $row['provedor'];
        }
        return $out;
    }
}
