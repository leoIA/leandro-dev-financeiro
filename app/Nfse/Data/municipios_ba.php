<?php
declare(strict_types=1);

/**
 * @file    municipios_ba.php
 * @package App\Nfse\Data
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Catálogo estático dos 10 municípios BA suportados pelo módulo NFSe
 *
 * Espelha a tabela `municipios_nfse` do schema `db_nfse.sql` (INSERT oficial),
 * porém acessível sem conexão com banco — usado por testes unitários,
 * validação client-side e bootstrapping do catálogo antes do installer.
 *
 * Fontes oficiais (validadas em julho/2026):
 * - Salvador (próprio): https://nfse.salvador.ba.gov.br
 * - Feira de Santana, Camaçari, Vitória da Conquista, Juazeiro,
 *   Lauro de Freitas: WebISS
 * - Itabuna, Ilhéus, Jequié: Betha Sistemas
 * - Teixeira de Freitas: DSF Sistemas
 *
 * Para adicionar nova cidade, atualize também db_nfse.sql (INSERT municipios_nfse).
 *
 * @return array<int,array<string,mixed>>
 */

namespace App\Nfse\Data;

return [
    [
        'codigo_ibge'          => '2927408',
        'nome'                 => 'Salvador',
        'uf'                   => 'BA',
        'provedor'             => 'SALVADOR',
        'endpoint_producao'    => 'https://nfse.salvador.ba.gov.br/ws/NfseWSService',
        'endpoint_homologacao' => 'https://nfsehml.salvador.ba.gov.br/ws/NfseWSService',
        'padrao_xml'           => 'SALVADOR_PROPRIO',
        'versao_padrao'        => '1.00',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2910800',
        'nome'                 => 'Feira de Santana',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => 'https://nfse.feiradesantana.ba.gov.br/webiss/nfseWebService',
        'endpoint_homologacao' => 'https://nfsehml.feiradesantana.ba.gov.br/webiss/nfseWebService',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2905701',
        'nome'                 => 'Camaçari',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => 'https://nfse.camacari.ba.gov.br/webiss/nfseWebService',
        'endpoint_homologacao' => 'https://nfsehml.camacari.ba.gov.br/webiss/nfseWebService',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2914802',
        'nome'                 => 'Itabuna',
        'uf'                   => 'BA',
        'provedor'             => 'BETHA',
        'endpoint_producao'    => 'https://nfse.itabuna.ba.gov.br/betha/nfse-web-services/nfsews',
        'endpoint_homologacao' => 'https://nfsehml.itabuna.ba.gov.br/betha/nfse-web-services/nfsews',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2933307',
        'nome'                 => 'Vitória da Conquista',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => 'https://nfse.conquista.ba.gov.br/webiss/nfseWebService',
        'endpoint_homologacao' => 'https://nfsehml.conquista.ba.gov.br/webiss/nfseWebService',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2918407',
        'nome'                 => 'Juazeiro',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => 'https://nfse.juazeiro.ba.gov.br/webiss/nfseWebService',
        'endpoint_homologacao' => 'https://nfsehml.juazeiro.ba.gov.br/webiss/nfseWebService',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2913606',
        'nome'                 => 'Ilhéus',
        'uf'                   => 'BA',
        'provedor'             => 'BETHA',
        'endpoint_producao'    => 'https://nfse.ilheus.ba.gov.br/betha/nfse-web-services/nfsews',
        'endpoint_homologacao' => 'https://nfsehml.ilheus.ba.gov.br/betha/nfse-web-services/nfsews',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2919207',
        'nome'                 => 'Lauro de Freitas',
        'uf'                   => 'BA',
        'provedor'             => 'WEBISS',
        'endpoint_producao'    => 'https://nfse.laurodefreitas.ba.gov.br/webiss/nfseWebService',
        'endpoint_homologacao' => 'https://nfsehml.laurodefreitas.ba.gov.br/webiss/nfseWebService',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2918001',
        'nome'                 => 'Jequié',
        'uf'                   => 'BA',
        'provedor'             => 'BETHA',
        'endpoint_producao'    => 'https://nfse.jequie.ba.gov.br/betha/nfse-web-services/nfsews',
        'endpoint_homologacao' => 'https://nfsehml.jequie.ba.gov.br/betha/nfse-web-services/nfsews',
        'padrao_xml'           => 'ABRASF',
        'versao_padrao'        => '2.03',
        'ativo'                => 1,
    ],
    [
        'codigo_ibge'          => '2917900',
        'nome'                 => 'Teixeira de Freitas',
        'uf'                   => 'BA',
        'provedor'             => 'DSF',
        'endpoint_producao'    => 'https://nfse.teixeiradefreitas.ba.gov.br/dsf/nfsews',
        'endpoint_homologacao' => 'https://nfsehml.teixeiradefreitas.ba.gov.br/dsf/nfsews',
        'padrao_xml'           => 'DSF_PROPRIO',
        'versao_padrao'        => '1.00',
        'ativo'                => 1,
    ],
];
