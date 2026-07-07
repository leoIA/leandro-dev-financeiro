<?php
/**
 * @file Sanitizer.php
 * @package App\Helpers
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Helpers;

/**
 * Sanitização de input pt-BR.
 */
final class Sanitizer
{
    /**
     * Trim + htmlspecialchars.
     *
     * @param string|null $value Valor.
     *
     * @return string
     */
    public static function string(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Converte para int.
     *
     * @param string|null $value Valor.
     *
     * @return int
     */
    public static function int(?string $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Converte string pt-BR ("1.234,56" ou "1234.56") para float.
     *
     * @param string|null $value Valor.
     *
     * @return float
     */
    public static function float(?string $value): float
    {
        return self::decimal($value);
    }

    /**
     * Converte string decimal pt-BR ("1.234,56") para float (1234.56).
     *
     * @param string|null $value Valor.
     *
     * @return float
     */
    public static function decimal(?string $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        // Remove espaços.
        $value = trim($value);

        // Detecta formato. Se tem vírgula, assume pt-BR (ponto = milhar, vírgula = decimal).
        if (str_contains($value, ',')) {
            // Remove pontos de milhar, troca vírgula decimal por ponto.
            $normalized = str_replace('.', '', $value);
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = $value;
        }

        // Remove tudo que não for número, sinal ou ponto.
        $normalized = preg_replace('/[^0-9\.\-]/', '', $normalized) ?? '';
        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0.0;
        }

        return (float) $normalized;
    }

    /**
     * Converte data BR (dd/mm/AAAA) para ISO (YYYY-MM-DD). Retorna null se inválida.
     *
     * @param string|null $value Data BR.
     *
     * @return string|null
     */
    public static function date(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            if (!checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
                return null;
            }
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }
        // Já ISO?
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value, $m)) {
            if (!checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }
            return $value;
        }
        return null;
    }

    /**
     * Sanitiza e-mail (lowercase + trim). Retorna null se inválido.
     *
     * @param string|null $value E-mail.
     *
     * @return string|null
     */
    public static function email(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = strtolower(trim($value));
        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        return $value;
    }

    /**
     * Apenas dígitos do CPF.
     *
     * @param string|null $value CPF.
     *
     * @return string
     */
    public static function cpf(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Apenas dígitos do CNPJ.
     *
     * @param string|null $value CNPJ.
     *
     * @return string
     */
    public static function cnpj(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Apenas dígitos do CEP (8).
     *
     * @param string|null $value CEP.
     *
     * @return string
     */
    public static function cep(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return preg_replace('/\D/', '', $value) ?? '';
    }
}
