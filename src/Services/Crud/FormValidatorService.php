<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

/**
 * Serviço de validação rica para campos do formulário do BaseCrud.
 *
 * Suporta regras configuradas por coluna em `colsValidations`:
 *   required, email, url, integer, numeric, min:X, max:X,
 *   between:X,Y, minLength:X, maxLength:X, regex:pattern,
 *   cpf, cnpj, phone
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
                $error = $this->applyRule($rule, $value, $label);

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
     */
    protected function applyRule(string $rule, mixed $value, string $label): ?string
    {
        // Regras com parâmetro: min:X, max:X, minLength:X, maxLength:X, between:X,Y, regex:expr
        if (str_contains($rule, ':')) {
            [$ruleName, $param] = explode(':', $rule, 2);

            return match (strtolower($ruleName)) {
                'min'       => is_numeric($value) && (float) $value < (float) $param
                    ? "{$label} deve ser no mínimo {$param}."
                    : null,
                'max'       => is_numeric($value) && (float) $value > (float) $param
                    ? "{$label} deve ser no máximo {$param}."
                    : null,
                'minlength' => mb_strlen((string) $value) < (int) $param
                    ? "{$label} deve ter pelo menos {$param} caracteres."
                    : null,
                'maxlength' => mb_strlen((string) $value) > (int) $param
                    ? "{$label} deve ter no máximo {$param} caracteres."
                    : null,
                'between'   => $this->validateBetween($value, $param, $label),
                'regex'     => $this->validateRegex($value, $param, $label),
                default     => null,
            };
        }

        // Regras simples
        return match (strtolower($rule)) {
            'email'   => ! filter_var($value, FILTER_VALIDATE_EMAIL)
                ? "{$label} deve ser um e-mail válido."
                : null,
            'url'     => ! filter_var($value, FILTER_VALIDATE_URL)
                ? "{$label} deve ser uma URL válida."
                : null,
            'integer' => ! ctype_digit(ltrim((string) $value, '-'))
                ? "{$label} deve ser um número inteiro."
                : null,
            'numeric' => ! is_numeric($value)
                ? "{$label} deve ser um valor numérico."
                : null,
            'cpf'     => ! $this->validateCpf((string) $value)
                ? "{$label} inválido."
                : null,
            'cnpj'    => ! $this->validateCnpj((string) $value)
                ? "{$label} inválido."
                : null,
            'phone'   => ! preg_match('/^\(?\d{2}\)?[\s\-]?\d{4,5}[\s\-]?\d{4}$/', preg_replace('/\D/', '', (string) $value))
                ? "{$label} deve ser um telefone válido."
                : null,
            default   => null,
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

    /**
     * Aceita tanto booleano (true/false) quanto legado string ('S'/'N').
     * Retorna true para: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
