<?php

namespace Ptah\Commands\Config\Parsers;

class JoinParser
{
    /**
     * Parse join definition string
     *
     * Format: type:table:first=second:option1=value1
     * Example: left:suppliers:products.supplier_id=suppliers.id:distinct:select=suppliers.name:supplier_name,suppliers.cnpj:supplier_cnpj
     */
    public function parse(string $definition): array
    {
        $parts = $this->tokenize($definition);

        if (count($parts) < 3) {
            throw new \InvalidArgumentException('JOIN syntax requires at least: type:table:first=second');
        }

        // The shape HasCrudQuery::applyJoins() reads (1.50.0). `select=` used
        // to be stored as a `selectRaw` string nothing read, and `distinct=true`
        // and `where=` were dropped without a word.
        $config = [
            'type' => array_shift($parts),
            'table' => array_shift($parts),
            'first' => '',
            'second' => '',
            'distinct' => false,
            'select' => [],
        ];

        foreach ($parts as $part) {
            if ($part === 'distinct') {
                $config['distinct'] = true;
            } elseif (str_starts_with($part, 'distinct=')) {
                $config['distinct'] = in_array(strtolower(substr($part, 9)), ['1', 'true', 'yes'], true);
            } elseif (str_starts_with($part, 'select=')) {
                $config['select'] = self::parseSelect(substr($part, 7));
            } elseif (preg_match('/^[A-Za-z_][\w]*=/', $part) === 1) {
                // A `key=value` option the runtime has no meaning for (where=…).
                throw new \InvalidArgumentException(
                    'JOIN option "'.strstr($part, '=', true).'" is not supported (only the ON pair, distinct and select=). '
                    .'A JOIN has no WHERE of its own — filter the screen instead.'
                );
            } elseif (str_contains($part, '=') && $config['first'] === '') {
                // ON condition: products.supplier_id=suppliers.id
                [$config['first'], $config['second']] = explode('=', $part, 2);
            }
        }

        return $config;
    }

    /**
     * `suppliers.name:supplier_name,suppliers.cnpj:supplier_cnpj` (or
     * `suppliers.name as supplier_name`) → [{column, alias}, …].
     *
     * @return list<array{column: string, alias: string}>
     */
    public static function parseSelect(string $value): array
    {
        $out = [];
        foreach (explode(',', $value) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $pair = preg_split('/\s+as\s+|:/i', $item, 2) ?: [$item];
            $column = trim($pair[0]);
            $alias = trim($pair[1] ?? '');
            if ($alias === '') {
                $alias = str_contains($column, '.') ? substr($column, strrpos($column, '.') + 1) : $column;
            }
            $out[] = ['column' => $column, 'alias' => $alias];
        }

        return $out;
    }

    /**
     * The naive explode(':') truncated select= at the first ':' inside its
     * value — the docblock's own example (select=suppliers.name:supplier_name,
     * suppliers.cnpj:supplier_cnpj) silently lost everything past
     * "suppliers.name". Same algorithm as FilterParser/ColumnParser, with one
     * join-specific twist: the bare keyword "distinct" is always a standalone
     * token, never a continuation of an open key=value buffer (it legally
     * appears right after the ON pair, which opens a buffer).
     *
     * @return array<int, string>
     */
    private function tokenize(string $definition): array
    {
        $raw = explode(':', $definition);
        $result = [];
        $buffer = null;

        foreach ($raw as $i => $part) {
            // First two tokens (type, table) are always standalone.
            if ($i < 2) {
                $result[] = $part;

                continue;
            }

            if ($part === 'distinct') {
                if ($buffer !== null) {
                    $result[] = $buffer;
                    $buffer = null;
                }
                $result[] = $part;
            } elseif (str_contains($part, '=')) {
                if ($buffer !== null) {
                    $result[] = $buffer;
                }
                $buffer = $part;
            } elseif ($buffer !== null) {
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
