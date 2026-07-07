<?php
/**
 * @file Format.php
 * @package App\Helpers
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Helpers;

/**
 * Helpers de formatação pt-BR (moeda, datas, documentos).
 */
final class Format
{
    /**
     * Formata valor como moeda BRL ("R$ 1.234,56" ou "-R$ 500,00").
     *
     * @param float $value Valor.
     *
     * @return string
     */
    public static function moneyBRL(float $value): string
    {
        $formatted = number_format(abs($value), 2, ',', '.');
        $prefix = $value < 0 ? '-R$ ' : 'R$ ';
        return $prefix . $formatted;
    }

    /**
     * Formata valor como moeda BRL sempre com prefixo "R$ " (sem sinal negativo).
     *
     * @param float $value Valor.
     *
     * @return string
     */
    public static function moneyBRLPositive(float $value): string
    {
        return 'R$ ' . number_format(abs($value), 2, ',', '.');
    }

    /**
     * Converte data ISO (YYYY-MM-DD) para dd/mm/AAAA. Retorna '-' se null/vazio.
     *
     * @param string|null $iso Data ISO.
     *
     * @return string
     */
    public static function dateBR(?string $iso): string
    {
        if ($iso === null || $iso === '' || $iso === '0000-00-00') {
            return '-';
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return '-';
        }
        return date('d/m/Y', $ts);
    }

    /**
     * Converte data BR (dd/mm/AAAA) para ISO (YYYY-MM-DD).
     *
     * @param string $br Data BR.
     *
     * @return string
     */
    public static function dateISO(string $br): string
    {
        $br = trim($br);
        if ($br === '') {
            return '';
        }
        $parts = explode('/', $br);
        if (count($parts) !== 3) {
            return '';
        }
        [$d, $m, $y] = $parts;
        if (!checkdate((int) $m, (int) $d, (int) $y)) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', (int) $y, (int) $m, (int) $d);
    }

    /**
     * Converte datetime ISO para "dd/mm/AAAA HH:MM".
     *
     * @param string|null $iso Datetime ISO.
     *
     * @return string
     */
    public static function datetimeBR(?string $iso): string
    {
        if ($iso === null || $iso === '' || $iso === '0000-00-00 00:00:00') {
            return '-';
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return '-';
        }
        return date('d/m/Y H:i', $ts);
    }

    /**
     * Formata CPF como 000.000.000-00.
     *
     * @param string $cpf CPF apenas dígitos.
     *
     * @return string
     */
    public static function cpfFormat(string $cpf): string
    {
        $cpf = self::onlyDigits($cpf);
        if (strlen($cpf) !== 11) {
            return $cpf;
        }
        return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $cpf) ?? $cpf;
    }

    /**
     * Formata CNPJ como 00.000.000/0000-00.
     *
     * @param string $cnpj CNPJ apenas dígitos.
     *
     * @return string
     */
    public static function cnpjFormat(string $cnpj): string
    {
        $cnpj = self::onlyDigits($cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $cnpj) ?? $cnpj;
    }

    /**
     * Formata CEP como 00000-000.
     *
     * @param string $cep CEP apenas dígitos.
     *
     * @return string
     */
    public static function cepFormat(string $cep): string
    {
        $cep = self::onlyDigits($cep);
        if (strlen($cep) !== 8) {
            return $cep;
        }
        return preg_replace('/^(\d{5})(\d{3})$/', '$1-$2', $cep) ?? $cep;
    }

    /**
     * Formata telefone (10 ou 11 dígitos) em padrão BR.
     *
     * @param string $phone Telefone.
     *
     * @return string
     */
    public static function phoneFormat(string $phone): string
    {
        $phone = self::onlyDigits($phone);
        $len = strlen($phone);
        if ($len === 11) {
            return preg_replace('/^(\d{2})(\d{5})(\d{4})$/', '($1) $2-$3', $phone) ?? $phone;
        }
        if ($len === 10) {
            return preg_replace('/^(\d{2})(\d{4})(\d{4})$/', '($1) $2-$3', $phone) ?? $phone;
        }
        return $phone;
    }

    /**
     * Remove todos os caracteres não-dígitos.
     *
     * @param string $value Valor.
     *
     * @return string
     */
    public static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * Trunca texto ao limite informado, adicionando "…".
     *
     * @param string $text   Texto.
     * @param int    $length Tamanho máximo.
     *
     * @return string
     */
    public static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        if ($length <= 1) {
            return mb_substr($text, 0, $length);
        }
        return mb_substr($text, 0, $length - 1) . '…';
    }
}
