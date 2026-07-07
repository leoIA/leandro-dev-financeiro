<?php
declare(strict_types=1);

/** @file Signer.php | @package App\Nfse\Services | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Nfse\Services;

use RuntimeException;
use Throwable;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Assinatura digital XML (XMLDSig) para NFSe usando certificado A1 (.pfx).
 *
 * Responsabilidade:
 *   - Carregar o certificado .pfx (PKCS#12) e extrair chave privada + certificado PEM
 *   - Assinar XML conforme padrão XMLDSig com DigestMethod SHA-1 e
 *     SignatureMethod RSA-SHA256 (algoritmo exigido pelas prefeituras BA)
 *   - Inserir a tag <Signature> dentro do elemento referenciado por $referenceUri
 *     (ex: '#rps1' → elemento com Id="rps1") ou no root quando referenceUri vazio
 *   - Expor dados do certificado (validade, CNPJ, titular) para uso nos formulários
 *
 * Fluxo do signXml():
 *   1. Carregar XML em DOMDocument (preserveWhiteSpace=false, formatOutput=false)
 *   2. Encontrar elemento referenciado por $referenceUri
 *   3. Canonicalizar o elemento (C14N exclusive)
 *   4. Calcular digest SHA-1 do canonicalizado, base64 encode
 *   5. Construir <SignedInfo> com:
 *        - CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"
 *        - SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"
 *        - Reference URI="$referenceUri" com Transform enveloped-signature,
 *          DigestMethod SHA-1 e DigestValue calculado
 *   6. Canonicalizar <SignedInfo>, assinar com openssl_sign SHA-256, base64 → <SignatureValue>
 *   7. Construir <KeyInfo><X509Data><X509Certificate>{cert base64 sem header/footer}
 *   8. Inserir <Signature> dentro do elemento referenciado (appendChild)
 *   9. Retornar XML como string ($dom->saveXML())
 *
 * Segurança:
 *   - A chave privada PEM reside apenas em memória durante o ciclo de vida do objeto
 *   - Nenhum arquivo temporário é gravado em disco
 *   - A senha do .pfx não é armazenada
 *
 * @see https://www.w3.org/TR/xmldsig-core/ XMLDSig specification
 * @used-by App\Nfse\Adapters\*Adapter (chamam signXml antes do envio SOAP)
 */
class Signer
{
    /** @var string Canonicalização C14N inclusiva (URI declarada em <CanonicalizationMethod>). */
    private const C14N_ALGORITHM = 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315';

    /** @var string Assinatura RSA-SHA256 (URI declarada em <SignatureMethod>). */
    private const SIGNATURE_METHOD_ALGORITHM = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';

    /** @var string Digest SHA-1 (URI declarada em <DigestMethod>). */
    private const DIGEST_METHOD_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#sha1';

    /** @var string Transform enveloped-signature (URI declarada em <Transform>). */
    private const ENVELOPED_TRANSFORM_ALGORITHM = 'http://www.w3.org/2000/09/xmldsig#enveloped-signature';

    /** @var string Namespace XMLDSig. */
    private const XMLDSIG_NS = 'http://www.w3.org/2000/09/xmldsig#';

    /** @var string Chave privada PEM (em memória apenas). */
    private string $privateKeyPem;

    /** @var string Certificado X.509 PEM (em memória apenas). */
    private string $certificatePem;

    /** @var string Certificado em base64 (sem marcadores PEM BEGIN/END) — usado em <X509Certificate>. */
    private string $certificateBase64;

    /**
     * Carrega o certificado .pfx e extrai chave privada + certificado.
     *
     * @param  string $pfxPath Caminho absoluto para o arquivo .pfx.
     * @param  string $senha   Senha do certificado (em texto plano, recebida do formulário).
     * @throws RuntimeException Se arquivo não existir, não puder ser lido,
     *                          senha inválida ou .pfx não contiver chave+cert.
     */
    public function __construct(string $pfxPath, string $senha)
    {
        if (!file_exists($pfxPath)) {
            throw new RuntimeException("Arquivo .pfx não encontrado: {$pfxPath}");
        }
        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new RuntimeException("Não foi possível ler o arquivo .pfx: {$pfxPath}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            throw new RuntimeException(
                'Senha do certificado inválida ou arquivo .pfx corrompido: '
                . (openssl_error_string() ?: 'erro desconhecido')
            );
        }

