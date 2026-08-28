<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;
use Ptah\Support\FilterRule;

class FilterParser
{
    /**
     * Parse filter definition string
     *
     * Format: field:type:option1=value1:option2=value2
     * Example: supplier_name:text:label=Fornecedor:whereHas=supplier:field=name:operator=LIKE
     */
    public function parse(string $definition): array
    {
        $parts = $this->tokenize($definition);
        $field = array_shift($parts);
        $type = array_shift($parts) ?? 'text';

        $config = [
            'field' => $field,
            'label' => Str::title(str_replace('_', ' ', $field)),
            'colsFilterType' => $type,
            'defaultOperator' => '=',
            'whereHas' => '',
            'field_relation' => '',
            'aggregate' => '',
        ];

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$k, $v] = explode('=', $part, 2);

                // Handle options key
                if ($k === 'options') {
                    $config['options'] = $v;
                } else {
                    $config[$k] = $v;
                }
            }
        }

        // Funnels through the single normaliser so `--filter=`, the interactive
        // wizard and older saved configs cannot drift into different shapes
        // again — see FilterRule for the three dialects this closed.
        return FilterRule::normalize($config) ?? $config;
    }

    /**
     * Smart tokenizer: splits field:type:key=value:key=value preserving ':'
     * that appear inside the VALUE side of a key=value pair (e.g. the
     * "options=active:Active,inactive:Inactive" select-options list).
     *
     * BUG FIX (Onda 4 Parte B): a plain explode(':', $definition) silently
     * truncated any option value containing ':' at the first colon — e.g.
     * "options=active:Active,inactive:Inactive" resolved to just "active",
     * dropping "Active,inactive:Inactive" with no error. ColumnParser already
     * solves the identical problem for the same options=... syntax with this
     * exact algorithm; mirrored here rather than left unfixed.
     *
     * @return array<int, string>
     */
    private function tokenize(string $definition): array
    {
        $raw = explode(':', $definition);
        $result = [];
        $buffer = null;

        foreach ($raw as $i => $part) {
            // First two tokens (field, type) are always standalone.
            if ($i < 2) {
                $result[] = $part;

                continue;
            }

            if (str_contains($part, '=')) {
                // A new key=value pair — flush any buffered value first.
                if ($buffer !== null) {
                    $result[] = $buffer;
                }
                $buffer = $part;
            } elseif ($buffer !== null) {
                // No '=' and we have an open buffer → this fragment is a
                // continuation of the previous value (value contained ':').
                $buffer .= ':'.$part;
            } else {
                $result[] = $part;
            }
        }

        if ($buffer !== null) {
            $result[] = $buffer;
        }

        return $result;
    }
}
