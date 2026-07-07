<?php
declare(strict_types=1);

/**
 * @file    SignerTest.php
 * @package Tests\Nfse
 * @since   2026.07.08
 * @author  Leandro DEV
 * @license Proprietary — MM Construtora
 * @brief   Testes PHPUnit para App\Nfse\Services\Signer.
 *
 * Cobre:
 *   - Assinatura XMLDSig de XML válido (verifica <Signature>, <SignatureValue>,
 *     <X509Certificate>, <DigestValue>)
 *   - Exceção ao instanciar Signer com senha inválida
 *   - Exceção ao assinar conteúdo vazio
 *   - getCertInfo() retorna array com chaves validade, cnpj, titular
 *   - isValid() retorna true para certificado recém-gerado
 *
 * Estratégia:
 *   - Gera certificado .pfx de teste via CLI openssl (rsa:2048, válido 1 dia)
 *     em setUp(); remove em tearDown()
 *   - Se openssl CLI não disponível, marca o teste como SKIPPED
 *   - Não depende de DB nem de HTTP real
 */

namespace Tests\Nfse;

use App\Nfse\Services\Signer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @coversDefaultClass \App\Nfse\Services\Signer
 */
class SignerTest extends TestCase
{
    /** @var string Caminho absoluto do .pfx temporário gerado em setUp(). */
    private string $tempPfxPath = '';

    /** @var string Senha do .pfx de teste. */
    private string $tempPassword = 'testepass';

    /**
     * Gera um certificado .pfx de teste via CLI openssl (RSA 2048, 1 dia).
     * Marca o teste como skipped se openssl CLI não estiver disponível.
     */
    protected function setUp(): void
    {
        $keyPem  = tempnam(sys_get_temp_dir(), 'key');
        $certPem = tempnam(sys_get_temp_dir(), 'cert');
        $this->tempPfxPath = tempnam(sys_get_temp_dir(), 'pfx') . '.pfx';

        // Gerar chave RSA + certificado X.509 auto-assinado.
        // CN=Teste MM Construtora / O=CNPJ: 00.000.000/0000-00 — formato esperado por getCertInfo().
        $cmd = 'openssl req -x509 -newkey rsa:2048 '
            . '-keyout ' . escapeshellarg($keyPem) . ' '
            . '-out '    . escapeshellarg($certPem) . ' '
            . '-days 1 -nodes '
            . "-subj '/CN=Teste MM Construtora/O=CNPJ: 00.000.000/0000-00' "
            . '2>/dev/null';
        exec($cmd, $output, $ret);
        if ($ret !== 0) {
            $this->markTestSkipped('openssl CLI não disponível para gerar certificado de teste');
        }

        // Empacotar chave + cert em .pfx (PKCS#12) com a senha de teste.
        $cmdPfx = 'openssl pkcs12 -export '
            . '-out ' . escapeshellarg($this->tempPfxPath) . ' '
            . '-inkey ' . escapeshellarg($keyPem) . ' '
            . '-in '    . escapeshellarg($certPem) . ' '
            . '-passout pass:' . escapeshellarg($this->tempPassword) . ' '
            . '2>/dev/null';
        exec($cmdPfx, $output2, $ret2);
        if ($ret2 !== 0) {
            $this->markTestSkipped('openssl pkcs12 não disponível');
        }

        @unlink($keyPem);
        @unlink($certPem);
    }

    /**
     * Remove o .pfx temporário.
     */
    protected function tearDown(): void
    {
        if ($this->tempPfxPath !== '' && file_exists($this->tempPfxPath)) {
            @unlink($this->tempPfxPath);
        }
    }

    /**
     * Assina XML simples e verifica que as tags XMLDSig foram inseridas.
     *
     * @covers ::signXml
     */
    public function testSignXmlReturnsValidSignature(): void
    {
        $signer = new Signer($this->tempPfxPath, $this->tempPassword);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root xmlns="http://test">'
            . '<element Id="rps1"><content>teste</content></element>'
            . '</root>';
        $signed = $signer->signXml($xml, '#rps1');

        // Asserções XMLDSig: a assinatura contém os 4 elementos essenciais.
        $this->assertStringContainsString('<Signature', $signed);
        $this->assertStringContainsString('<SignatureValue>', $signed);
        $this->assertStringContainsString('<X509Certificate>', $signed);
        $this->assertStringContainsString('<DigestValue>', $signed);

        // Assinatura deve referenciar o URI '#rps1' no <Reference>.
        $this->assertStringContainsString('URI="#rps1"', $signed);

        // Algoritmos obrigatórios: RSA-SHA256 + enveloped + C14N.
        $this->assertStringContainsString('rsa-sha256', $signed);
        $this->assertStringContainsString('enveloped-signature', $signed);
        $this->assertStringContainsString('REC-xml-c14n-20010315', $signed);
    }

    /**
     * Senha inválida deve disparar RuntimeException no construtor.
     *
     * @covers ::__construct
     */
    public function testSignXmlWithInvalidCertThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        new Signer($this->tempPfxPath, 'senha_errada');
    }

    /**
     * Conteúdo vazio deve disparar RuntimeException.
     *
     * @covers ::signXml
     */
    public function testSignXmlWithEmptyContentThrowsException(): void
    {
        $signer = new Signer($this->tempPfxPath, $this->tempPassword);
        $this->expectException(RuntimeException::class);
        $signer->signXml('', '#rps1');
    }

    /**
     * getCertInfo() deve retornar array com chaves validade, cnpj, titular.
     *
     * @covers ::getCertInfo
     */
    public function testGetCertInfoReturnsArray(): void
    {
        $signer = new Signer($this->tempPfxPath, $this->tempPassword);
        $info = $signer->getCertInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('validade', $info);
        $this->assertArrayHasKey('cnpj', $info);
        $this->assertArrayHasKey('titular', $info);

        // validade deve estar em YYYY-MM-DD.
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $info['validade']);

        // titular deve conter "Teste MM Construtora" (CN do certificado gerado).
        $this->assertStringContainsString('Teste MM Construtora', (string) $info['titular']);
    }

    /**
     * isValid() deve retornar true para certificado recém-gerado (válido 1 dia).
     *
     * @covers ::isValid
     */
    public function testIsValidReturnsTrueForFreshCert(): void
    {
        $signer = new Signer($this->tempPfxPath, $this->tempPassword);
        $this->assertTrue($signer->isValid());
    }

    /**
     * Arquivo .pfx inexistente deve disparar RuntimeException.
     *
     * @covers ::__construct
     */
    public function testConstructorThrowsWhenPfxFileMissing(): void
    {
        $this->expectException(RuntimeException::class);
        new Signer('/tmp/certificado_inexistente_' . uniqid() . '.pfx', 'qualquer');
    }

    /**
     * Assinatura com referenceUri vazio deve assinar o elemento root.
     *
     * @covers ::signXml
     */
    public function testSignXmlWithEmptyReferenceUriSignsRootElement(): void
    {
        $signer = new Signer($this->tempPfxPath, $this->tempPassword);
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<root xmlns="http://test"><content>teste</content></root>';
        $signed = $signer->signXml($xml, '');

        // <Signature> deve estar dentro do root (último filho).
        $this->assertStringContainsString('<root', $signed);
        $this->assertStringContainsString('<Signature', $signed);
        $this->assertStringContainsString('<SignatureValue>', $signed);

        // Reference URI deve ser vazia.
        $this->assertStringContainsString('URI=""', $signed);
    }
}
