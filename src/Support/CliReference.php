<?php

declare(strict_types=1);

namespace Ptah\Support;

use Ptah\Commands\Config\Parsers\ColumnParser;
use Ptah\Enums\CrudConfigEnums;

/**
 * The `ptah:config` vocabulary, answered from the code that parses it.
 *
 * The question an agent asks most while building a screen is "what is the
 * option for X?" — and the answer lived in `docs/BaseCrud.md` (~29k tokens) and
 * `docs/Configuration.md` (~30k tokens). One lookup could cost the price of the
 * whole screen. `ptah:docs <topic>` answers it in a few hundred tokens.
 *
 * Everything that CAN come from a constant does: column types, renderers,
 * modifiers, the option → config-key map, operators, action and join types,
 * the mask registry — including masks the HOST registered, which no static
 * document can know. So the answer cannot drift from the parser the way the
 * prose docs did (the invented `badgeMap=`, `sortable=true`, `width=` of the
 * last releases were all docs disagreeing with the code).
 *
 * The one part a constant cannot carry is the FORMAT line of each option. Each
 * topic therefore ships a working example, and `CliReferenceTest` runs every
 * example through the real parser: a format line that lies breaks the suite.
 */
final class CliReference
{
    /**
     * @return list<string>
     */
    public static function topics(): array
    {
        return ['column', 'filter', 'style', 'action', 'join', 'mask'];
    }

    /**
     * @return array<string, mixed>|null null for an unknown topic
     */
    public static function topic(string $name): ?array
    {
        return match ($name) {
            'column' => self::column(),
            'filter' => self::filter(),
            'style' => self::style(),
            'action' => self::action(),
            'join' => self::join(),
            'mask' => self::mask(),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function column(): array
    {
        return [
            'flag' => '--column',
            'format' => 'field:type[:modifier...][:key=value...]',
            'types' => CrudConfigEnums::COLUMN_TYPES,
            'modifiers' => ColumnParser::MODIFIERS,
            'options' => ColumnParser::KEY_MAP,
            'structured' => [
                'validation' => 'rule|rule — e.g. required|email',
                'options' => 'value:Label,value:Label — a select\'s choices',
                'badges' => 'value|color|label,... — a select with only badges derives its options',
                'sd_array_search' => 'col,col',
            ],
            'renderers' => CrudConfigEnums::RENDERERS,
            'notes' => [
                'Modifiers are BARE: `:sortable`, never `sortable=true` (that stores a key nothing reads).',
                'Search covers every text column automatically — there is no searchable option.',
                'A full config key (`colsMinWidth=120px`) is accepted as-is.',
                'An unknown option is stored AND warned about; the warning names the likely intent.',
            ],
            'example' => 'status:select:label=Status:renderer=badge:badges=active|green|Ativo,inactive|red|Inativo',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function filter(): array
    {
        return [
            'flag' => '--filter',
            'format' => 'field:type[:key=value...]',
            'types' => CrudConfigEnums::FILTER_TYPES,
            'options' => [
                'label' => 'shown in the panel',
                'operator' => 'default operator (see operators)',
                'options' => 'value:Label,... for a select filter',
                'whereHas' => 'relation to filter through',
                'field_relation' => 'column on the related model',
                'aggregate' => 'sum|count|… for a HAVING filter',
            ],
            'operators' => CrudConfigEnums::OPERATORS,
            'example' => 'status:select:label=Status:operator==:options=active:Ativo,inactive:Inativo',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function style(): array
    {
        return [
            'flag' => '--style',
            'format' => 'field:condition:value:css',
            'conditions' => StyleRule::CONDITIONS,
            'notes' => [
                'The css segment is last and may contain `:` and `;`.',
                'The css may use `{{column}}` placeholders — only a safe value (hex colour, keyword) is substituted.',
                '`*` as the condition applies to every row.',
            ],
            'example' => 'is_active:==:0:background:#FEF2F2;color:#B91C1C;',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function action(): array
    {
        return [
            'flag' => '--action',
            'format' => 'name:type:value[:key=value...]',
            'types' => CrudConfigEnums::ACTION_TYPES,
            'colors' => CrudConfigEnums::ACTION_COLORS,
            'options' => [
                'icon' => 'Boxicons class, e.g. bx-check',
                'color' => 'see colors',
                'permission' => 'gate or ptah permission that shows the action',
            ],
            'example' => 'approve:livewire:approve(%id%):icon=bx-check:color=success',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function join(): array
    {
        return [
            'flag' => '--join',
            'format' => 'type:table:first=second[:key=value...]',
            'types' => CrudConfigEnums::JOIN_TYPES,
            'options' => [
                'select' => 'col,col or `col as alias`',
                'where' => 'table.col=value',
                'distinct' => 'true|false',
            ],
            'example' => 'left:suppliers:products.supplier_id=suppliers.id:select=name',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function mask(): array
    {
        return [
            'flag' => '--column',
            'format' => 'field:type:mask=<name>',
            // A partir do registro em runtime: inclui as mascaras que o HOST
            // registrou (PtahMask::define / config/ptah-masks.php), que nenhum
            // documento estatico conhece.
            'masks' => PtahMask::names(),
            'transforms' => CrudConfigEnums::MASK_TRANSFORMS,
            'notes' => [
                'Register a host mask with PtahMask::define() or config/ptah-masks.php.',
            ],
            'example' => 'document:text:mask=digits',
        ];
    }

    /**
     * A topic as compact text — the default output, sized for an agent's
     * context rather than a reader's screen.
     *
     * @param  array<string, mixed>  $topic
     */
    public static function render(string $name, array $topic): string
    {
        $out = ["{$name} — {$topic['flag']}=\"{$topic['format']}\""];

        foreach ($topic as $key => $value) {
            if (in_array($key, ['flag', 'format', 'example', 'notes'], true)) {
                continue;
            }

            if (is_array($value) && ! array_is_list($value)) {
                $pairs = [];

                foreach ($value as $k => $v) {
                    $pairs[] = "{$k}→{$v}";
                }

                $out[] = "{$key}: ".implode(', ', $pairs);

                continue;
            }

            $out[] = "{$key}: ".implode(', ', array_map('strval', (array) $value));
        }

        foreach ($topic['notes'] ?? [] as $note) {
            $out[] = "• {$note}";
        }

        $out[] = "example: {$topic['flag']}=\"{$topic['example']}\"";

        return implode("\n", $out);
    }
}
