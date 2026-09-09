<?php

namespace Ptah\Commands\Config\Parsers;

use Illuminate\Support\Str;
use Ptah\Support\LabelHumanizer;
use Ptah\Support\SelectOptions;

class ColumnParser
{
    /**
     * Transient key listing which config keys the definition actually set.
     *
     * Stripped by ConfigCommand::upsertColumn() before anything is persisted —
     * it never reaches crud_configs.
     */
    public const EXPLICIT_KEYS = '__explicit';

    /**
     * Transient key listing option names the DSL does not know.
     *
     * Stripped by ConfigCommand::upsertColumn(), which warns about them first.
     * Like EXPLICIT_KEYS, it never reaches crud_configs.
     */
    public const UNKNOWN_KEYS = '__unknown';

    /**
     * Keys the definition DSL parses itself, without going through KEY_MAP.
     *
     * Their values are structures, not scalars, so each has its own parser.
     */
    public const SPECIAL_KEYS = [
        'validation',
        'options',
        'badges',
        // Este nao esta no KEY_MAP: existe apenas no ramo especial.
        'sd_array_search',
    ];

    /**
     * The option vocabulary: `key=` shortcut => the config key it writes.
     *
     * Was a local array inside `applyKeyValue()`. Lifted out because it is the
     * only definition of what the CLI understands, and an unknown key used to
     * be written to the config verbatim and read by nobody — `sortable=true`
     * stored a dead `sortable` key while the real switch is the bare
     * `sortable` modifier, and `searchable=true` stored a key that has no
     * runtime at all. Both appeared in documented examples for releases.
     */
    public const KEY_MAP = [
        'label' => 'colsNomeLogico',
        'placeholder' => 'colsPlaceholder',
        'align' => 'colsAlign',
        'renderer' => 'colsRenderer',
        'mask' => 'colsMask',
        'relation' => 'colsRelacao',
        'relation_display' => 'colsRelacaoExibe',
        'relation_nested' => 'colsRelacaoNested',
        'min_width' => 'colsMinWidth',
        'cell_style' => 'colsCellStyle',
        'cell_class' => 'colsCellClass',
        'cell_icon' => 'colsCellIcon',
        'source' => 'colsSource',
        'method' => 'colsMetodoCustom',
        'method_raw' => 'colsMetodoRaw',
        'order_by' => 'colsOrderBy',
        'permission' => 'colsPermission',

        // SearchDropdown
        'sd_mode' => 'colsSDMode',
        'sd_model' => 'colsSDModel',
        'sd_service' => 'colsSDService',
        'sd_service_method' => 'colsSDServiceMethod',
        'sd_value' => 'colsSDValor',
        'sd_label' => 'colsSDLabel',
        'sd_label_two' => 'colsSDLabelTwo',
        'sd_order_by' => 'colsSDOrder',
        'sd_limit' => 'colsSDLimit',
        'sd_placeholder' => 'colsSDPlaceholder',
        'sd_filters' => 'colsSDFilters',
        'sd_init_with_data' => 'colsSDInitWithData',
        'sd_label_three' => 'colsSDLabelThree',
        'sd_mask_one' => 'colsSDMaskOne',
        'sd_mask_two' => 'colsSDMaskTwo',
        'sd_mask_three' => 'colsSDMaskThree',
        'sd_start_list' => 'colsSDStartList',
        'sd_depends_on' => 'colsSDDependsOn',
        'sd_filter_column' => 'colsSDFilterColumn',

        // Renderer specific
        'currency' => 'colsRendererCurrency',
        'decimals' => 'colsRendererDecimals',
        'bool_true' => 'colsRendererBoolTrue',
        'bool_false' => 'colsRendererBoolFalse',
        'link_template' => 'colsRendererLinkTemplate',
        'link_label' => 'colsRendererLinkLabel',
        'link_new_tab' => 'colsRendererLinkNewTab',
        'image_width' => 'colsRendererImageWidth',
        'image_height' => 'colsRendererImageHeight',
        'upload_path' => 'colsUploadPath',
        'upload_max_size' => 'colsUploadMaxSize',
        'upload_allowed_types' => 'colsUploadAllowedTypes',
        'max_chars' => 'colsRendererMaxChars',
        'locale' => 'colsRendererLocale',
        'progress_max' => 'colsRendererMax',
        'progress_color' => 'colsRendererColor',
        'rating_max' => 'colsRendererMax',
        'duration_unit' => 'colsRendererDurationUnit',
        'qr_size' => 'colsRendererQrSize',

        // Mask
        'mask_regex' => 'colsMaskRegex',
        'mask_transform' => 'colsMaskTransform',

        // Totalizer
        'totalizer' => 'totalizadorType',
        'totalizer_format' => 'totalizadorFormat',
        'totalizer_label' => 'totalizadorLabel',
        'totalizer_enabled' => 'totalizadorEnabled',
    ];

