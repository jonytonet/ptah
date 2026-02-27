<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

/**
 * Serviço de validação rica para campos do formulário do BaseCrud.
 *
 * Suporta regras configuradas por coluna em `colsValidations`:
 *   required, email, url, integer, numeric, alpha, alphaNum,
 *   min:X, max:X, between:X,Y, minLength:X, maxLength:X,
 *   digits:N, digitsBetween:N,M, regex:pattern,
 *   in:a,b,c, notIn:a,b,c,
 *   after:YYYY-MM-DD|today, before:YYYY-MM-DD|today,
 *   confirmed:fieldName, unique:Model,field,
 *   dateFormat:d/m/Y,
 *   cpf, cnpj, phone, ncm
 */
class FormValidatorService
{
    /**
     * Valida os dados do formulário de acordo com as regras de cada coluna.
     *
     * @param  array $formData  Dados submetidos (campo => valor)
     * @param  array $formCols  Colunas com colsGravar === true e suas regras
     * @return array            Erros por campo ['campo' => 'Mensagem de erro']
     */
    public function validate(array $formData, array $formCols): array
    {
        $errors = [];

        foreach ($formCols as $col) {
            $field    = $col['colsNomeFisico'] ?? null;
            $label    = $col['colsNomeLogico'] ?? $field;
            $required = $this->ptahBool($col['colsRequired'] ?? false);
            $rules    = $col['colsValidations'] ?? [];

            if (! $field) {
                continue;
            }

            $value = $formData[$field] ?? null;
            $empty = $value === null || $value === '';

            // ── required (via colsRequired) ────────────────────────────────
            if ($required && $empty) {
                $errors[$field] = "{$label} é obrigatório.";
                continue; // Não valida regras adicionais em campo vazio obrigatório
            }

            if ($empty) {
                continue; // Campo opcional vazio: pula as demais validações
            }

            // ── regras adicionais ──────────────────────────────────────────
            foreach ((array) $rules as $rule) {
                $error = $this->applyRule($rule, $value, $label, $field, $formData);

                if ($error !== null) {
                    $errors[$field] = $error;
                    break; // Apenas o primeiro erro por campo
                }
            }
        }

        return $errors;
    }

