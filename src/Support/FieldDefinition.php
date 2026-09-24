<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Value object representing the definition of a single field.
 *
 * Input string format (--fields option):
 *   name:type[(params)][:nullable][:unique][:surname=Label][:default(val)]
 *
 * Examples:
 *   name:string
 *   price:decimal(10,2):nullable
 *   status:enum(active|inactive|pending)
 *   is_active:boolean:default(true)
 *   qty:integer:default(0)
 *   email:string:unique
 *   user_id:unsignedBigInteger        ← raw column + auto index, NO constrained()
 *   user_id:foreignId                 ← constrained FK with cascade (convention-based)
 *   city:string:surname=City
 *   price:decimal(10,2):nullable:surname=Price
 */
readonly class FieldDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
        public bool $unique,
        public int $precision,    // decimal: total digits
        public int $scale,        // decimal: decimal places
        public array $enumValues,   // enum: ['active', 'inactive']
        public string $label = '',    // display label in BaseCrud (surname)
        public bool $hasDefault = false, // whether a ->default() should be emitted
        public ?string $defaultValue = null,  // default value literal: 'true', '0', 'active'
        public ?int $length = null,  // string/char: declared length — string(60), char(2)
    ) {}

    /**
     * Type for Eloquent's $casts.
     */
    public function castType(): string
    {
        return match (true) {
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger']) => 'integer',
            $this->type === 'decimal' => "decimal:{$this->scale}",
            in_array($this->type, ['float', 'double']) => 'float',
            $this->type === 'boolean' => 'boolean',
            $this->type === 'date' => 'date',
            in_array($this->type, ['datetime', 'timestamp']) => 'datetime',
            $this->type === 'json' => 'array',
            default => 'string',
        };
    }

    /**
     * Returns true when the field should generate a belongsTo relationship in the Model.
     *
     * Includes both `foreignId` (constrained FK in migration) and
     * `unsignedBigInteger`/`bigInteger` ending in `_id` (raw column + auto index,
     * no constrained — the FK constraint is the developer's responsibility).
     *
     * NOTE: in migrationLine(), only `foreignId` generates constrained() automatically.
     */
    public function isForeignKey(): bool
    {
        return str_ends_with($this->name, '_id')
            && in_array($this->type, ['unsignedBigInteger', 'foreignId', 'bigInteger']);
    }

    /**
     * Name without the _id suffix (e.g. business_partner_id → business_partner).
     */
    public function relatedName(): string
    {
        return substr($this->name, 0, -3);
    }

    /**
     * Related model in StudlyCase (e.g. business_partner → BusinessPartner).
     */
    public function relatedModel(): string
    {
        return Str::studly($this->relatedName());
    }

    /**
     * Related table in snake_case plural (e.g. business_partner → business_partners).
     *
     * If the inferred table name matches a known ptah-prefixed table, the prefix
     * is added automatically so the FK points to the right table.
     * e.g.: department_id → departments → ptah_departments
     */
    public function relatedTable(): string
    {
        $table = Str::plural($this->relatedName());

        // Tables that ptah creates with the ptah_ prefix
        $ptahTables = [
            'companies', 'departments', 'roles', 'pages',
            'page_objects', 'role_permissions', 'user_roles', 'permission_audits',
        ];

        return in_array($table, $ptahTables, true) ? 'ptah_'.$table : $table;
    }

    /**
     * Blueprint definition line for migration.
     *
     * FK rules:
     *  - `foreignId` ending in `_id`  → constrained() + cascade/nullOnDelete (automatic)
     *  - `unsignedBigInteger`/`bigInteger` ending in `_id`  → raw column + ->index() only.
     *    The FK constraint is the developer's responsibility: the referenced table
     *    may not yet exist, or its name may differ from the field-name convention.
     */
    public function migrationLine(string $indent = '            '): string
    {
        // Only foreignId triggers constrained() automatically.
        if ($this->type === 'foreignId' && str_ends_with($this->name, '_id')) {
            $line = "\$table->foreignId('{$this->name}')->constrained('{$this->relatedTable()}')->cascadeOnDelete()";
            if ($this->nullable) {
                $line = "\$table->foreignId('{$this->name}')->nullable()->constrained('{$this->relatedTable()}')->nullOnDelete()";
            }

            return $indent.$line.';';
        }

        $line = match ($this->type) {
            // string(60)/char(2): the declared length is honoured, like decimal(p,s).
            // Omitting it silently produced varchar(255) everywhere, which in
            // InnoDB/utf8mb4 costs 1020 bytes per indexed column and overflows the
            // 3072-byte index limit on composite indexes.
            'string' => "\$table->string('{$this->name}'".($this->length !== null ? ", {$this->length}" : '').')',
            'char' => "\$table->char('{$this->name}'".($this->length !== null ? ", {$this->length}" : '').')',
            'text' => "\$table->text('{$this->name}')",
            'longText' => "\$table->longText('{$this->name}')",
            'integer' => "\$table->integer('{$this->name}')",
            'bigInteger' => "\$table->bigInteger('{$this->name}')",
            'unsignedBigInteger' => "\$table->unsignedBigInteger('{$this->name}')",
            'unsignedInteger' => "\$table->unsignedInteger('{$this->name}')",
            'tinyInteger' => "\$table->tinyInteger('{$this->name}')",
            'smallInteger' => "\$table->smallInteger('{$this->name}')",
            'decimal' => "\$table->decimal('{$this->name}', {$this->precision}, {$this->scale})",
            'float' => "\$table->float('{$this->name}')",
            'double' => "\$table->double('{$this->name}')",
            'boolean' => "\$table->boolean('{$this->name}')",
            'date' => "\$table->date('{$this->name}')",
            'datetime', 'timestamp' => "\$table->timestamp('{$this->name}')",
            'json' => "\$table->json('{$this->name}')",
            'enum' => $this->enumMigrationCall(),
            default => "\$table->string('{$this->name}')",
        };

        if ($this->nullable) {
            $line .= '->nullable()';
        }

        if ($this->unique) {
            $line .= '->unique()';
        }

        // Auto-index FK-like columns (unsignedBigInteger/bigInteger ending in _id).
        // Skipped when already ->unique() since unique creates its own index.
        if (! $this->unique
            && str_ends_with($this->name, '_id')
            && in_array($this->type, ['unsignedBigInteger', 'bigInteger'], true)) {
            $line .= '->index()';
        }

        // Emit ->default() when a default value was declared via :default(val) or :default=val.
        if ($this->hasDefault && $this->defaultValue !== null) {
            $val = $this->defaultValue;
            $line .= match (true) {
                in_array(strtolower($val), ['true', 'false', 'null'], true) => "->default({$val})",
                is_numeric($val) => "->default({$val})",
                default => "->default('{$val}')",
            };
        }

        return $indent.$line.';';
    }

    /**
     * Laravel validation rule for creation (store).
     */
    public function validationRuleStore(): string
    {
        return "'{$this->name}' => '{$this->buildRules(isUpdate: false)}',";
    }

    /**
     * Laravel validation rule for update.
     */
    public function validationRuleUpdate(): string
    {
        return "'{$this->name}' => '{$this->buildRules(isUpdate: true)}',";
    }

    /**
     * Tipo PHP para a propriedade do DTO.
     */
    /**
     * A Faker expression that produces a value the migration and the Store
     * rules both accept: within the declared length, inside the enum, a real
     * row for a foreign key. The name refines the type (email, phone, url…)
     * because a factory full of lorem is useless for looking at a screen.
     *
     * @param  string|null  $relatedFqcn  the FK's model when it resolved; a
     *                                    nullable FK without it is null, a required one a TODO
     */
    public function fakerExpression(?string $relatedFqcn = null): string
    {
        if ($this->isForeignKey() || ($this->type === 'foreignId' && str_ends_with($this->name, '_id'))) {
            // Um registro que ja existe (semeie os pais primeiro); sem nenhum, a
            // factory do pai. Company e do pacote e nao tem factory.
            if ($relatedFqcn === 'Ptah\\Models\\Company') {
                return 'fn () => \\Ptah\\Models\\Company::query()->value(\'id\')';
            }

            if ($relatedFqcn !== null) {
                return "fn () => \\{$relatedFqcn}::query()->inRandomOrder()->first()?->getKey() ?? \\{$relatedFqcn}::factory()";
            }

            return $this->nullable ? 'null' : "null, // TODO: {$this->relatedModel()} ainda nao existe — gere-o e troque por {$this->relatedModel()}::factory()";
        }

        $unique = $this->unique ? 'unique()->' : '';
        $name = strtolower($this->name);

        $expr = match (true) {
            $this->type === 'enum' && $this->enumValues !== [] => "fake()->randomElement(['".implode("', '", $this->enumValues)."'])",
            $this->type === 'boolean' => 'fake()->boolean()',
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'smallInteger'], true) => "fake()->{$unique}numberBetween(1, 1000)",
            $this->type === 'tinyInteger' => 'fake()->numberBetween(0, 100)',
            in_array($this->type, ['decimal', 'float', 'double'], true) => 'fake()->randomFloat('.($this->type === 'decimal' ? $this->scale : 2).', 1, '.($this->type === 'decimal' ? min(99999, 10 ** max(1, $this->precision - $this->scale) - 1) : 1000).')',
            $this->type === 'date' => "fake()->date('Y-m-d')",
            in_array($this->type, ['datetime', 'timestamp'], true) => 'fake()->dateTime()',
            $this->type === 'json' => '[]',
            in_array($this->type, ['text', 'longText'], true) => 'fake()->paragraph()',
            str_contains($name, 'email') => "fake()->{$unique}safeEmail()",
            str_contains($name, 'phone') || str_contains($name, 'celular') || str_contains($name, 'telefone') => "fake()->{$unique}numerify('(##) 9####-####')",
            str_contains($name, 'url') || str_contains($name, 'site') => "fake()->{$unique}url()",
            str_contains($name, 'cpf') => "fake()->{$unique}numerify('###########')",
            str_contains($name, 'cnpj') => "fake()->{$unique}numerify('##############')",
            str_contains($name, 'cep') || str_contains($name, 'zip') => "fake()->{$unique}numerify('#####-###')",
            in_array($name, ['city', 'cidade'], true) => 'fake()->city()',
            in_array($name, ['name', 'nome', 'full_name'], true) => "fake()->{$unique}name()",
            in_array($name, ['title', 'titulo'], true) => "fake()->{$unique}sentence(3)",
            in_array($name, ['description', 'descricao'], true) => 'fake()->sentence()',
            str_contains($name, 'code') || str_contains($name, 'codigo') || str_contains($name, 'sku') => "fake()->{$unique}bothify('??-####')",
            default => "fake()->{$unique}words(2, true)",
        };

        // string(10)/char(2): o valor tem de caber na coluna.
        if (in_array($this->type, ['string', 'char'], true) && $this->length !== null && $this->length < 40) {
            $expr = "substr({$expr}, 0, {$this->length})";
        }

        return $expr;
    }

    public function phpType(): string
    {
        $base = match (true) {
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger']) => 'int',
            in_array($this->type, ['decimal', 'float', 'double']) => 'float',
            $this->type === 'boolean' => 'bool',
            in_array($this->type, ['date', 'datetime', 'timestamp']) => '\Carbon\Carbon',
            $this->type === 'json' => 'array',
            default => 'string',
        };

        return $this->nullable ? "?{$base}" : $base;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function enumMigrationCall(): string
    {
        $values = implode(', ', array_map(fn ($v) => "'{$v}'", $this->enumValues));

        return "\$table->enum('{$this->name}', [{$values}])";
    }

    private function buildRules(bool $isUpdate): string
    {
        $rules = [];

        if ($isUpdate) {
            $rules[] = 'sometimes';
        }

        $rules[] = $this->nullable ? 'nullable' : 'required';

        $rules[] = match (true) {
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger']) => 'integer',
            in_array($this->type, ['decimal', 'float', 'double']) => 'numeric',
            $this->type === 'boolean' => 'boolean',
            in_array($this->type, ['date', 'datetime', 'timestamp']) => 'date',
            $this->type === 'json' => 'array',
            $this->type === 'enum' => 'in:'.implode(',', $this->enumValues),
            default => 'string',
        };

        if ($this->unique) {
            $rules[] = 'unique:TABELA_AQUI';
        }

        if (in_array($this->type, ['string', 'char'], true)) {
            $rules[] = 'max:'.($this->length ?? 255);
        }

        if ($this->type === 'text') {
            $rules[] = 'max:65535';
        }

        return implode('|', $rules);
    }
}
