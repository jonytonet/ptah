<?php

declare(strict_types=1);

namespace Ptah\Support;

/**
 * The single normaliser for select options, in the one shape the runtime reads.
 *
 * ── The shape ─────────────────────────────────────────────────────────────
 * `colsSelect` is a map of **label => value**. Both places that render a select
 * build their option list the same way:
 *
 *     array_map(fn($l, $v) => ['value' => $v, 'label' => $l],
 *         array_keys($col['colsSelect']), array_values($col['colsSelect']))
 *
 * — the modal form (`_modal-form.blade.php`) and both branches of the filter
 * panel (`_filter-panel.blade.php`). That map is therefore the contract, and
 * anything that writes options has to produce it.
 *
 * ── Why this class exists ─────────────────────────────────────────────────
 * Nothing did. Three writers each got it wrong differently:
 *
 *   ColumnParser   stored the RAW STRING under the right key (`colsSelect`).
 *                  `collect("Aberto|open,Fechado|closed")` wraps a scalar into
 *                  `[0 => "…"]`, so the view rendered exactly one option,
 *                  labelled `0`, whose value was the whole unparsed string.
 *   FilterParser   stored the raw string under the WRONG key (`options`), which
 *                  the filter panel never reads — so a `--filter=` select was
 *                  silently empty.
 *   ColumnWizard   never asked for options at all (see below).
 *
 * Same family as the `--style=` and `--filter=` divergences: several writers, one
 * reader, and no shared normaliser between them. This is that normaliser.
 *
 * ── Accepted input ───────────────────────────────────────────────────────
 * The two forms the docs already promised are honoured rather than replaced —
 * ratifying the de-facto contract, as with `colsSelect` itself:
 *
 *   "open,in_progress,resolved"          bare list  (docs/BaseCrud.md)
 *   "active:Active,inactive:Inactive"    value:label (docs/Commands.md)
 *
 * A bare entry becomes its own value with a humanised label, so
 * `in_progress` shows as "In Progress" rather than as a snake_case token.
 *
 * `|` is deliberately NOT accepted here even though `badges=` uses it: there it
 * means `value|color|label`, and having one separator mean two different orders
 * in two modifiers of the same command is how this family of bugs starts.
 */
class SelectOptions
{
    /**
     * @param  mixed  $raw  a definition string, an already-normalised map, or a plain list
     * @return array<string, string> label => value
     */
    public static function normalize(mixed $raw): array
    {
        if (is_array($raw)) {
            return self::fromArray($raw);
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $options = [];

        foreach (explode(',', $raw) as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            // `value:Label` — only the FIRST colon splits, so a label may
            // contain one ("open:Aberto: urgente" keeps the rest of the label).
            if (str_contains($entry, ':')) {
                [$value, $label] = array_map('trim', explode(':', $entry, 2));

                if ($value !== '' && $label !== '') {
                    $options[$label] = $value;

                    continue;
                }
            }

            $options[LabelHumanizer::make($entry)] = $entry;
        }

        return $options;
    }

    /**
     * An array can arrive in three shapes, and telling them apart matters: a
     * config saved by the visual editor is already a label => value map, while
     * a host writing config by hand may pass a plain list.
     *
     * @param  array<mixed>  $raw
     * @return array<string, string>
     */
    private static function fromArray(array $raw): array
    {
        $options = [];

        foreach ($raw as $key => $value) {
            // A list: [0 => 'open', 1 => 'closed'] — the value is the value.
            if (is_int($key)) {
                if (is_array($value)) {
                    // [['value' => 'open', 'label' => 'Aberto'], …] — the shape
                    // the views build for <x-forge-select>, sometimes fed back.
                    $v = $value['value'] ?? null;
                    $l = $value['label'] ?? null;

                    if ($v !== null) {
                        $options[(string) ($l ?? LabelHumanizer::make((string) $v))] = (string) $v;
                    }

                    continue;
                }

                $options[LabelHumanizer::make((string) $value)] = (string) $value;

                continue;
            }

            // Already label => value.
            if (is_scalar($value)) {
                $options[(string) $key] = (string) $value;
            }
        }

        return $options;
    }
}