    /**
     * Aplica uma única regra ao valor e retorna a mensagem de erro, ou null se válido.
     *
     * @param string $rule     Regra ex: "email", "min:3", "in:a,b,c"
     * @param mixed  $value    Valor do campo
     * @param string $label    Label do campo para mensagens de erro
     * @param string $field    Nome físico do campo (para `confirmed`)
     * @param array  $formData Todos os dados do formulário (para `confirmed` e `unique`)
     */
    protected function applyRule(string $rule, mixed $value, string $label, string $field = '', array $formData = []): ?string
    {
        // ── Regras com parâmetro: min:X, max:X, minLength:X, maxLength:X, etc. ────
        if (str_contains($rule, ':')) {
            [$ruleName, $param] = explode(':', $rule, 2);

            return match (strtolower($ruleName)) {
                'min'           => is_numeric($value) && (float) $value < (float) $param
                    ? "{$label} deve ser no mínimo {$param}."
                    : null,
                'max'           => is_numeric($value) && (float) $value > (float) $param
                    ? "{$label} deve ser no máximo {$param}."
                    : null,
                'minlength'     => mb_strlen((string) $value) < (int) $param
                    ? "{$label} deve ter pelo menos {$param} caracteres."
                    : null,
                'maxlength'     => mb_strlen((string) $value) > (int) $param
                    ? "{$label} deve ter no máximo {$param} caracteres."
                    : null,
                'between'       => $this->validateBetween($value, $param, $label),
                'regex'         => $this->validateRegex($value, $param, $label),
                // digits:N — exatamente N dígitos
                'digits'        => (! preg_match('/^\d+$/', (string) $value) || strlen((string) $value) !== (int) $param)
                    ? "{$label} deve ter exatamente {$param} dígito(s)."
                    : null,
                // digitsBetween:N,M — entre N e M dígitos
                'digitsbetween' => $this->validateDigitsBetween($value, $param, $label),
                // in:a,b,c — valor deve estar entre as opções
                'in'            => ! in_array((string) $value, array_map('trim', explode(',', $param)), true)
                    ? "{$label} deve ser um dos valores: {$param}."
                    : null,
                // notIn:a,b,c — valor NÃO deve estar entre as opções
                'notin'         => in_array((string) $value, array_map('trim', explode(',', $param)), true)
                    ? "{$label} não pode ser: {$param}."
                    : null,
                // after:YYYY-MM-DD ou after:today
                'after'         => $this->validateDateComparison($value, $param, 'after', $label),
                // before:YYYY-MM-DD ou before:today
                'before'        => $this->validateDateComparison($value, $param, 'before', $label),
                // confirmed:fieldName — campos devem ser iguais
                'confirmed'     => $this->validateConfirmed($value, $param, $formData, $label),
                // unique:Model,field — verifica unicidade via Eloquent
                'unique'        => $this->validateUnique($value, $param, $formData, $label),
                // dateFormat:d/m/Y — formato de data específico
                'dateformat'    => $this->validateDateFormat($value, $param, $label),
                default         => null,
            };
        }

        // ── Regras simples ───────────────────────────────────────────────────────
        return match (strtolower($rule)) {
            'email'    => ! filter_var($value, FILTER_VALIDATE_EMAIL)
                ? "{$label} deve ser um e-mail válido."
                : null,
            'url'      => ! filter_var($value, FILTER_VALIDATE_URL)
                ? "{$label} deve ser uma URL válida."
                : null,
            'integer'  => ! ctype_digit(ltrim((string) $value, '-'))
                ? "{$label} deve ser um número inteiro."
                : null,
            'numeric'  => ! is_numeric($value)
                ? "{$label} deve ser um valor numérico."
                : null,
            // alpha — apenas letras (Unicode)
            'alpha'    => ! preg_match('/^\p{L}+$/u', (string) $value)
                ? "{$label} deve conter apenas letras."
                : null,
            // alphaNum — letras e números
            'alphanum' => ! preg_match('/^[\p{L}\d]+$/u', (string) $value)
                ? "{$label} deve conter apenas letras e números."
                : null,
            // ncm — 8 dígitos (pode vir formatado como 0000.00.00 ou 00000000)
            'ncm'      => ! preg_match('/^\d{4}\.\d{2}\.\d{2}$|^\d{8}$/', (string) $value)
                ? "{$label} deve ser um NCM válido (ex: 8471.30.19 ou 84713019)."
                : null,
            'cpf'      => ! $this->validateCpf((string) $value)
                ? "{$label} inválido."
                : null,
            'cnpj'     => ! $this->validateCnpj((string) $value)
                ? "{$label} inválido."
                : null,
            'phone'    => ! preg_match('/^\(?\d{2}\)?[\s\-]?\d{4,5}[\s\-]?\d{4}$/', preg_replace('/\D/', '', (string) $value))
                ? "{$label} deve ser um telefone válido."
                : null,
            default    => null,
        };
    }

    protected function validateBetween(mixed $value, string $param, string $label): ?string
    {
        $parts = explode(',', $param, 2);

        if (count($parts) !== 2) {
            return null;
        }

        [$min, $max] = [(float) $parts[0], (float) $parts[1]];
        $num = (float) $value;

        if ($num < $min || $num > $max) {
            return "{$label} deve estar entre {$min} e {$max}.";
        }

        return null;
    }

    protected function validateRegex(mixed $value, string $pattern, string $label): ?string
    {
        // Encapsula se não tiver delimitadores
        if (! preg_match('/^[\/~@#%\|!]/', $pattern)) {
            $pattern = '/' . $pattern . '/';
        }

        if (! @preg_match($pattern, (string) $value)) {
            return "{$label} possui formato inválido.";
        }

        return null;
    }

