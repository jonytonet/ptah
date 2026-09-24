<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;
use Ptah\Support\FilterRule;
use Ptah\Support\SelectOptions;

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

                if ($k === 'options') {
                    // `colsSelect`, not `options`: the filter panel reads
                    // `$cf['colsSelect']` for a select filter (see
                    // _filter-panel.blade.php), so a value stored under
                    // `options` was never read and every `--filter=…:options=`
                    // select rendered empty. Same normaliser as the column
                    // parser, so the two cannot drift apart again.
                    $config['colsSelect'] = SelectOptions::normalize($v);
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
     * Tokens after `field:type` that parse() silently throws away.
     *
     * The format is `field:type` then `key=value` only. A positional operator
     * — `name:text:LIKE:…`, `score:number:>=:…` — used to vanish without a word
     * and the filter fell back to equality. A `key=value` opens a value that
     * may continue across `:` (`options=1:Ativo,0:Inativo`); a bare token is
     * legal only as that continuation, and a "key" that is not an identifier
     * (`=`, `>=`) is an operator in disguise.
     *
     * @return list<string>
     */
    public static function discardedTokens(string $definition): array
    {
        $parts = explode(':', $definition);
        array_shift($parts); // field
        array_shift($parts); // type

        $discarded = [];
        $open = false;

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                $open = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', explode('=', $part, 2)[0]) === 1;

                if (! $open) {
                    $discarded[] = $part;
                }

                continue;
            }

            if (! $open) {
                $discarded[] = $part;
            }
        }

        return $discarded;
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
