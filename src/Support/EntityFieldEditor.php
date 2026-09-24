<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * Adds one field to the files `ptah:forge` generated, by editing them in place.
 *
 * Adding a column to an existing entity is five edits in five files — the
 * migration, `$fillable`, `$casts`, the Store and Update rules, the DTO — and
 * the one an agent forgets is the one that bites: a field outside `$fillable`
 * is silently not saved, a DTO without it silently drops it. These are the
 * same edits, made where the generator put things.
 *
 * Every method returns the new source, or NULL when the anchor it needs is not
 * there (a hand-edited file). NULL is reported, never guessed around: an edit
 * in the wrong place compiles and is worse than no edit. A field already
 * present returns the source unchanged.
 */
final class EntityFieldEditor
{
    public static function addToFillable(string $code, string $field): ?string
    {
        return self::addToArrayProperty($code, 'fillable', "'{$field}'", "'{$field}'");
    }

    public static function addToCasts(string $code, string $field, string $cast): ?string
    {
        return self::addToArrayProperty($code, 'casts', "'{$field}' => '{$cast}'", "'{$field}'");
    }

    /**
     * The rule line into `rules(): array { return [ … ]; }`.
     */
    public static function addRule(string $code, string $field, string $ruleLine): ?string
    {
        if (preg_match('/function\s+rules\s*\([^)]*\)\s*:\s*array\s*\{\s*return\s*\[(.*?)\n(\s*)\];/s', $code, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        if (preg_match("/['\"]".preg_quote($field, '/')."['\"]\s*=>/", $m[1][0]) === 1) {
            return $code;
        }

        $indent = $m[2][0].'    ';
        $insertAt = $m[1][1] + strlen($m[1][0]);

        return substr($code, 0, $insertAt)."\n".$indent.$ruleLine.substr($code, $insertAt);
    }

    /**
     * A constructor property and its `fromArray()` mapping. A required
     * property goes before the first optional one (PHP deprecates optional
     * parameters before required ones); fromArray uses named arguments, so
     * order there does not matter.
     */
    public static function addToDto(string $code, string $field, string $phpType, bool $nullable): ?string
    {
        if (preg_match('/function\s+__construct\s*\((.*?)\n(\s*)\)\s*\{\s*\}/s', $code, $ctor, PREG_OFFSET_CAPTURE) !== 1
            || preg_match('/function\s+fromArray\s*\([^)]*\)\s*:\s*static\s*\{\s*return\s+new\s+static\s*\((.*?)\n(\s*)\);/s', $code, $from) !== 1) {
            return null;
        }

        if (preg_match('/\$'.preg_quote($field, '/').'\b/', $ctor[1][0]) === 1) {
            return $code;
        }

        $propIndent = $ctor[2][0].'    ';
        $property = $propIndent."public readonly {$phpType} \${$field}".($nullable ? ' = null,' : ',');
        $params = $ctor[1][0];

        if (! $nullable && preg_match('/\n[^\n]*= null,/', $params, $firstOptional, PREG_OFFSET_CAPTURE) === 1) {
            $params = substr($params, 0, $firstOptional[0][1])."\n".$property.substr($params, $firstOptional[0][1]);
        } else {
            $params .= "\n".$property;
        }

        $code = substr($code, 0, $ctor[1][1]).$params.substr($code, $ctor[1][1] + strlen($ctor[1][0]));

        // Recalcula: o construtor acima deslocou os offsets.
        preg_match('/function\s+fromArray\s*\([^)]*\)\s*:\s*static\s*\{\s*return\s+new\s+static\s*\((.*?)\n(\s*)\);/s', $code, $from, PREG_OFFSET_CAPTURE);

        $argIndent = $from[2][0].'    ';
        $mapping = $argIndent."{$field}: \$data['{$field}']".($nullable ? ' ?? null,' : ',');
        $insertAt = $from[1][1] + strlen($from[1][0]);

        return substr($code, 0, $insertAt)."\n".$mapping.substr($code, $insertAt);
    }

    private static function addToArrayProperty(string $code, string $property, string $entry, string $presence): ?string
    {
        if (preg_match('/(protected|public)\s+\$'.$property.'\s*=\s*\[(.*?)\n(\s*)\];/s', $code, $m, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $body = $m[2][0];

        if (preg_match('/^\s*'.preg_quote($presence, '/').'\s*(,|=>)/m', $body) === 1) {
            return $code;
        }

        $indent = $m[3][0].'    ';
        $line = $indent.$entry.',';

        // Antes dos campos de auditoria, onde o gerador os deixa por ultimo.
        if (preg_match("/\n\s*'created_by'/", $body, $audit, PREG_OFFSET_CAPTURE) === 1) {
            $at = $m[2][1] + $audit[0][1];

            return substr($code, 0, $at)."\n".$line.substr($code, $at);
        }

        $at = $m[2][1] + strlen($body);
        $trimmed = rtrim(substr($code, 0, $at));
        $needsComma = $trimmed !== '' && ! str_ends_with($trimmed, ',') && ! str_ends_with($trimmed, '[');

        return $trimmed.($needsComma ? ',' : '')."\n".$line.substr($code, $at);
    }
}