        $this->privateKeyPem  = $certs['pkey'] ?? '';
        $this->certificatePem = $certs['cert'] ?? '';
        if (empty($this->privateKeyPem) || empty($this->certificatePem)) {
            throw new RuntimeException('Certificado .pfx não contém chave privada ou certificado.');
        }
        $this->certificateBase64 = $this->extractBase64FromPem($this->certificatePem);
    }

    /**
     * Assina o XML inserindo a tag <Signature> conforme XMLDSig.
     *
     * @param  string $xml           XML original (não assinado).
     * @param  string $referenceUri  URI do elemento a ser assinado (ex: '#rps1' ou '' para root).
     * @return string                XML assinado (preserva declaração XML e encoding).
     * @throws RuntimeException      Se XML vazio, inválido, ou elemento referenciado não existir.
     */
    public function signXml(string $xml, string $referenceUri = ''): string
    {
        if (empty(trim($xml))) {
            throw new RuntimeException('XML vazio não pode ser assinado.');
        }

        $previous = libxml_use_internal_errors(true);
        try {
            // 1) Carrega XML em DOMDocument.
            $doc = new DOMDocument('1.0', 'UTF-8');
            $doc->preserveWhiteSpace = false;
            $doc->formatOutput = false;
            $loaded = $doc->loadXML($xml, LIBXML_NOBLANKS);
            if ($loaded === false) {
                $errors = [];
                foreach (libxml_get_errors() as $e) {
                    $errors[] = trim($e->message);
                }
                libxml_clear_errors();
                throw new RuntimeException(
                    'XML inválido não pode ser assinado: ' . implode('; ', $errors)
                );
            }

            // 2) Localiza o elemento referenciado por $referenceUri.
            $refElement = $this->findReferenceElement($doc, $referenceUri);

            // 3) Canonicaliza o elemento (C14N exclusive sem comentários).
            $canonicalized = $refElement->C14N(true, false);
            if ($canonicalized === false) {
                throw new RuntimeException('Falha na canonicalização C14N do elemento referenciado.');
            }

            // 4) Calcula digest SHA-1 do canonicalizado, base64 encode.
            $digest = sha1($canonicalized, true);
            $digestB64 = base64_encode($digest);
            if ($digestB64 === false) {
                throw new RuntimeException('Falha ao codificar digest em base64.');
            }

            // 5) Monta o elemento <SignedInfo> com algoritmos definidos pelo spec.
            $signedInfo = $this->buildSignedInfo($doc, $referenceUri, $digestB64);

            // 6) Canonicaliza <SignedInfo> e assina com openssl_sign SHA-256.
            $signedInfoCanon = $signedInfo->C14N(true, false);
            if ($signedInfoCanon === false) {
                throw new RuntimeException('Falha na canonicalização C14N do <SignedInfo>.');
            }
            $signature = '';
            $ok = openssl_sign(
                $signedInfoCanon,
                $signature,
                $this->privateKeyPem,
                OPENSSL_ALGO_SHA256
            );
            if ($ok !== true || $signature === '') {
                throw new RuntimeException(
                    'Falha ao assinar <SignedInfo> com RSA-SHA256: '
                    . (openssl_error_string() ?: 'erro desconhecido')
                );
            }
            $signatureB64 = base64_encode($signature);
            if ($signatureB64 === false) {
                throw new RuntimeException('Falha ao codificar assinatura em base64.');
            }

            // 7) Monta <KeyInfo><X509Data><X509Certificate>.
            // 8) Monta o elemento <Signature> completo e insere no documento.
            $signatureEl = $this->buildSignatureElement(
                $doc,
                $signedInfo,
                $signatureB64,
                $this->certificateBase64
            );

            // Insere <Signature> como último filho do elemento referenciado.
            $refElement->appendChild($signatureEl);

            // 9) Retorna XML como string.
            $result = $doc->saveXML();
            if ($result === false) {
                throw new RuntimeException('Falha ao serializar XML assinado.');
            }
            return $result;
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new RuntimeException('Erro inesperado durante assinatura: ' . $e->getMessage(), 0, $e);
        } finally {
            libxml_use_internal_errors($previous);
            libxml_clear_errors();
        }
    }

    /**
     * Extrai dados do certificado para uso em formulários e validação.
     *
     * @return array{validade:string, cnpj:string, titular:string}
     *         - validade: data no formato YYYY-MM-DD
     *         - cnpj: CNPJ no formato XX.XXX.XXX/XXXX-XX (string vazia se não encontrado)
     *         - titular: nome do titular (CN do subject)
     * @throws RuntimeException Se não for possível parsear o certificado.
     */
    public function getCertInfo(): array
    {
        $parsed = openssl_x509_parse($this->certificatePem);
        if ($parsed === false) {
            throw new RuntimeException('Não foi possível parsear o certificado.');
        }

        // CNPJ pode estar no subject (name), no O (razão social) ou em OID ICP-Brasil 2.16.76.1.3.3.
        $cnpj = '';
        $subject = $parsed['name'] ?? '';

        if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', $subject, $m)) {
            $cnpj = $m[1];
        }
        if ($cnpj === '' && isset($parsed['subject']['O'])) {
            if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', (string) $parsed['subject']['O'], $m)) {
                $cnpj = $m[1];
            }
        }
        // OID 2.16.76.1.3.3 (CNPJ na AC-Raiz brasileira) — retorna dígitos puros.
        if ($cnpj === '' && isset($parsed['extensions']['2.16.76.1.3.3'])) {
            $raw = (string) $parsed['extensions']['2.16.76.1.3.3'];
            $digits = preg_replace('/\D/', '', $raw);
            if ($digits !== null && strlen($digits) >= 14) {
                $cnpjDigits = substr($digits, 0, 14);
                $cnpj = sprintf(
                    '%s.%s.%s/%s-%s',
                    substr($cnpjDigits, 0, 2),
                    substr($cnpjDigits, 2, 3),
                    substr($cnpjDigits, 5, 3),
                    substr($cnpjDigits, 8, 4),
                    substr($cnpjDigits, 12, 2)
                );
            }
        }

        $titular = $parsed['subject']['CN'] ?? $subject;

        return [
            'validade' => date('Y-m-d', $parsed['validTo_time_t']),
            'cnpj'     => $cnpj,
            'titular'  => is_array($titular) ? implode(' ', $titular) : (string) $titular,
        ];
    }

    /**
     * Verifica se o certificado está dentro da validade (não expirado).
     *
     * @return bool True se válido (validade > agora), false caso contrário.
     */
    public function isValid(): bool
    {
        $parsed = openssl_x509_parse($this->certificatePem);
        if ($parsed === false) {
            return false;
        }
        return $parsed['validTo_time_t'] > time();
    }

    /**
     * Localiza o elemento referenciado por $referenceUri no documento.
     *
     * @param  DOMDocument $doc          Documento XML.
     * @param  string      $referenceUri URI no formato '#id' (aponta para elemento com Id=$id).
     *                                   Se vazio, retorna o elemento root.
     * @return DOMElement
     * @throws RuntimeException Se o elemento não for encontrado.
     */
    private function findReferenceElement(DOMDocument $doc, string $referenceUri): DOMElement
    {
        if ($referenceUri === '') {
            $root = $doc->documentElement;
            if ($root === null) {
                throw new RuntimeException('Documento XML não possui elemento root.');
            }
            return $root;
        }

        // Remove o '#' inicial e normaliza.
        $idValue = str_starts_with($referenceUri, '#')
            ? substr($referenceUri, 1)
            : $referenceUri;
        if ($idValue === '') {
            $root = $doc->documentElement;
            if ($root === null) {
                throw new RuntimeException('Documento XML não possui elemento root.');
            }
            return $root;
        }

        // Busca por atributo Id/id/ID igual a $idValue (ABRASF usa Id="rps1").
        $xpath = new DOMXPath($doc);
        foreach (['Id', 'id', 'ID'] as $attrName) {
            $nodes = @$xpath->query("//*[@{$attrName}='{$idValue}']");
            if ($nodes !== false && $nodes->length > 0) {
                $el = $nodes->item(0);
                if ($el instanceof DOMElement) {
                    return $el;
                }
            }
        }

        throw new RuntimeException(
            "Elemento referenciado por URI '{$referenceUri}' não encontrado no XML."
        );
    }

    /**
     * Monta o elemento <SignedInfo> conforme XMLDSig.
     *
     * @param  DOMDocument $doc
     * @param  string      $referenceUri URI do elemento referenciado.
     * @param  string      $digestB64    Digest base64 do elemento referenciado.
     * @return DOMElement
     */
    private function buildSignedInfo(DOMDocument $doc, string $referenceUri, string $digestB64): DOMElement
    {
        $signedInfo = $doc->createElementNS(self::XMLDSIG_NS, 'SignedInfo');

        $canonMethod = $doc->createElement('CanonicalizationMethod');
        $canonMethod->setAttribute('Algorithm', self::C14N_ALGORITHM);
        $signedInfo->appendChild($canonMethod);

        $sigMethod = $doc->createElement('SignatureMethod');
        $sigMethod->setAttribute('Algorithm', self::SIGNATURE_METHOD_ALGORITHM);
        $signedInfo->appendChild($sigMethod);

        $reference = $doc->createElement('Reference');
        $reference->setAttribute('URI', $referenceUri);

        $transforms = $doc->createElement('Transforms');
        $transform = $doc->createElement('Transform');
        $transform->setAttribute('Algorithm', self::ENVELOPED_TRANSFORM_ALGORITHM);
        $transforms->appendChild($transform);
        $reference->appendChild($transforms);

        $digestMethod = $doc->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', self::DIGEST_METHOD_ALGORITHM);
        $reference->appendChild($digestMethod);

        $digestValue = $doc->createElement('DigestValue', $digestB64);
        $reference->appendChild($digestValue);

        $signedInfo->appendChild($reference);

        return $signedInfo;
    }

    /**
     * Monta o elemento <Signature> completo (SignedInfo + SignatureValue + KeyInfo).
     *
     * @param  DOMDocument $doc
     * @param  DOMElement  $signedInfo
     * @param  string      $signatureB64
     * @param  string      $certBase64
     * @return DOMElement
     */
    private function buildSignatureElement(
        DOMDocument $doc,
        DOMElement $signedInfo,
        string $signatureB64,
        string $certBase64
    ): DOMElement {
        $signature = $doc->createElementNS(self::XMLDSIG_NS, 'Signature');
        $signature->appendChild($signedInfo);

        $sigValue = $doc->createElement('SignatureValue', $signatureB64);
        $signature->appendChild($sigValue);

        $keyInfo = $doc->createElement('KeyInfo');
        $x509Data = $doc->createElement('X509Data');
        $x509Cert = $doc->createElement('X509Certificate', $certBase64);
        $x509Data->appendChild($x509Cert);
        $keyInfo->appendChild($x509Data);
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * Extrai o conteúdo base64 de um certificado PEM, removendo os marcadores
     * BEGIN/END CERTIFICATE e quebras de linha.
     *
     * @param  string $pem Certificado PEM.
     * @return string      Conteúdo base64 puro.
     */
    private function extractBase64FromPem(string $pem): string
    {
        $cleaned = preg_replace(
            '/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/',
            '',
            $pem
        );
        return $cleaned ?? '';
    }
}