    protected function validateCpf(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);

        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1+$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) {
                $sum += (int) $cpf[$i] * ($t + 1 - $i);
            }
            $rem = (10 * $sum) % 11;
            if ((int) $cpf[$t] !== ($rem < 10 ? $rem : 0)) {
                return false;
            }
        }

        return true;
    }

    protected function validateCnpj(string $cnpj): bool
    {
        $cnpj = preg_replace('/\D/', '', $cnpj);

        if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }

        $calc = function (string $cnpj, int $length): int {
            $sum    = 0;
            $pos    = $length - 7;
            for ($i = $length; $i >= 1; $i--) {
                $sum += (int) $cnpj[$length - $i] * $pos--;
                if ($pos < 2) {
                    $pos = 9;
                }
            }
            return $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
        };

        return (int) $cnpj[12] === $calc($cnpj, 12)
            && (int) $cnpj[13] === $calc($cnpj, 13);
    }

    // ── Helpers para novas regras ────────────────────────────────────────────────

    /**
     * digitsBetween:N,M — verifica se o valor tem entre N e M dígitos.
     */
    protected function validateDigitsBetween(mixed $value, string $param, string $label): ?string
    {
        $parts = explode(',', $param, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$min, $max] = [(int) trim($parts[0]), (int) trim($parts[1])];
        $digits = preg_replace('/\D/', '', (string) $value);
        $len    = strlen($digits);

        return ($len < $min || $len > $max)
            ? "{$label} deve ter entre {$min} e {$max} dígitos."
            : null;
    }

    /**
     * after:ref / before:ref — compara data do campo com uma referência.
     * Referência pode ser "today" ou uma data legível por strtotime().
     */
    protected function validateDateComparison(mixed $value, string $ref, string $direction, string $label): ?string
    {
        $fieldDate = strtotime((string) $value);
        $refDate   = strtolower($ref) === 'today' ? strtotime('today') : strtotime($ref);

        if ($fieldDate === false || $refDate === false) {
            return "{$label} possui uma data inválida.";
        }

        if ($direction === 'after' && $fieldDate <= $refDate) {
            return "{$label} deve ser uma data posterior a {$ref}.";
        }
        if ($direction === 'before' && $fieldDate >= $refDate) {
            return "{$label} deve ser uma data anterior a {$ref}.";
        }

        return null;
    }

    /**
     * confirmed:fieldName — verifica se o campo é igual ao campo de confirmação.
     */
    protected function validateConfirmed(mixed $value, string $confirmField, array $formData, string $label): ?string
    {
        $confirmValue = $formData[$confirmField] ?? null;

        return $value !== $confirmValue
            ? "{$label} não confere com a confirmação."
            : null;
    }

    /**
     * unique:Model,column[,ignoreId] — verifica unicidade via Eloquent.
     * Exemplo: "unique:App\Models\Product,email"
     *          "unique:Product,email"   (auto-prefixo App\Models\)
     *
     * Ignora automaticamente o registro em edição quando $formData['id'] existir.
     */
    protected function validateUnique(mixed $value, string $param, array $formData, string $label): ?string
    {
        $parts  = array_map('trim', explode(',', $param));
        $model  = $parts[0] ?? '';
        $column = $parts[1] ?? 'id';

        // Auto-prefixo se não houver namespace completo
        if (! str_contains($model, '\\')) {
            $model = "App\\Models\\{$model}";
        }

        if (! class_exists($model)) {
            return null; // modelo não encontrado → ignora silenciosamente
        }

        $ignoreId = $parts[2] ?? ($formData['id'] ?? null);

        $query = $model::where($column, $value);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists()
            ? "{$label} já está em uso."
            : null;
    }

    /**
     * dateFormat:FORMAT — valida se o valor segue o formato de data especificado.
     * Exemplo: "dateFormat:d/m/Y"
     */
    protected function validateDateFormat(mixed $value, string $format, string $label): ?string
    {
        $date = \DateTime::createFromFormat($format, (string) $value);
        $valid = $date && $date->format($format) === (string) $value;

        return ! $valid
            ? "{$label} deve estar no formato {$format}."
            : null;
    }

    // ── Utilitários ─────────────────────────────────────────────────────────────

    /**
     * Aceita tanto booleano (true/false) quanto legado string ('S'/'N').
     * Retorna true para: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
