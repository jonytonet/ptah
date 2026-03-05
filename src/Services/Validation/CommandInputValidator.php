<?php

declare(strict_types=1);

namespace Ptah\Services\Validation;

use Ptah\Exceptions\CommandValidationException;
use Illuminate\Support\Str;

/**
 * Validator for command-line inputs.
 *
 * Validates arguments and options passed to Artisan commands.
 *
 * @package Ptah\Services\Validation
 */
class CommandInputValidator
{
    /**
     * Validate that a model class exists.
     *
     * @param string $model
     * @return void
     * @throws CommandValidationException
     */
    public function validateModelExists(string $model): void
    {
        if (!class_exists($model)) {
            throw CommandValidationException::modelNotFound($model);
        }
    }

    /**
     * Validate column option format.
     *
     * Expected format: "field:type:modifier=value:modifier=value"
     *
     * @param string $value
     * @return array<string, mixed>
     * @throws CommandValidationException
     */
    public function validateColumnOption(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) < 2) {
            throw CommandValidationException::invalidOptionFormat(
                'column',
                $value,
                'field:type[:modifier=value...]'
            );
        }

        $field = $parts[0];
        $type = $parts[1];

        if (empty($field)) {
            throw CommandValidationException::invalidOptionFormat(
                'column',
                $value,
                'field:type[:modifier=value...] - field name cannot be empty'
            );
        }

        $validTypes = ['text', 'badge', 'boolean', 'date', 'datetime', 'money', 'numeric', 'relation'];
        if (!in_array($type, $validTypes, true)) {
            throw CommandValidationException::invalidOptionValue(
                'column type',
                $type,
                $validTypes
            );
        }

        return [
            'field' => $field,
            'type' => $type,
            'modifiers' => array_slice($parts, 2),
        ];
    }

    /**
     * Validate action option format.
     *
     * Expected format: "name:type:value:icon=icon:color=color"
     *
     * @param string $value
     * @return array<string, mixed>
     * @throws CommandValidationException
     */
    public function validateActionOption(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) < 3) {
            throw CommandValidationException::invalidOptionFormat(
                'action',
                $value,
                'name:type:value[:icon=icon:color=color]'
            );
        }

        $name = $parts[0];
        $type = $parts[1];
        $actionValue = $parts[2];

        if (empty($name)) {
            throw CommandValidationException::invalidOptionFormat(
                'action',
                $value,
                'name:type:value - action name cannot be empty'
            );
        }

        $validTypes = ['wire', 'route', 'url'];
        if (!in_array($type, $validTypes, true)) {
            throw CommandValidationException::invalidOptionValue(
                'action type',
                $type,
                $validTypes
            );
        }

        return [
            'name' => $name,
            'type' => $type,
            'value' => $actionValue,
            'modifiers' => array_slice($parts, 3),
        ];
    }

    /**
     * Validate filter option format.
     *
     * Expected format: "field:type:operator:label=Label"
     *
     * @param string $value
     * @return array<string, mixed>
     * @throws CommandValidationException
     */
    public function validateFilterOption(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) < 3) {
            throw CommandValidationException::invalidOptionFormat(
                'filter',
                $value,
                'field:type:operator[:label=Label]'
            );
        }

        $field = $parts[0];
        $type = $parts[1];
        $operator = $parts[2];

        if (empty($field)) {
            throw CommandValidationException::invalidOptionFormat(
                'filter',
                $value,
                'field:type:operator - field name cannot be empty'
            );
        }

        $validTypes = ['boolean', 'select', 'numeric', 'date', 'text'];
        if (!in_array($type, $validTypes, true)) {
            throw CommandValidationException::invalidOptionValue(
                'filter type',
                $type,
                $validTypes
            );
        }

        $validOperators = ['eq', 'ne', 'lt', 'gt', 'lte', 'gte', 'like', 'in'];
        if (!in_array($operator, $validOperators, true)) {
            throw CommandValidationException::invalidOptionValue(
                'filter operator',
                $operator,
                $validOperators
            );
        }

        return [
            'field' => $field,
            'type' => $type,
            'operator' => $operator,
            'modifiers' => array_slice($parts, 3),
        ];
    }

    /**
     * Validate style option format.
     *
     * Expected format: "field:operator:value:css"
     *
     * @param string $value
     * @return array<string, mixed>
     * @throws CommandValidationException
     */
    public function validateStyleOption(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) < 4) {
            throw CommandValidationException::invalidOptionFormat(
                'style',
                $value,
                'field:operator:value:css'
            );
        }

        $field = $parts[0];
        $operator = $parts[1];
        $styleValue = $parts[2];
        $css = implode(':', array_slice($parts, 3)); // Re-join in case CSS has colons

        if (empty($field)) {
            throw CommandValidationException::invalidOptionFormat(
                'style',
                $value,
                'field:operator:value:css - field name cannot be empty'
            );
        }

        $validOperators = ['eq', 'ne', 'lt', 'gt', 'lte', 'gte'];
        if (!in_array($operator, $validOperators, true)) {
            throw CommandValidationException::invalidOptionValue(
                'style operator',
                $operator,
                $validOperators
            );
        }

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $styleValue,
            'css' => $css,
        ];
    }

    /**
     * Validate join option format.
     *
     * Expected format: "type:table:first=second:select=field1,field2"
     *
     * @param string $value
     * @return array<string, mixed>
     * @throws CommandValidationException
     */
    public function validateJoinOption(string $value): array
    {
        $parts = explode(':', $value);

        if (count($parts) < 3) {
            throw CommandValidationException::invalidOptionFormat(
                'join',
                $value,
                'type:table:first=second[:select=field1,field2]'
            );
        }

        $type = $parts[0];
        $table = $parts[1];
        $on = $parts[2];

        $validTypes = ['inner', 'left', 'right'];
        if (!in_array($type, $validTypes, true)) {
            throw CommandValidationException::invalidOptionValue(
                'join type',
                $type,
                $validTypes
            );
        }

        if (empty($table)) {
            throw CommandValidationException::invalidOptionFormat(
                'join',
                $value,
                'type:table:first=second - table name cannot be empty'
            );
        }

        if (!str_contains($on, '=')) {
            throw CommandValidationException::invalidOptionFormat(
                'join',
                $value,
                'type:table:first=second - ON clause must use = format'
            );
        }

        return [
            'type' => $type,
            'table' => $table,
            'on' => $on,
            'modifiers' => array_slice($parts, 3),
        ];
    }

    /**
     * Validate set option format.
     *
     * Expected format: "key=value"
     *
     * @param string $value
     * @return array<string, string>
     * @throws CommandValidationException
     */
    public function validateSetOption(string $value): array
    {
        if (!str_contains($value, '=')) {
            throw CommandValidationException::invalidOptionFormat(
                'set',
                $value,
                'key=value'
            );
        }

        [$key, $setValue] = explode('=', $value, 2);

        if (empty($key)) {
            throw CommandValidationException::invalidOptionFormat(
                'set',
                $value,
                'key=value - key cannot be empty'
            );
        }

        $validKeys = ['itemsPerPage', 'cacheEnabled', 'cacheTime', 'paginationEnabled', 'exportEnabled'];
        if (!in_array($key, $validKeys, true)) {
            throw CommandValidationException::invalidOptionValue(
                'setting key',
                $key,
                $validKeys
            );
        }

        return [
            'key' => $key,
            'value' => $setValue,
        ];
    }

    /**
     * Parse modifiers from option parts.
     *
     * Converts ["label=Name", "sortable=true"] to ["label" => "Name", "sortable" => "true"]
     *
     * @param array<int, string> $modifierParts
     * @return array<string, string>
     */
    public function parseModifiers(array $modifierParts): array
    {
        $modifiers = [];

        foreach ($modifierParts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $modifiers[$key] = $value;
            }
        }

        return $modifiers;
    }

    /**
     * Validate that required modifiers are present.
     *
     * @param array<string, string> $modifiers
     * @param array<int, string> $required
     * @param string $context
     * @return void
     * @throws CommandValidationException
     */
    public function validateRequiredModifiers(array $modifiers, array $required, string $context): void
    {
        foreach ($required as $requiredKey) {
            if (!isset($modifiers[$requiredKey])) {
                throw CommandValidationException::invalidOptionFormat(
                    $context,
                    implode(':', array_map(fn($k, $v) => "{$k}={$v}", array_keys($modifiers), $modifiers)),
                    "Missing required modifier: {$requiredKey}"
                );
            }
        }
    }
}
