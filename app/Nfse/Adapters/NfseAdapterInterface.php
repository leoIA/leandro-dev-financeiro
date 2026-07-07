<?php
declare(strict_types=1);

/**
 * @file    NfseAdapterInterface.php
 * @package App\Nfse\Adapters
 * @since   2026.07.07
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Contrato comum a todos os adapters de provedor NFSe BA.
 *
 * Define os 4 métodos que cada adapter de prefeitura deve implementar:
 *   - emitir(array $nfse): array      → POST SOAP GerarNfse e retorna resultado
 *   - consultar(string $protocolo): array
 *   - cancelar(string $protocolo, string $motivo): array
 *   - gerarDanfse(array $nfse): string → HTML print-friendly
 *
 * O construtor recebe o registro do certificado (com `arquivo_path` e a senha
 * já descriptografada em `senha`) e o ambiente ('HOMOLOGACAO' ou 'PRODUCAO').
 *
 * Implementações concretas:
 *   - WebissAdapter     → Feira de Santana, Camaçari, Vitória da Conquista,
 *                          Juazeiro, Lauro de Freitas
 *   - BethaAdapter      → Itabuna, Ilhéus, Jequié
 *   - DsfAdapter        → Teixeira de Freitas
 *   - SalvadorAdapter   → Salvador
 *
 * @see App\Nfse\Data\MunicipiosCatalog
 * @see App\Nfse\Services\{Signer, XmlBuilder}
 */

namespace App\Nfse\Adapters;

interface NfseAdapterInterface
{
    /**
     * Recebe o certificado A1 (.pfx) e o ambiente de operação.
     *
     * @param array<string,mixed> $certificado Registro da tabela `certificados`:
     *                                         deve conter `arquivo_path` (caminho
     *                                         absoluto do .pfx) e `senha` (senha
     *                                         em texto plano, já descriptografada).
     * @param string              $ambiente    Um de: 'HOMOLOGACAO' ou 'PRODUCAO'.
     */
    public function __construct(array $certificado, string $ambiente);

    /**
     * Emite uma NFSe na prefeitura.
     *
     * @param  array<string,mixed> $nfse Dados da NFSe (todos campos da tabela nfse).
     * @return array{
     *   sucesso: bool,
     *   numero: ?string,
     *   codigo_verificacao: ?string,
     *   protocolo: ?string,
     *   erro: ?string,
     *   xml_envio: ?string,
     *   xml_retorno: ?string
     * }
     */
    public function emitir(array $nfse): array;

    /**
     * Consulta a situação de uma NFSe pelo protocolo retornado na emissão.
     *
     * @param  string $protocolo Número do protocolo retornado pela prefeitura.
     * @return array{sucesso: bool, status: ?string, erro: ?string, xml_retorno: ?string}
     */
    public function consultar(string $protocolo): array;

    /**
     * Cancela uma NFSe previamente autorizada.
     *
     * @param  string $protocolo Número do protocolo da NFSe a cancelar.
     * @param  string $motivo    Justificativa do cancelamento (>= 15 caracteres).
     * @return array{sucesso: bool, erro: ?string, xml_retorno: ?string}
     */
    public function cancelar(string $protocolo, string $motivo): array;

    /**
     * Gera o HTML do DANFSE (Documento Auxiliar da NFSe) para impressão.
     *
     * @param  array<string,mixed> $nfse Dados completos da NFSe autorizada.
     * @return string                    HTML print-friendly (Bootstrap 5 inline).
     */
    public function gerarDanfse(array $nfse): string;
}
