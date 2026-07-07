<?php
declare(strict_types=1);

/**
 * @file Certificado.php
 * @package App\Models
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 *
 * Model de certificados A1 (.pfx) para assinatura digital de NFSe.
 *
 * Regras de segurança:
 *   - Apenas UM certificado ativo por vez (o método ativo() retorna o mais recente).
 *   - O arquivo .pfx fica em storage/certificados/ (fora da web root).
 *   - A senha do .pfx é criptografada com AES-256-CBC (openssl_encrypt) antes
 *     de ser persistida na coluna senha_criptografada.
 *   - A chave de criptografia é derivada de APP_NAME + BASE_PATH + salt fixo
 *     (definidos no projeto), nunca persistida.
 *   - O hard-delete remove o arquivo físico do storage junto com o registro.
 *
 * @see App\Nfse\Services\Signer (consome o .pfx + senha em texto plano)
 * @see db_nfse.sql (schema da tabela certificados)
 */

/** @file Certificado.php | @package App\Models | @since 2026.07.07 | @author Leandro DEV | @license Proprietary — MM Construtora */

namespace App\Models;

use PDO;
use RuntimeException;
use Throwable;

class Certificado extends BaseModel
{
    protected string $table = 'certificados';
    protected string $modulo = 'certificados';

    // -----------------------------------------------------------------
    // Lookups.
    // -----------------------------------------------------------------

