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
        public string  $name,
        public string  $type,
        public bool    $nullable,
        public bool    $unique,
        public int     $precision,    // decimal: total digits
        public int     $scale,        // decimal: decimal places
        public array   $enumValues,   // enum: ['active', 'inactive']
        public string  $label        = '',    // display label in BaseCrud (surname)
        public bool    $hasDefault   = false, // whether a ->default() should be emitted
        public ?string $defaultValue = null,  // default value literal: 'true', '0', 'active'
    ) {}

    /**
     * Type for Eloquent's $casts.
     */
    public function castType(): string
    {
        return match (true) {
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger'])
                => 'integer',
            $this->type === 'decimal'
                => "decimal:{$this->scale}",
            in_array($this->type, ['float', 'double'])
                => 'float',
            $this->type === 'boolean'
                => 'boolean',
            $this->type === 'date'
                => 'date',
            in_array($this->type, ['datetime', 'timestamp'])
                => 'datetime',
            $this->type === 'json'
                => 'array',
            default
                => 'string',
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

        return in_array($table, $ptahTables, true) ? 'ptah_' . $table : $table;
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
            return $indent . $line . ';';
        }

        $line = match ($this->type) {
            'string'             => "\$table->string('{$this->name}')",
            'text'               => "\$table->text('{$this->name}')",
            'longText'           => "\$table->longText('{$this->name}')",
            'integer'            => "\$table->integer('{$this->name}')",
            'bigInteger'         => "\$table->bigInteger('{$this->name}')",
            'unsignedBigInteger' => "\$table->unsignedBigInteger('{$this->name}')",
            'unsignedInteger'    => "\$table->unsignedInteger('{$this->name}')",
            'tinyInteger'        => "\$table->tinyInteger('{$this->name}')",
            'smallInteger'       => "\$table->smallInteger('{$this->name}')",
            'decimal'            => "\$table->decimal('{$this->name}', {$this->precision}, {$this->scale})",
            'float'              => "\$table->float('{$this->name}')",
            'double'             => "\$table->double('{$this->name}')",
            'boolean'            => "\$table->boolean('{$this->name}')",
            'date'               => "\$table->date('{$this->name}')",
            'datetime', 'timestamp' => "\$table->timestamp('{$this->name}')",
            'json'               => "\$table->json('{$this->name}')",
            'enum'               => $this->enumMigrationCall(),
            default              => "\$table->string('{$this->name}')",
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
            $val  = $this->defaultValue;
            $line .= match (true) {
                in_array(strtolower($val), ['true', 'false', 'null'], true) => "->default({$val})",
                is_numeric($val)                                            => "->default({$val})",
                default                                                     => "->default('{$val}')",
            };
        }

        return $indent . $line . ';';
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
    public function phpType(): string
    {
        $base = match (true) {
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger'])
                => 'int',
            in_array($this->type, ['decimal', 'float', 'double'])
                => 'float',
            $this->type === 'boolean'
                => 'bool',
            in_array($this->type, ['date', 'datetime', 'timestamp'])
                => '\Carbon\Carbon',
            $this->type === 'json'
                => 'array',
            default
                => 'string',
        };

        return $this->nullable ? "?{$base}" : $base;
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function enumMigrationCall(): string
    {
        $values = implode(', ', array_map(fn($v) => "'{$v}'", $this->enumValues));
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
            in_array($this->type, ['integer', 'bigInteger', 'unsignedBigInteger', 'unsignedInteger', 'tinyInteger', 'smallInteger'])
                => 'integer',
            in_array($this->type, ['decimal', 'float', 'double'])
                => 'numeric',
            $this->type === 'boolean'
                => 'boolean',
            in_array($this->type, ['date', 'datetime', 'timestamp'])
                => 'date',
            $this->type === 'json'
                => 'array',
            $this->type === 'enum'
                => 'in:' . implode(',', $this->enumValues),
            default
                => 'string',
        };

        if ($this->unique) {
            $rules[] = 'unique:TABELA_AQUI';
        }

        if (in_array($this->type, ['string'])) {
            $rules[] = 'max:255';
        }

        if ($this->type === 'text') {
            $rules[] = 'max:65535';
        }

        return implode('|', $rules);
    }
}
