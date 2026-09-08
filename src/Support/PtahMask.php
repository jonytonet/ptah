<?php

declare(strict_types=1);

namespace Ptah\Support;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * The registry of input masks: named, declarative, and defined by the HOST.
 *
 * ── Why the host and not the package ─────────────────────────────────────
 *
 * `CrudConfigEnums::MASKS` has shipped `cpf`, `cnpj`, `rg`, `pis`, `ncm`,
 * `ean13`, `cep` and `plate` since long before this class. All of them are
 * Brazilian, and none of them ever did anything: the validator accepted the
 * name, the form implemented only `money_brl` and `uppercase`, and a column
 * configured `colsMask: "cpf"` rendered a plain text input. So the package was
 * carrying the maintenance liability of a country's document formats while
 * delivering none of the behaviour.
 *
 * And those formats change. Brazil's CNPJ became alphanumeric in 2026, which
 * would have been a breaking change inside a package used outside Brazil, on a
 * release cadence that has nothing to do with the law that moved.
 *
 * So the package ships only masks that are not anyone's law — `digits` and
 * `decimal` — and the host declares the rest, either in `config/ptah-masks.php`
 * or through `PtahMask::define()` from a service provider. `colsMask` keeps
 * referring to a mask by name, so nothing in a crud_config changes.
 *
 * ── A definition ─────────────────────────────────────────────────────────
 *
 *   PtahMask::define('cpf', [
 *       'pattern' => '000.000.000-00',
 *       'store'   => 'digits',
 *   ]);
 *
 *   // 2026's alphanumeric CNPJ: twelve alphanumeric positions, then two
 *   // numeric check digits, punctuated as before. Written out in code rather
 *   // than in this comment because the pattern contains the two characters
 *   // that would close this docblock.
 *   PtahMask::define('cnpj', [
 *       'pattern' => $cnpjPattern,   // AA.AAA.AAA(slash)AAAA-00, with * for each A
 *       'store'   => 'alnum',
 *   ]);
 *
 *   // A mask can carry more than one shape, and the longest one that the input
 *   // fills wins — which is the only way a phone field is usable in a country
 *   // that has both eight- and nine-digit numbers.
 *   PtahMask::define('phone', [
 *       'pattern' => ['(00) 0000-0000', '(00) 00000-0000'],
 *       'store'   => 'digits',
 *   ]);
 *
 * Pattern tokens: `0` a digit, `A` a letter, `*` a letter or digit. Every other
 * character is a literal the mask inserts.
 *
 * `store` says what reaches the database. The named rules are `digits`, `alnum`,
 * `upper`, `lower`, `trim`, `decimal` and `raw`. `define()` also accepts a
 * closure — `config/ptah-masks.php` cannot, because `php artisan config:cache`
 * serialises that file and a closure is not serialisable. That asymmetry is
 * deliberate and worth knowing before choosing where to put a definition.
 */
final class PtahMask
{
    /**
     * The masks the package itself ships — and the list is short on purpose.
     *
     * Neither of these belongs to a country or a statute: "keep only the
     * digits" and "one decimal separator" are true everywhere. Anything with a
     * check digit or a legal format is the host's.
     *
     * @var array<string, array<string, mixed>>
     */
    private const BUILT_IN = [
        'digits' => ['pattern' => null, 'store' => 'digits', 'inputmode' => 'numeric'],
        'decimal' => ['pattern' => null, 'store' => 'decimal', 'inputmode' => 'decimal'],
    ];

