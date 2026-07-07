<?php
/**
 * @file Validator.php
 * @package App\Core
 * @since 2026.07.07
 * @author Leandro DEV
 * @license Proprietary — MM Construtora
 */

declare(strict_types=1);

namespace App\Core;

/**
 * Validador declarativo para formulários.
 *
 * Uso:
 *   $v = new Validator($_POST);
 *   $v->rule('nome', 'required')->rule('nome', 'min', 3);
 *   $v->rule('email', 'required')->rule('email', 'email');
 *   $v->rule('cpf', 'cpf');
 *   if (!$v->validate()) { ... $v->errors(); }
 */
class Validator
{
    /** @var array<string,mixed> */
    private array $data;

    /** @var array<int,array{field:string,rule:string,param:mixed}> */
    private array $rules = [];

    /** @var array<string,list<string>> */
    private array $errors = [];

    /**
     * @param array<string,mixed> $data Dados a validar (ex.: $_POST).
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Adiciona uma regra a um campo.
     *
     * @param string $field Nome do campo.
     * @param string $rule  Regra: required, email, cpf, cnpj, min, max, decimal, date, unique.
     * @param mixed  $param Parâmetro extra (ex.: tamanho para min/max).
     *
     * @return self
     */
    public function rule(string $field, string $rule, mixed $param = null): self
    {
        $this->rules[] = ['field' => $field, 'rule' => $rule, 'param' => $param];
        return $this;
    }

    /**
     * Executa todas as regras acumuladas.
     *
     * @return bool
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $entry) {
            $field = $entry['field'];
            $rule = $entry['rule'];
            $param = $entry['param'];

            $value = $this->data[$field] ?? null;

            $ok = match ($rule) {
                'required' => $this->validateRequired($value),
                'email'    => $this->validateEmail($value),
                'cpf'      => $this->validateCpf($value),
                'cnpj'     => $this->validateCnpj($value),
                'min'      => $this->validateMin($value, (int) $param),
                'max'      => $this->validateMax($value, (int) $param),
                'decimal'  => $this->validateDecimal($value),
                'date'     => $this->validateDate($value),
                'unique'   => $this->validateUnique($field, $value, $param),
                default    => true,
            };

            if ($ok === false) {
                $this->addError($field, $this->messageFor($rule, $field, $param));
            }
        }

        return count($this->errors) === 0;
    }

    /**
     * Retorna erros por campo.
     *
     * @return array<string,list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // ---------- Static helpers ----------

    /**
     * Valida CPF (dígitos verificadores).
     *
     * @param string $cpf CPF apenas dígitos ou formatado.
     *
     * @return bool
     */
    public static function cpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf) ?? '';
        if (strlen($cpf) !== 11) {
            return false;
        }
        if (preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $d = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $d) {
                return false;
            }
        }
        return true;
    }

    /**
     * Valida CNPJ (dígitos verificadores).
     *
     * @param string $cnpj CNPJ.
     *
     * @return bool
     */
    public static function cnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj) ?? '';
        if (strlen($cnpj) !== 14) {
            return false;
        }
        if (preg_match('/(\d)\1{13}/', $cnpj)) {
            return false;
        }

        // Primeiro dígito.
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights1[$i];
        }
        $d1 = $sum % 11;
        $d1 = $d1 < 2 ? 0 : 11 - $d1;
        if ((int) $cnpj[12] !== $d1) {
            return false;
        }

        // Segundo dígito.
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights2[$i];
        }
        $d2 = $sum % 11;
        $d2 = $d2 < 2 ? 0 : 11 - $d2;
        return (int) $cnpj[13] === $d2;
    }

    /**
     * Valida e-mail.
     *
     * @param string $email E-mail.
     *
     * @return bool
     */
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida string decimal pt-BR ("1.234,56" ou "1234,56" ou "1234.56").
     *
     * @param string $value Valor.
     *
     * @return bool
     */
    public static function decimal(string $value): bool
    {
        $value = trim($value);
        if ($value === '') {
            return false;
        }
        // Aceita "1.234,56" ou "1234,56" ou "1234.56" ou "1234".
        return (bool) preg_match('/^-?\d{1,3}(\.\d{3})*(,\d{1,2})?$/', $value)
            || (bool) preg_match('/^-?\d+,\d{1,2}$/', $value)
            || (bool) preg_match('/^-?\d+(\.\d{1,2})?$/', $value);
    }

    // ---------- Internals ----------

    private function validateRequired(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }
        return $value !== null && $value !== [];
    }

    private function validateEmail(mixed $value): bool
    {
        if ($value === null || !is_string($value) || trim($value) === '') {
            return true; // não required por padrão.
        }
        return self::email($value);
    }

    private function validateCpf(mixed $value): bool
    {
        if ($value === null || !is_string($value) || trim($value) === '') {
            return true;
        }
        return self::cpf($value);
    }

    private function validateCnpj(mixed $value): bool
    {
        if ($value === null || !is_string($value) || trim($value) === '') {
            return true;
        }
        return self::cnpj($value);
    }

    private function validateMin(mixed $value, int $min): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return mb_strlen($value) >= $min;
    }

    private function validateMax(mixed $value, int $max): bool
    {
        if (!is_string($value)) {
            return false;
        }
        return mb_strlen($value) <= $max;
    }

    private function validateDecimal(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        return self::decimal($value);
    }

    private function validateDate(mixed $value): bool
    {
        if (!is_string($value) || trim($value) === '') {
            return false;
        }
        $value = trim($value);
        // dd/mm/YYYY.
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3]);
        }
        // YYYY-mm-dd.
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        }
        return false;
    }

    /**
     * @param mixed $param Deve ser array ['table'=>string, 'column'=>string, 'except'=>?int]
     */
    private function validateUnique(string $field, mixed $value, mixed $param): bool
    {
        if (!is_array($param) || !isset($param['table'], $param['column'])) {
            return true;
        }
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return true;
        }

        try {
            $pdo = Database::getInstance();
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $param['table']);
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $param['column']);

            $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE `{$column}` = ?";
            $params = [$value];

            if (isset($param['except']) && (int) $param['except'] > 0) {
                $sql .= ' AND id <> ?';
                $params[] = (int) $param['except'];
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch();
            return ((int) ($row['total'] ?? 0)) === 0;
        } catch (\Throwable $e) {
            Logger::error('Validator::unique falhou', [
                'field' => $field,
                'table' => $param['table'] ?? null,
                'error' => $e->getMessage(),
            ]);
            return true;
        }
    }

    private function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    private function messageFor(string $rule, string $field, mixed $param): string
    {
        return match ($rule) {
            'required' => sprintf('O campo "%s" é obrigatório.', $field),
            'email'    => sprintf('O campo "%s" deve conter um e-mail válido.', $field),
            'cpf'      => sprintf('O CPF informado no campo "%s" é inválido.', $field),
            'cnpj'     => sprintf('O CNPJ informado no campo "%s" é inválido.', $field),
            'min'      => sprintf('O campo "%s" deve ter no mínimo %s caracteres.', $field, (string) $param),
            'max'      => sprintf('O campo "%s" deve ter no máximo %s caracteres.', $field, (string) $param),
            'decimal'  => sprintf('O campo "%s" deve ser um valor decimal válido (ex.: 1.234,56).', $field),
            'date'     => sprintf('O campo "%s" deve ser uma data válida (dd/mm/AAAA).', $field),
            'unique'   => sprintf('O valor informado em "%s" já está em uso.', $field),
            default    => sprintf('O campo "%s" é inválido.', $field),
        };
    }
}