    /**
     * The bare, closed list of boolean modifiers.
     *
     * One list, read by three places: `applyModifier()` applies them,
     * `tokenize()` needs them to tell a modifier from the tail of a value, and
     * the documentation guard needs them to tell an option from a typo.
     */
    public const MODIFIERS = [
        'required',
        'nullable',
        'readonly',
        'hidden',
        'sortable',
        'filterable',
        'not_filterable',
    ];

    /**
     * Parse column definition string
     *
     * Format: field:type:modifier1:modifier2:option1=value1:option2=value2
     * Example: name:text:required:label=Product Name:placeholder=Enter name
     */
    public function parse(string $definition): array
    {
        $parts = $this->tokenize($definition);
        $field = array_shift($parts);
        $type = array_shift($parts) ?? 'text';

        $config = [
            'colsNomeFisico' => $field,
            'colsNomeLogico' => LabelHumanizer::make($field),
            'colsTipo' => $type,
            'colsAlign' => 'text-start',
            'colsGravar' => true,
            'colsRequired' => false,
            'colsIsFilterable' => true,
            'colsVisibleList' => true,
            'colsEditableForm' => true,
        ];

        // Which keys this definition SET, as opposed to the defaults seeded
        // above. ConfigCommand::upsertColumn() merges only these when a column
        // for the field already exists: without the distinction, the humanised
        // `colsNomeLogico` default silently overwrote a label the user had set
        // in the visual editor every time they ran `--column=` to change
        // something else.
        $before = $config;

        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $config = $this->applyKeyValue($config, $key, $value);
            } else {
                $config = $this->applyModifier($config, $part);
            }
        }

        // `select` + `badges=` sem `options=` era um beco sem saída: o
        // ConfigSchemaValidator exige colsSelect para todo colsTipo `select`
        // (ConfigSchemaValidator.php:162), então a definição morria com
        // "requires colsSelect to be configured" e nada era gravado — e é
        // exatamente assim que os exemplos da documentação estão escritos
        // (ptah-development/SKILL.md:431 e :481, KnownLimitations.md:219, esse
        // último apresentado como "CORRECT"). Não havia caminho documentado
        // que funcionasse: quem passasse `options=` também salvava e ficava
        // com o badge cinza, pelo defeito do flip em HasCrudRenderers.
        //
        // As entradas de badge JÁ são pares valor/rótulo, que é precisamente a
        // forma de colsSelect (label => value), então derive uma da outra. Um
        // `options=` explícito continua vencendo: quem quer as opções do form
        // diferentes das cores da listagem escreve as duas.
        if (($config['colsTipo'] ?? '') === 'select'
            && empty($config['colsSelect'])
            && ! empty($config['colsRendererBadges'])) {
            $config['colsSelect'] = self::selectFromBadges($config['colsRendererBadges']);
        }

        // Toda opcao que o DSL nao conhece, para o comando avisar. A gravacao
        // continua acontecendo: um host pode guardar uma chave propria na
        // config e le-la num hook, e tirar isso agora seria quebrar sem
        // necessidade. O que faltava era o aviso — `sortable=true` gravava um
        // `sortable` que nada le (o interruptor e o modificador nu), e
        // `searchable=true` gravava uma chave que nao tem runtime nenhum.
        // Ambos apareciam em exemplos documentados havia releases, e o silencio
        // e o que os manteve la.
        $unknown = [];

        foreach ($parts as $part) {
            if (! str_contains($part, '=')) {
                continue;
            }

            [$key] = explode('=', $part, 2);

            if (! self::knowsKey($key)) {
                $unknown[] = $key;
            }
        }

        $explicit = ['colsNomeFisico', 'colsTipo'];

        foreach ($config as $key => $value) {
            if (! array_key_exists($key, $before) || $before[$key] !== $value) {
                $explicit[] = $key;
            }
        }

        $config[self::EXPLICIT_KEYS] = array_values(array_unique($explicit));

        if ($unknown !== []) {
            $config[self::UNKNOWN_KEYS] = array_values(array_unique($unknown));
        }

        return $config;
    }

    /**
     * The `colsSelect` map implied by a badge list.
     *
     * @param  array<int, mixed>  $badges
     * @return array<string, string> label => value, a forma que colsSelect usa
     */
    public static function selectFromBadges(array $badges): array
    {
        $options = [];

        foreach ($badges as $badge) {
            if (! is_array($badge)) {
                continue;
            }

            $value = (string) ($badge['value'] ?? '');

            if ($value === '') {
                continue;
            }

            $label = trim((string) ($badge['label'] ?? ''));

            $options[$label !== '' ? $label : LabelHumanizer::make($value)] = $value;
        }

        return $options;
    }

    /**
     * Is this a `key=` the DSL understands?
     *
     * Three ways to be known: a shortcut in KEY_MAP, one of the structured
     * SPECIAL_KEYS, or a real config key written out in full — `colsMinWidth=`
     * and `totalizadorLabel=` reach the config untouched, and that escape
     * hatch is deliberate.
     */
    public static function knowsKey(string $key): bool
    {
        return array_key_exists($key, self::KEY_MAP)
            || in_array($key, self::SPECIAL_KEYS, true)
            || str_starts_with($key, 'cols')
            || str_starts_with($key, 'totalizador');
    }

    /**
     * The likeliest thing the writer meant, or null.
     *
     * A modifier written as `key=value` is the mistake worth naming out loud:
     * `sortable=true` looks like it works, and the flag it means is the bare
     * `sortable`. Beyond that, the nearest shortcut within two edits — enough
     * for `min_widht` or `renderr`, not enough to invent a match for
     * `searchable`, which corresponds to nothing.
     */
    public static function suggestionFor(string $key): ?string
    {
        if (in_array($key, self::MODIFIERS, true)) {
            return ':'.$key;
        }

        // O vocabulario e snake_case, e a variante camelCase e o erro mais
        // frequente depois do modificador: `uploadPath`, `uploadMaxSize` e
        // `rendererImageWidth` estavam nos exemplos de Configuration.md e
        // BaseCrud.md. Pontuar as duas formas e o que faz a sugestao acertar o
        // nome em vez de apontar o vizinho.
        $needles = array_unique([
            strtolower($key),
            strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $key)),
        ]);

        $best = null;
        // 3 e o teto: a partir dai o palpite erra mais do que acerta, e
        // `searchable` — que nao corresponde a nada — tem de sair sem sugestao
        // em vez de sair com uma inventada.
        $bestScore = 3;
        $bestGap = PHP_INT_MAX;

        foreach ([...array_keys(self::KEY_MAP), ...self::SPECIAL_KEYS, ...self::MODIFIERS] as $candidate) {
            $lower = strtolower((string) $candidate);

            foreach ($needles as $needle) {
                $score = match (true) {
                    // `width` dentro de `min_width`. Exige quatro caracteres,
                    // senao uma chave curta casaria com meio mapa.
                    strlen($needle) >= 4 && (str_contains($lower, $needle) || str_contains($needle, $lower)) => 0,
                    // `badgeMap` e `badges` compartilham `badge` — perto o
                    // bastante para nomear, longe demais para o levenshtein.
                    self::sharedPrefix($needle, $lower) >= 4 => 1,
                    default => levenshtein($needle, $lower),
                };

                // Empate vai para o candidato de tamanho mais PROXIMO, que e
                // o mais parecido: `rendererImageWidth` contem `renderer` e
                // `image_width`, os dois com score 0, e o mapa lista `renderer`
                // primeiro — sem desempate a sugestao apontava o errado dos
                // dois. "O mais longo" tambem resolveria esse, e estragava
                // `width`, que passava a sugerir `image_width` em vez de
                // `min_width`.
                $gap = abs(strlen($lower) - strlen($needle));

                if ($score < $bestScore || ($score === $bestScore && $gap < $bestGap)) {
                    $best = in_array($candidate, self::MODIFIERS, true) ? ':'.$candidate : $candidate.'=';
                    $bestScore = $score;
                    $bestGap = $gap;
                }
            }
        }

        return $best;
    }

    /**
     * How many leading characters two names share.
     */
    private static function sharedPrefix(string $a, string $b): int
    {
        $limit = min(strlen($a), strlen($b));
        $shared = 0;

        while ($shared < $limit && $a[$shared] === $b[$shared]) {
            $shared++;
        }

        return $shared;
    }

    /**
     * Apply boolean modifiers
     */
    protected function applyModifier(array $config, string $modifier): array
    {
        if (! in_array($modifier, self::MODIFIERS, true)) {
            return $config;
        }

        return match ($modifier) {
            'required' => array_merge($config, ['colsRequired' => true]),
            'nullable' => array_merge($config, ['colsRequired' => false]),
            'readonly' => array_merge($config, ['colsGravar' => false]),
            'hidden' => array_merge($config, ['colsVisibleList' => false]),
            'sortable' => array_merge($config, ['colsOrderBy' => $config['colsNomeFisico']]),
            'filterable' => array_merge($config, ['colsIsFilterable' => true]),
            'not_filterable' => array_merge($config, ['colsIsFilterable' => false]),
            default => $config,
        };
    }

    /**
     * Apply key=value options
     */
    protected function applyKeyValue(array $config, string $key, string $value): array
    {

        $mappedKey = self::KEY_MAP[$key] ?? $key;

        // Special parsing for complex fields
        if ($key === 'validation') {
            $config['colsValidations'] = $this->parseValidations($value);
        } elseif ($key === 'options') {
            $config['colsSelect'] = $this->parseOptions($value);
        } elseif ($key === 'badges') {
            $config['colsRendererBadges'] = $this->parseBadges($value);
        } elseif ($key === 'upload_allowed_types') {
            // Split comma-separated extension list into an array
            $config['colsUploadAllowedTypes'] = array_map('trim', explode(',', $value));
        } elseif ($key === 'sd_array_search') {
            // Split comma-separated column list into an array
            $config['colsSDArraySearch'] = array_map('trim', explode(',', $value));
        } elseif ($mappedKey === 'totalizadorType') {
            $config['totalizadorEnabled'] = true;
            $config['totalizadorType'] = $value;
        } else {
            $config[$mappedKey] = $this->castValue($value);
        }

        return $config;
    }

    /**
     * Parse validation rules
     * Format: email|unique:products,email|maxLength:255|min:0
     */
    protected function parseValidations(string $value): array
    {
        return array_map('trim', explode('|', $value));
    }

    /**
     * Parse select options
     * Format: active:Active,inactive:Inactive,pending:Pending
     */
    /**
     * @return array<string, string> label => value
     */
    protected function parseOptions(string $value): array
    {
        // Used to `return $value` — the raw string — under the right key. The
        // views do `collect($col['colsSelect'])->map(...)`, and collect() on a
        // scalar yields `[0 => "…"]`, so a select configured through the CLI
        // rendered exactly ONE option, labelled `0`, whose value was the whole
        // unparsed definition. See Ptah\Support\SelectOptions.
        return SelectOptions::normalize($value);
    }

    /**
     * Parse badge configurations
     * Format: active|green|Ativo,inactive|gray|Inativo,pending|yellow|Pendente
     * Note: use '|' as separator within each badge entry (not ':') to avoid
     * collision with the field:type:modifier definition syntax.
     */
    protected function parseBadges(string $value): array
    {
        $badges = [];
        foreach (explode(',', $value) as $badge) {
            $parts = explode('|', $badge, 3);
            if (count($parts) >= 2) {
                $badges[] = [
                    'value' => $parts[0],
                    'color' => $parts[1],
                    'label' => $parts[2] ?? Str::title($parts[0]),
                ];
            }
        }

        return $badges;
    }

    /**
     * Cast string value to appropriate type
     */
    protected function castValue(string $value): mixed
    {
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if (is_numeric($value)) {
            return is_float($value + 0) ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * Smart tokenizer: splits field:type:modifier:key=value:key=value
     * preserving ':' that appear inside the VALUE side of key=value pairs.
     *
     * Example: "status:select:options=active:Active,inactive:Inactive:renderer=badge"
     * → ['status', 'select', 'options=active:Active,inactive:Inactive', 'renderer=badge']
     */
    protected function tokenize(string $definition): array
    {
        $raw = explode(':', $definition);
        $result = [];
        $buffer = null;

        foreach ($raw as $i => $part) {
            // First two tokens (field, type) are always standalone
            if ($i < 2) {
                $result[] = $part;

                continue;
            }

            if (str_contains($part, '=')) {
                // A new key=value pair — flush any buffered value first
                if ($buffer !== null) {
                    $result[] = $buffer;
                }
                $buffer = $part;
            } elseif ($buffer !== null && ! in_array($part, self::MODIFIERS, true)) {
                // No '=' and we have an open buffer → this fragment is a
                // continuation of the previous value (value contained ':')
                $buffer .= ':'.$part;
            } elseif ($buffer !== null) {
                // Um modificador conhecido fecha o valor aberto em vez de
                // entrar nele. Sem esta ressalva,
                // `price:number:renderer=money:sortable` — o exemplo de
                // ptah-development/SKILL.md:430 — virava
                // `renderer = "money:sortable"`, um renderer inexistente, e o
                // ConfigSchemaValidator recusava a coluna inteira. A ambiguidade
                // e real (um valor PODE conter ':', e e por isso que o buffer
                // existe), mas a lista de modificadores e fechada e nenhuma
                // delas e um rotulo plausivel.
                $result[] = $buffer;
                $buffer = null;
                $result[] = $part;
            } else {
                // Standalone modifier (e.g. 'required', 'hidden')
                $result[] = $part;
            }
        }

        if ($buffer !== null) {
            $result[] = $buffer;
        }

        return $result;
    }
}
