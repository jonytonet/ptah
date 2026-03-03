<?php

declare(strict_types=1);

namespace Ptah\Services\Crud;

/**
 * Rich validation service for BaseCrud form fields.
 *
 * Supports rules configured per column in `colsValidations`:
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
     * Validates form data according to each column's rules.
     *
     * @param  array $formData  Submitted data (field => value)
     * @param  array $formCols  Columns with colsGravar === true and their rules
     * @return array            Errors per field ['field' => 'Error message']
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
                $errors[$field] = trans('ptah::ui.validation_required', ['label' => $label]);
                continue; // Skip additional rules for an empty required field
            }

            if ($empty) {
                continue; // Optional empty field: skip remaining validations
            }

            // ── additional rules ──────────────────────────────────────────────────
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
     * Applies a single rule to the value and returns the error message, or null if valid.
     *
     * @param string $rule     Rule e.g. "email", "min:3", "in:a,b,c"
     * @param mixed  $value    Field value
     * @param string $label    Field label for error messages
     * @param string $field    Physical field name (for `confirmed`)
     * @param array  $formData All form data (for `confirmed` and `unique`)
     */
    protected function applyRule(string $rule, mixed $value, string $label, string $field = '', array $formData = []): ?string
    {
        // ── Rules with parameter: min:X, max:X, minLength:X, maxLength:X, etc. ──────
        if (str_contains($rule, ':')) {
            [$ruleName, $param] = explode(':', $rule, 2);

            return match (strtolower($ruleName)) {
                'min'           => is_numeric($value) && (float) $value < (float) $param
                    ? trans('ptah::ui.validation_min', ['label' => $label, 'param' => $param])
                    : null,
                'max'           => is_numeric($value) && (float) $value > (float) $param
                    ? trans('ptah::ui.validation_max', ['label' => $label, 'param' => $param])
                    : null,
                'minlength'     => mb_strlen((string) $value) < (int) $param
                    ? trans('ptah::ui.validation_minlength', ['label' => $label, 'param' => $param])
                    : null,
                'maxlength'     => mb_strlen((string) $value) > (int) $param
                    ? trans('ptah::ui.validation_maxlength', ['label' => $label, 'param' => $param])
                    : null,
                'between'       => $this->validateBetween($value, $param, $label),
                'regex'         => $this->validateRegex($value, $param, $label),
                // digits:N — exactly N digits
                'digits'        => (! preg_match('/^\d+$/', (string) $value) || strlen((string) $value) !== (int) $param)
                    ? trans('ptah::ui.validation_digits', ['label' => $label, 'param' => $param])
                    : null,
                // digitsBetween:N,M — between N and M digits
                'digitsbetween' => $this->validateDigitsBetween($value, $param, $label),
                // in:a,b,c — value must be among the options
                'in'            => ! in_array((string) $value, array_map('trim', explode(',', $param)), true)
                    ? trans('ptah::ui.validation_in', ['label' => $label, 'param' => $param])
                    : null,
                // notIn:a,b,c — value must NOT be among the options
                'notin'         => in_array((string) $value, array_map('trim', explode(',', $param)), true)
                    ? trans('ptah::ui.validation_not_in', ['label' => $label, 'param' => $param])
                    : null,
                // after:YYYY-MM-DD or after:today
                'after'         => $this->validateDateComparison($value, $param, 'after', $label),
                // before:YYYY-MM-DD or before:today
                'before'        => $this->validateDateComparison($value, $param, 'before', $label),
                // confirmed:fieldName — fields must match
                'confirmed'     => $this->validateConfirmed($value, $param, $formData, $label),
                // unique:Model,field — checks uniqueness via Eloquent
                'unique'        => $this->validateUnique($value, $param, $formData, $label),
                // dateFormat:d/m/Y — specific date format
                'dateformat'    => $this->validateDateFormat($value, $param, $label),
                default         => null,
            };
        }

        // ── Regras simples ───────────────────────────────────────────────────────
        return match (strtolower($rule)) {
            'email'    => ! filter_var($value, FILTER_VALIDATE_EMAIL)
                ? trans('ptah::ui.validation_email', ['label' => $label])
                : null,
            'url'      => ! filter_var($value, FILTER_VALIDATE_URL)
                ? trans('ptah::ui.validation_url', ['label' => $label])
                : null,
            'integer'  => ! ctype_digit(ltrim((string) $value, '-'))
                ? trans('ptah::ui.validation_integer', ['label' => $label])
                : null,
            'numeric'  => ! is_numeric($value)
                ? trans('ptah::ui.validation_numeric', ['label' => $label])
                : null,
            // alpha — letters only (Unicode)
            'alpha'    => ! preg_match('/^\p{L}+$/u', (string) $value)
                ? trans('ptah::ui.validation_alpha', ['label' => $label])
                : null,
            // alphaNum — letters and digits
            'alphanum' => ! preg_match('/^[\p{L}\d]+$/u', (string) $value)
                ? trans('ptah::ui.validation_alpha_num', ['label' => $label])
                : null,
            // ncm — 8 digits (may be formatted as 0000.00.00 or 00000000)
            'ncm'      => ! preg_match('/^\d{4}\.\d{2}\.\d{2}$|^\d{8}$/', (string) $value)
                ? trans('ptah::ui.validation_ncm', ['label' => $label])
                : null,
            'cpf'      => ! $this->validateCpf((string) $value)
                ? trans('ptah::ui.validation_invalid', ['label' => $label])
                : null,
            'cnpj'     => ! $this->validateCnpj((string) $value)
                ? trans('ptah::ui.validation_invalid', ['label' => $label])
                : null,
            'phone'    => ! preg_match('/^\(?\d{2}\)?[\s\-]?\d{4,5}[\s\-]?\d{4}$/', preg_replace('/\D/', '', (string) $value))
                ? trans('ptah::ui.validation_phone', ['label' => $label])
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
            return trans('ptah::ui.validation_between', ['label' => $label, 'min' => $min, 'max' => $max]);
        }

        return null;
    }

    protected function validateRegex(mixed $value, string $pattern, string $label): ?string
    {
        // Wraps if there are no delimiters
        if (! preg_match('/^[\/~@#%\|!]/', $pattern)) {
            $pattern = '/' . $pattern . '/';
        }

        if (! @preg_match($pattern, (string) $value)) {
            return trans('ptah::ui.validation_regex', ['label' => $label]);
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
     * digitsBetween:N,M — checks whether the value has between N and M digits.
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
            ? trans('ptah::ui.validation_digits_between', ['label' => $label, 'min' => $min, 'max' => $max])
            : null;
    }

    /**
     * after:ref / before:ref — compares the field date with a reference.
     * Reference can be "today" or any date readable by strtotime().
     */
    protected function validateDateComparison(mixed $value, string $ref, string $direction, string $label): ?string
    {
        $fieldDate = strtotime((string) $value);
        $refDate   = strtolower($ref) === 'today' ? strtotime('today') : strtotime($ref);

        if ($fieldDate === false || $refDate === false) {
            return trans('ptah::ui.validation_date_invalid', ['label' => $label]);
        }

        if ($direction === 'after' && $fieldDate <= $refDate) {
            return trans('ptah::ui.validation_after', ['label' => $label, 'ref' => $ref]);
        }
        if ($direction === 'before' && $fieldDate >= $refDate) {
            return trans('ptah::ui.validation_before', ['label' => $label, 'ref' => $ref]);
        }

        return null;
    }

    /**
     * confirmed:fieldName — checks whether the field matches the confirmation field.
     */
    protected function validateConfirmed(mixed $value, string $confirmField, array $formData, string $label): ?string
    {
        $confirmValue = $formData[$confirmField] ?? null;

        return $value !== $confirmValue
            ? trans('ptah::ui.validation_confirmed', ['label' => $label])
            : null;
    }

    /**
     * unique:Model,column[,ignoreId] — checks uniqueness via Eloquent.
     * Examples: "unique:App\Models\Product,email"
     *           "unique:Product,email"   (auto-prefixes App\Models\)
     *
     * Automatically ignores the record under edit when $formData['id'] exists.
     */
    protected function validateUnique(mixed $value, string $param, array $formData, string $label): ?string
    {
        $parts  = array_map('trim', explode(',', $param));
        $model  = $parts[0] ?? '';
        $column = $parts[1] ?? 'id';

        // Auto-prefix when there is no full namespace
        if (! str_contains($model, '\\')) {
            $model = "App\\Models\\{$model}";
        }

        if (! class_exists($model)) {
            return null; // model not found → ignore silently
        }

        $ignoreId = $parts[2] ?? ($formData['id'] ?? null);

        $query = $model::where($column, $value);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists()
            ? trans('ptah::ui.validation_unique', ['label' => $label])
            : null;
    }

    /**
     * dateFormat:FORMAT — validates whether the value follows the specified date format.
     * Example: "dateFormat:d/m/Y"
     */
    protected function validateDateFormat(mixed $value, string $format, string $label): ?string
    {
        $date = \DateTime::createFromFormat($format, (string) $value);
        $valid = $date && $date->format($format) === (string) $value;

        return ! $valid
            ? trans('ptah::ui.validation_date_format', ['label' => $label, 'format' => $format])
            : null;
    }

    // ── Utilities ───────────────────────────────────────────────────────────────

    /**
     * Accepts both boolean (true/false) and legacy string ('S'/'N').
     * Returns true for: true, 'S', 1, '1'.
     */
    protected function ptahBool(mixed $value): bool
    {
        return $value === true || $value === 'S' || $value === 1 || $value === '1';
    }
}