    /**
     * Masks the package still implements for compatibility.
     *
     * `money_brl` and `uppercase` are the two names that actually worked before
     * the registry existed, and hosts have them in live crud_configs. They keep
     * their own hand-written controls in the form rather than going through the
     * pattern engine, so they are listed here to be RESOLVABLE, not to be
     * re-implemented. New configs should prefer `decimal` and `upper`.
     *
     * @var list<string>
     */
    public const LEGACY = ['money_brl', 'uppercase'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $resolved = null;

    /** @var array<string, array<string, mixed>> */
    private static array $runtime = [];

    /** @var array<string, array<string, mixed>> */
    private static array $presets = [];

    /**
     * Registers or replaces a mask.
     *
     * @param  array<string, mixed>  $definition
     */
    public static function define(string $name, array $definition): void
    {
        $name = self::key($name);

        if ($name === '') {
            return;
        }

        self::$runtime[$name] = $definition;
        self::$resolved = null;
    }

    /**
     * Activates a shipped set of masks (see MaskPresets).
     *
     * Weaker than `config/ptah-masks.php` and than `define()`, so a host can
     * enable `br` and still override the one mask whose format moved before the
     * next ptah release.
     *
     * Returns false for a name this version does not ship, rather than failing:
     * a preset that disappears must not take a boot sequence down with it.
     */
    public static function preset(string $name): bool
    {
        $set = MaskPresets::get($name);

        if ($set === null) {
            Log::warning('ptah: preset de mascara desconhecido.', [
                'preset' => $name,
                'disponiveis' => MaskPresets::names(),
            ]);

            return false;
        }

        foreach ($set as $maskName => $definition) {
            self::$presets[self::key((string) $maskName)] = $definition;
        }

        self::$resolved = null;

        return true;
    }

    /**
     * @param  array<string, array<string, mixed>>  $masks
     */
    public static function defineMany(array $masks): void
    {
        foreach ($masks as $name => $definition) {
            if (is_string($name) && is_array($definition)) {
                self::define($name, $definition);
            }
        }
    }

    /**
     * Forgets runtime definitions. For tests, and for nothing else.
     */
    public static function flush(): void
    {
        self::$runtime = [];
        self::$presets = [];
        self::$resolved = null;
    }

    /**
     * Every mask this application knows, normalised.
     *
     * Precedence, weakest first: the package's built-ins, then
     * `config/ptah-masks.php`, then `PtahMask::define()`. A host that wants
     * `decimal` to mean something else says so and is obeyed — the built-ins are
     * a floor, not a fence.
     *
     * @return array<string, array{name: string, patterns: list<string>, store: string|Closure, placeholder: string, inputmode: string, maxlength: int|null}>
     */
    public static function all(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $raw = self::BUILT_IN;

        // Presets nomeados em `ptah-masks.presets` entram aqui, e nao no boot do
        // provider, para que `config:cache` e um host que troca a chave em
        // runtime (teste, tenant) sejam vistos do mesmo jeito.
        foreach ((array) config('ptah-masks.presets', []) as $presetName) {
            if (is_string($presetName)) {
                $set = MaskPresets::get($presetName);

                if ($set === null) {
                    Log::warning('ptah: preset de mascara desconhecido em ptah-masks.presets.', [
                        'preset' => $presetName,
                        'disponiveis' => MaskPresets::names(),
                    ]);

                    continue;
                }

                foreach ($set as $maskName => $definition) {
                    $raw[self::key((string) $maskName)] = $definition;
                }
            }
        }

        foreach (self::$presets as $name => $definition) {
            $raw[$name] = $definition;
        }

        foreach ((array) config('ptah-masks', []) as $name => $definition) {
            // `presets` e chave de controle do arquivo, nao uma mascara chamada
            // "presets" — sem esta linha ela seria normalizada como definicao e
            // rejeitada, o que funciona por acidente e nao por intencao.
            if ($name === 'presets') {
                continue;
            }

            if (is_string($name) && is_array($definition)) {
                $raw[self::key($name)] = $definition;
            }
        }

        foreach (self::$runtime as $name => $definition) {
            $raw[$name] = $definition;
        }

        $out = [];

        foreach ($raw as $name => $definition) {
            $normalised = self::normalize((string) $name, $definition);

            if ($normalised !== null) {
                $out[(string) $name] = $normalised;
            }
        }

        return self::$resolved = $out;
    }

    /**
     * One mask, or null when the name is not registered.
     *
     * @return array{name: string, patterns: list<string>, store: string|Closure, placeholder: string, inputmode: string, maxlength: int|null}|null
     */
    public static function get(?string $name): ?array
    {
        $name = self::key((string) $name);

        return $name === '' ? null : (self::all()[$name] ?? null);
    }

    public static function has(?string $name): bool
    {
        return self::get($name) !== null;
    }

    /**
     * The names a config may reference: the registry plus the legacy pair.
     *
     * The validator uses this instead of a frozen constant, which is the point
     * of the whole change — a host that defines `cpf` gets `colsMask: "cpf"`
     * accepted, and one that does not gets told the name is unknown instead of
     * getting a text input that silently does nothing.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_values(array_unique([
            ...array_keys(self::all()),
            ...self::LEGACY,
        ]));
    }

    /**
     * What should reach the database for this value.
     *
     * An unregistered mask returns the value untouched: a name nobody defined
     * must not quietly rewrite data.
     */
    public static function store(?string $name, mixed $value): mixed
    {
        $mask = self::get($name);

        if ($mask === null) {
            return $value;
        }

        $rule = $mask['store'];

        if ($rule instanceof Closure) {
            return $rule($value);
        }

        return self::applyNamedStore((string) $rule, $value);
    }

    /**
     * Formats a raw value the way the mask displays it.
     *
     * Server-side twin of what the browser does while typing — used to seed the
     * input when editing a record whose column holds the stripped value, so an
     * existing CPF opens as `000.000.000-00` and not as eleven bare digits.
     */
    public static function format(?string $name, mixed $value): string
    {
        $mask = self::get($name);
        $raw = (string) ($value ?? '');

        if ($mask === null || $mask['patterns'] === [] || $raw === '') {
            return $raw;
        }

        $chars = preg_split('//u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return self::applyPattern(self::pick($mask['patterns'], $raw), $chars);
    }

    /**
     * The SMALLEST pattern that still holds what was typed.
     *
     * Two versions of this were wrong, and both were caught here rather than in
     * a browser. The first counted every character of the input, punctuation
     * included, so a pasted `(11) 3322-4455` looked like fifteen characters and
     * overflowed a ten-slot pattern. The second returned the first fit while the
     * list is ordered LARGEST first, so it always chose the longest shape — a
     * ten-digit phone came out as `(11) 33224-455`, formatted against the
     * nine-digit pattern.
     *
     * Only slot-fillable characters count, and the loop keeps overwriting, so on
     * a descending list it ends on the smallest pattern that fits.
     *
     * @param  list<string>  $patterns
     */
    private static function pick(array $patterns, string $raw): string
    {
        $tokens = (int) preg_match_all('/[A-Za-z0-9]/u', $raw);
        $chosen = $patterns[0];

        foreach ($patterns as $pattern) {
            if (self::capacity($pattern) >= $tokens) {
                $chosen = $pattern;
            }
        }

        return $chosen;
    }

    // ─────────────────────────────────────────
    // Internals
    // ─────────────────────────────────────────

    private static function key(string $name): string
    {
        return strtolower(trim($name));
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{name: string, patterns: list<string>, store: string|Closure, placeholder: string, inputmode: string, maxlength: int|null}|null
     */
    private static function normalize(string $name, array $definition): ?array
    {
        $patterns = $definition['pattern'] ?? $definition['patterns'] ?? null;

        if (is_string($patterns)) {
            $patterns = [$patterns];
        }

        $patterns = array_values(array_filter(
            array_map(static fn ($p): string => is_string($p) ? $p : '', (array) $patterns),
            static fn (string $p): bool => $p !== ''
        ));

        // Longest capacity first: `format()` and the browser both take the first
        // pattern that fits, so the order IS the rule.
        usort($patterns, static fn (string $a, string $b): int => self::capacity($b) <=> self::capacity($a));

        $store = $definition['store'] ?? 'raw';

        if (! $store instanceof Closure && ! is_string($store)) {
            $store = 'raw';
        }

        if (is_string($store) && ! in_array($store, self::storeRules(), true)) {
            // A store rule nobody implements would silently pass data through
            // while looking like it was cleaning it.
            return null;
        }

        $placeholder = $definition['placeholder'] ?? ($patterns[0] ?? '');

        return [
            'name' => $name,
            'patterns' => $patterns,
            'store' => $store,
            'placeholder' => is_string($placeholder) ? $placeholder : '',
            'inputmode' => is_string($definition['inputmode'] ?? null)
                ? $definition['inputmode']
                : self::inputmodeFor($patterns),
            'maxlength' => $patterns === [] ? null : max(array_map('mb_strlen', $patterns)),
        ];
    }

    /**
     * @return list<string>
     */
    public static function storeRules(): array
    {
        return ['digits', 'alnum', 'upper', 'lower', 'trim', 'decimal', 'raw'];
    }

    private static function applyNamedStore(string $rule, mixed $value): mixed
    {
        $s = (string) ($value ?? '');

        return match ($rule) {
            'digits' => preg_replace('/\D+/u', '', $s) ?? '',
            'alnum' => preg_replace('/[^A-Za-z0-9]+/u', '', $s) ?? '',
            'upper' => mb_strtoupper(trim($s)),
            'lower' => mb_strtolower(trim($s)),
            'trim' => trim($s),
            // The last separator wins, so both "1.234,56" and "1,234.56" land on
            // the same number instead of one of them becoming 123456.
            'decimal' => self::toDecimal($s),
            default => $value,
        };
    }

    private static function toDecimal(string $value): float
    {
        $clean = preg_replace('/[^0-9.,\-]/', '', $value) ?? '';

        if ($clean === '' || $clean === '-') {
            return 0.0;
        }

        $dot = strrpos($clean, '.');
        $comma = strrpos($clean, ',');

        if ($dot !== false && $comma !== false) {
            return $comma > $dot
                ? (float) str_replace(['.', ','], ['', '.'], $clean)
                : (float) str_replace(',', '', $clean);
        }

        if ($comma !== false) {
            // Two decimals or fewer after a lone comma reads as a decimal mark;
            // three reads as a thousands separator ("1,000").
            return strlen(substr($clean, $comma + 1)) <= 2
                ? (float) str_replace(',', '.', $clean)
                : (float) str_replace(',', '', $clean);
        }

        return (float) $clean;
    }

    /** How many input characters a pattern can hold. */
    private static function capacity(string $pattern): int
    {
        return (int) preg_match_all('/[0A*]/', $pattern);
    }

    /**
     * @param  list<string>  $chars
     */
    private static function applyPattern(string $pattern, array $chars): string
    {
        $out = '';
        $i = 0;
        $tokens = preg_split('//u', $pattern, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (! in_array($token, ['0', 'A', '*'], true)) {
                // A literal is only emitted once there is something after it to
                // hold, so a half-typed value does not end in a dangling dot.
                if ($i < count($chars)) {
                    $out .= $token;
                }

                continue;
            }

            // Skip input that cannot fill this slot rather than dropping the
            // slot: a letter pasted into a digit position is discarded, and the
            // digit that follows lands where it belongs.
            while ($i < count($chars) && ! self::fits($token, $chars[$i])) {
                $i++;
            }

            if ($i >= count($chars)) {
                break;
            }

            $out .= $chars[$i];
            $i++;
        }

        return $out;
    }

    private static function fits(string $token, string $char): bool
    {
        return match ($token) {
            '0' => preg_match('/^\d$/u', $char) === 1,
            'A' => preg_match('/^[A-Za-z]$/u', $char) === 1,
            '*' => preg_match('/^[A-Za-z0-9]$/u', $char) === 1,
            default => false,
        };
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function inputmodeFor(array $patterns): string
    {
        if ($patterns === []) {
            return 'text';
        }

        // A pattern with no letter slot is a number pad on a phone; one with
        // letters is not, and forcing the numeric keyboard there would make the
        // field impossible to fill on a touch device.
        foreach ($patterns as $pattern) {
            if (preg_match('/[A*]/', $pattern) === 1) {
                return 'text';
            }
        }

        return 'numeric';
    }
}