    /**
     * Retorna o certificado ativo (apenas 1 ativo por vez).
     * Se houver múltiplos ativos (estado inconsistente), retorna o mais recente.
     *
     * @return array<string,mixed>|null
     */
    public function ativo(): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM certificados WHERE ativo = 1 ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    /**
     * Busca certificado por CNPJ do titular (normaliza máscara em ambos os lados).
     *
     * @param string $cnpj CNPJ com ou sem máscara (XX.XXX.XXX/XXXX-XX ou 14 dígitos).
     *
     * @return array<string,mixed>|null
     */
    public function byCnpj(string $cnpj): ?array
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? $cnpj;
        // Compara apenas dígitos — usa REPLACE aninhado para normalizar o valor
        // armazenado (compatível com MySQL 5.7+ e MariaDB 10.0+).
        $sql = 'SELECT * FROM certificados
                WHERE REPLACE(REPLACE(REPLACE(REPLACE(cnpj_titular, \'.\', \'\'), \'/\', \'\'), \'-\', \'\'), \' \', \'\') = ?
                ORDER BY ativo DESC, id DESC
                LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$digits]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row !== false ? $row : null;
    }

    // -----------------------------------------------------------------
    // Criptografia de senha.
    // -----------------------------------------------------------------

    /**
     * Chave de criptografia derivada de APP_NAME + BASE_PATH + salt fixo.
     * O salt é específico do projeto (MM Construtora) para impedir uso da
     * chave mesmo em ambientes compartilhados.
     *
     * @return string Chave binária (32 bytes — sha256).
     */
    private static function encryptionKey(): string
    {
        return hash(
            'sha256',
            (defined('APP_NAME') ? APP_NAME : 'Leandro DEV')
            . (defined('BASE_PATH') ? BASE_PATH : __DIR__)
            . 'salt-mm-construtora-nfse'
        );
    }

    /**
     * Criptografa a senha do certificado com AES-256-CBC + IV aleatório.
     * Saída: base64(IV[16] + ciphertext).
     *
     * @param string $senha Senha em texto plano.
     *
     * @return string Senha criptografada (base64).
     *
     * @throws RuntimeException Se openssl_encrypt falhar.
     */
    public static function encryptSenha(string $senha): string
    {
        $key = self::encryptionKey();
        $iv  = openssl_random_pseudo_bytes(16);
        if ($iv === false) {
            throw new RuntimeException('Falha ao gerar IV para criptografia da senha.');
        }
        $encrypted = openssl_encrypt($senha, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            throw new RuntimeException('Falha ao criptografar senha do certificado.');
        }
        return base64_encode($iv . $encrypted);
    }

    /**
     * Descriptografa senha previamente criptografada com encryptSenha().
     *
     * @param string $encrypted Senha criptografada (base64).
     *
     * @return string Senha em texto plano.
     *
     * @throws RuntimeException Se a entrada for inválida ou a descriptografia falhar.
     */
    public static function decryptSenhaString(string $encrypted): string
    {
        $key  = self::encryptionKey();
        $data = base64_decode($encrypted, true);
        if ($data === false || strlen($data) < 16) {
            throw new RuntimeException('Senha criptografada inválida (payload muito curto).');
        }
        $iv         = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        $decrypted  = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new RuntimeException('Falha ao descriptografar senha do certificado.');
        }
        return $decrypted;
    }

    /**
     * Descriptografa uma senha criptografada (alias de decryptSenhaString).
     *
     * @param string $senhaCriptografada
     *
     * @return string
     */
    public function decryptSenha(string $senhaCriptografada): string
    {
        return self::decryptSenhaString($senhaCriptografada);
    }

    /**
     * Busca a senha do certificado pelo ID e a retorna descriptografada.
     *
     * @param int $id ID do certificado.
     *
     * @return string Senha em texto plano.
     *
     * @throws RuntimeException Se o certificado não for encontrado.
     */
    public function getSenha(int $id): string
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new RuntimeException("Certificado não encontrado: {$id}");
        }
        return self::decryptSenhaString((string) $row['senha_criptografada']);
    }

    // -----------------------------------------------------------------
    // Criação / atualização / expiração.
    // -----------------------------------------------------------------

    /**
     * Cria um certificado A1: valida .pfx, move para storage, criptografa senha,
     * extrai validade/titular/CNPJ via openssl_x509_parse e insere no DB.
     *
     * @param array<string,mixed> $data     Deve conter: nome, senha (texto plano).
     *                                      Opcional: cnpj_titular (sobrescreve o extraído).
     * @param string              $pfxPath  Caminho temporário do .pfx (tmp_name do upload).
     *
     * @return int ID inserido.
     *
     * @throws RuntimeException Se arquivo não existir, senha inválida,
     *                          certificado sem X.509, ou falha ao mover/inserir.
     */
    public function createWithFile(array $data, string $pfxPath): int
    {
        if (!file_exists($pfxPath)) {
            throw new RuntimeException("Arquivo .pfx não encontrado: {$pfxPath}");
        }
        $senha = (string) ($data['senha'] ?? '');
        if ($senha === '') {
            throw new RuntimeException('Senha do certificado é obrigatória.');
        }

        // 1) Lê e valida o .pfx
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
        $pemCert = $certs['cert'] ?? '';
        if ($pemCert === '') {
            throw new RuntimeException('Certificado .pfx não contém certificado X.509.');
        }

        $parsed = openssl_x509_parse($pemCert);
        if ($parsed === false) {
            throw new RuntimeException('Não foi possível parsear o certificado .pfx.');
        }

        // 2) Extrai dados do certificado.
        $validade    = date('Y-m-d', (int) ($parsed['validTo_time_t'] ?? time()));
        $titularNome = (string) ($parsed['subject']['CN'] ?? ($parsed['name'] ?? ''));
        $cnpj        = $this->extractCnpjFromParsed($parsed);

        // 3) Move arquivo para storage/certificados/
        $storageDir = $this->storageDir();
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0775, true);
        }
        $fileName = 'cert_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.pfx';
        $destPath = $storageDir . '/' . $fileName;
        if (!copy($pfxPath, $destPath)) {
            throw new RuntimeException("Falha ao mover .pfx para storage: {$destPath}");
        }

        // 4) Criptografa senha.
        $senhaCript = self::encryptSenha($senha);

        // 5) Insere no DB.
        $insertData = [
            'nome'                => (string) ($data['nome'] ?? $titularNome),
            'arquivo_path'        => $destPath,
            'senha_criptografada' => $senhaCript,
            'validade'            => $validade,
            'cnpj_titular'        => $cnpj !== '' ? $cnpj : (string) ($data['cnpj_titular'] ?? ''),
            'titular_nome'        => $titularNome,
            'ativo'               => 1,
        ];

        try {
            $id = parent::create($insertData);
            // Não loga a senha (criptografada ou não) por segurança.
            $auditData = $insertData;
            unset($auditData['senha_criptografada']);
            $this->audit('CREATE', $id, null, $auditData);
            return $id;
        } catch (Throwable $e) {
            // Remove arquivo físico se a inserção falhou — não deixa órfão.
            if (file_exists($destPath)) {
                @unlink($destPath);
            }
            throw new RuntimeException(
                'Falha ao inserir certificado: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Lê o .pfx do certificado, extrai a validade via openssl_x509_parse e
     * atualiza o campo `validade` no DB.
     *
     * @param int $id ID do certificado.
     *
     * @return void
     *
     * @throws RuntimeException Em caso de falha.
     */
    public function updateValidade(int $id): void
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new RuntimeException("Certificado não encontrado: {$id}");
        }
        $pfxPath = (string) ($row['arquivo_path'] ?? '');
        if (!file_exists($pfxPath)) {
            throw new RuntimeException("Arquivo .pfx não encontrado: {$pfxPath}");
        }

        $senha = $this->getSenha($id);

        $pfxContent = file_get_contents($pfxPath);
        if ($pfxContent === false) {
            throw new RuntimeException("Não foi possível ler o arquivo .pfx: {$pfxPath}");
        }

        $certs = [];
        if (!openssl_pkcs12_read($pfxContent, $certs, $senha)) {
            throw new RuntimeException('Senha do certificado inválida ou .pfx corrompido.');
        }
        $pemCert = $certs['cert'] ?? '';
        $parsed  = openssl_x509_parse($pemCert);
        if ($parsed === false) {
            throw new RuntimeException('Não foi possível parsear o certificado.');
        }

        $validade = date('Y-m-d', (int) ($parsed['validTo_time_t'] ?? time()));

        $antes = $row;
        $ok    = parent::update($id, ['validade' => $validade]);
        if ($ok) {
            $this->audit('UPDATE', $id, $antes, ['validade' => $validade]);
        }
    }

    /**
     * Verifica se o certificado está expirado (validade < hoje).
     *
     * @param int $id ID do certificado.
     *
     * @return bool True se expirado (ou não encontrado).
     */
    public function isExpired(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return true;
        }
        $validade = (string) ($row['validade'] ?? '1970-01-01');
        $ts       = strtotime($validade);
        if ($ts === false) {
            return true;
        }
        return $ts < strtotime(date('Y-m-d'));
    }

    // -----------------------------------------------------------------
    // Soft-delete / hard-delete.
    // -----------------------------------------------------------------

    /**
     * Desativa um certificado (soft delete: ativo=0).
     *
     * @param int $id ID do certificado.
     *
     * @return void
     */
    public function desativar(int $id): void
    {
        $antes = $this->find($id);
        $ok    = parent::update($id, ['ativo' => 0]);
        if ($ok) {
            $this->audit('DELETE', $id, $antes, ['ativo' => 0]);
        }
    }

    /**
     * Hard-delete: remove registro do DB e apaga arquivo .pfx do storage.
     *
     * @param int $id ID do certificado.
     *
     * @return bool False se o registro não existia; True se removido com sucesso.
     */
    public function delete(int $id): bool
    {
        $row = $this->find($id);
        if ($row === null) {
            return false;
        }
        $ok = parent::delete($id);
        if ($ok) {
            $this->audit('DELETE', $id, $row, null);
            $pfxPath = (string) ($row['arquivo_path'] ?? '');
            if ($pfxPath !== '' && file_exists($pfxPath)) {
                @unlink($pfxPath);
            }
        }
        return $ok;
    }

    // -----------------------------------------------------------------
    // Helpers internos.
    // -----------------------------------------------------------------

    /**
     * Caminho absoluto do diretório de storage de certificados.
     * Localização: <project_root>/storage/certificados/
     *
     * @return string
     */
    private function storageDir(): string
    {
        // app/Models/X.php → dirname(__DIR__, 2) = project root.
        return dirname(__DIR__, 2) . '/storage/certificados';
    }

    /**
     * Extrai CNPJ formatado (XX.XXX.XXX/XXXX-XX) do certificado X.509 parseado.
     * Procura em: subject CN/O, depois OID ICP-Brasil 2.16.76.1.3.3.
     *
     * @param array<string,mixed> $parsed Resultado de openssl_x509_parse.
     *
     * @return string CNPJ formatado ou vazio se não encontrado.
     */
    private function extractCnpjFromParsed(array $parsed): string
    {
        $subject = (string) ($parsed['name'] ?? '');

        if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', $subject, $m)) {
            return $m[1];
        }
        if (isset($parsed['subject']['O'])) {
            if (preg_match('/(\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})/', (string) $parsed['subject']['O'], $m)) {
                return $m[1];
            }
        }
        // OID 2.16.76.1.3.3 (CNPJ na AC-Raiz brasileira) — retorna dígitos puros.
        if (isset($parsed['extensions']['2.16.76.1.3.3'])) {
            $raw     = (string) $parsed['extensions']['2.16.76.1.3.3'];
            $digits  = preg_replace('/\D/', '', $raw);
            if ($digits !== null && strlen($digits) >= 14) {
                $cnpjDigits = substr($digits, 0, 14);
                return sprintf(
                    '%s.%s.%s/%s-%s',
                    substr($cnpjDigits, 0, 2),
                    substr($cnpjDigits, 2, 3),
                    substr($cnpjDigits, 5, 3),
                    substr($cnpjDigits, 8, 4),
                    substr($cnpjDigits, 12, 2)
                );
            }
        }
        return '';
    }
}
