<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Value object (immutable DTO) that carries all pre-computed data
 * of an entity during the scaffold execution.
 *
 * Created once by ScaffoldCommand and passed to all Generators.
 *
 * Sub-folder support:
 *   - subFolder   : subfolder relative to the models directory, e.g. 'Product'
 *                   Can be multi-level: 'Catalog/Product'
 *                   Empty when the entity is at the root.
 *   - modelNamespace : full PHP namespace of the Model, e.g. App\Models\Product
 *   - modelFqn       : full Model FQN,  e.g. App\Models\Product\ProductStock
 */
readonly class EntityContext
{
    /**
     * PHP namespace of the Model (e.g. App\Models\Product or App\Models).
     * Automatically computed from rootNamespace + subFolder.
     */
    public string $modelNamespace;

    /**
     * Full Model FQN (e.g. App\Models\Product\ProductStock).
     */
    public string $modelFqn;

    /**
     * @param  FieldDefinition[]  $fields
     * @param  string  $subFolder  Subfolder relative to Models/, e.g. 'Product'. Empty if root.
     */
    public function __construct(
        public string $entity,             // ProductStock
        public string $entityLower,        // product_stock
        public string $entityPlural,       // product_stocks
        public string $entityPluralStudly, // ProductStocks
        public string $table,              // product_stocks (ou valor de --table)
        public string $rootNamespace,      // App\
        public string $timestamp,          // 2026_02_25_120000
        public bool $withViews,          // false quando --api-only
        public bool $withSoftDeletes,    // true by default
        public bool $force,              // --force
        public array $fields,
        public string $subFolder = '',     // ex: 'Product' ou 'Catalog/Product'
        public bool $withApi = false,  // true quando --api ou --api-only
        public bool $withFactory = false, // --factory: factory + seeder
    ) {
        $nsBase = rtrim($rootNamespace, '\\').'\\Models';
        $this->modelNamespace = $subFolder
            ? $nsBase.'\\'.str_replace('/', '\\', $subFolder)
            : $nsBase;
        $this->modelFqn = $this->modelNamespace.'\\'.$entity;
    }

    /**
     * Returns the namespace with the subfolder applied.
     * E.g. subNs('App\\Services') with subFolder='Product' → 'App\\Services\\Product'
     */
    public function subNs(string $baseNamespace): string
    {
        if ($this->subFolder === '') {
            return rtrim($baseNamespace, '\\');
        }

        return rtrim($baseNamespace, '\\').'\\'.str_replace('/', '\\', $this->subFolder);
    }

    /**
     * Returns the file path with the subfolder applied.
     * E.g. subPath('app/Services') with subFolder='Product' → 'app/Services/Product'
     */
    public function subPath(string $basePath): string
    {
        if ($this->subFolder === '') {
            return rtrim($basePath, '/');
        }

        return rtrim($basePath, '/').'/'.$this->subFolder;
    }

    /**
     * Generates the $fillable list as a string for the Model stub.
     * Result: 'name', 'price', 'status'
     */
    public function fillableList(): string
    {
        $base = empty($this->fields)
            ? ['// Add fields here']
            : array_map(fn (FieldDefinition $f) => "'{$f->name}'", $this->fields);

        // Audit fields — automatically populated by the HasAuditFields trait
        $base[] = "'created_by'";
        $base[] = "'updated_by'";

        if ($this->withSoftDeletes) {
            $base[] = "'deleted_by'";
        }

        return implode(",\n        ", $base);
    }

    /**
     * Generates the $casts block as a string for the Model stub.
     * Result: 'price' => 'decimal:2', 'is_active' => 'boolean',
     */
    public function castsList(): string
    {
        $base = empty($this->fields)
            ? ['// \'field\' => \'type\',']
            : array_map(fn (FieldDefinition $f) => "'{$f->name}' => '{$f->castType()}',", $this->fields);

        // Casts for the audit fields
        $base[] = "'created_by' => 'integer',";
        $base[] = "'updated_by' => 'integer',";

        if ($this->withSoftDeletes) {
            $base[] = "'deleted_by' => 'integer',";
        }

        return implode("\n        ", $base);
    }

    /**
     * Generates the Blueprint lines for the migration.
     */
    public function migrationColumns(): string
    {
        if (empty($this->fields)) {
            return '            // Add columns here';
        }

        return implode("\n", array_map(
            fn (FieldDefinition $f) => $f->migrationLine(),
            $this->fields
        ));
    }

    /**
     * Generates validation rules for Store.
     */
    public function validationRulesStore(): string
    {
        if (empty($this->fields)) {
            return "            // 'field' => 'required|string',";
        }

        return implode("\n            ", array_map(
            fn (FieldDefinition $f) => $f->validationRuleStore(),
            $this->fields
        ));
    }

    /**
     * Generates validation rules for Update.
     */
    public function validationRulesUpdate(): string
    {
        if (empty($this->fields)) {
            return "            // 'field' => 'sometimes|required|string',";
        }

        return implode("\n            ", array_map(
            fn (FieldDefinition $f) => $f->validationRuleUpdate(),
            $this->fields
        ));
    }

    /**
     * Generates DTO properties with PHP types.
     *
     * Required (non-nullable) properties are emitted first: PHP 8 deprecates
     * optional parameters declared before required ones. Reordering is safe
     * because fromArray() instantiates the DTO with named arguments.
     */
    public function dtoProperties(): string
    {
        if (empty($this->fields)) {
            return '        // public readonly string $name,';
        }

        $ordered = array_merge(
            array_filter($this->fields, fn (FieldDefinition $f) => ! $f->nullable),
            array_filter($this->fields, fn (FieldDefinition $f) => $f->nullable),
        );

        return implode("\n", array_map(
            fn (FieldDefinition $f) => "        public readonly {$f->phpType()} \${$f->name}".
                ($f->nullable ? ' = null,' : ','),
            $ordered
        ));
    }

    /**
     * The factory's definition() body: one Faker expression per field, FKs
     * pointing at the related model when it resolved (see relationshipImports).
     */
    public function factoryDefinition(?string $modelsPath = null): string
    {
        if (empty($this->fields)) {
            return "            // 'name' => fake()->words(2, true),";
        }

        $related = [];
        foreach ($this->relationshipImports($modelsPath) as $import) {
            $related[$import['field']] = $import['fqcn'];
        }

        return implode("\n", array_map(function (FieldDefinition $f) use ($related): string {
            $expr = $f->fakerExpression($related[$f->name] ?? null);

            // O TODO de FK ja traz a virgula antes do comentario.
            return "            '{$f->name}' => {$expr}".(str_contains($expr, ', //') ? '' : ',');
        }, $this->fields));
    }

    /**
     * Generates the DTO fromArray mapping.
     */
    public function dtoFromArray(): string
    {
        if (empty($this->fields)) {
            return "            // name: \$data['name'],";
        }

        return implode("\n", array_map(
            fn (FieldDefinition $f) => "            {$f->name}: \$data['{$f->name}']".
                ($f->nullable ? ' ?? null,' : ','),
            $this->fields
        ));
    }

    /**
     * Generates belongsTo methods for FK fields (_id with large integer type).
     * Returns an empty string if there are no FKs.
     */
    public function relationships(): string
    {
        $fkFields = array_values(array_filter(
            $this->fields,
            fn (FieldDefinition $f) => $f->isForeignKey()
        ));

        if (empty($fkFields)) {
            return '';
        }

        $methods = array_map(function (FieldDefinition $f) {
            $methodName = Str::camel($f->relatedName());
            $relatedModel = $f->relatedModel();

            return
                "    public function {$methodName}(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo\n".
                "    {\n".
                "        return \$this->belongsTo({$relatedModel}::class, '{$f->name}');\n".
                '    }';
        }, $fkFields);

        return "\n".implode("\n\n", $methods)."\n";
    }

    /**
     * How each foreign key's related model was located.
     *
     * One entry per FK field, in field order:
     *
     *   status     'resolved'       exactly one class of that name exists
     *              'same_namespace' resolved, and it lives in this model's own
     *                               namespace — no `use` needed
     *              'package'        a model the package ships (Company)
     *              'missing'        no class of that name yet — the related
     *                               entity has not been generated
     *              'ambiguous'      more than one class of that name
     *   candidates the FQCNs found (empty for 'missing' / 'package')
     *
     * The generator used to leave EVERY import as a TODO, and the package's
     * skill made fixing them a mandatory manual step. See ModelLocator for why
     * resolving only the unambiguous case keeps the original "never guess"
     * rule intact.
     *
     * @param  string|null  $modelsPath  where the models live; defaults to
     *                                   `ptah.paths.models`, the same directory the
     *                                   generator writes to
     * @return list<array{field: string, model: string, status: string, fqcn: string|null, candidates: list<string>}>
     */
    public function relationshipImports(?string $modelsPath = null): array
    {
        $modelsPath ??= (string) config('ptah.paths.models', '');

        $out = [];

        foreach ($this->fields as $field) {
            if (! $field->isForeignKey()) {
                continue;
            }

            $model = $field->relatedModel();

            // company_id → Ptah\Models\Company (well-known package model)
            if ($model === 'Company') {
                $out[] = ['field' => $field->name, 'model' => $model, 'status' => 'package', 'fqcn' => 'Ptah\\Models\\Company', 'candidates' => []];

                continue;
            }

            $candidates = $modelsPath !== '' ? ModelLocator::find($model, $modelsPath) : [];

            $status = match (count($candidates)) {
                0 => 'missing',
                1 => $this->namespaceOf($candidates[0]) === $this->modelNamespace ? 'same_namespace' : 'resolved',
                default => 'ambiguous',
            };

            $out[] = [
                'field' => $field->name,
                'model' => $model,
                'status' => $status,
                'fqcn' => count($candidates) === 1 ? $candidates[0] : null,
                'candidates' => $candidates,
            ];
        }

        return $out;
    }

    /**
     * `use` declarations for models related via FK.
     *
     * Resolved when exactly one class of that name exists (see
     * relationshipImports()); a TODO otherwise, now saying WHY — not generated
     * yet, or which candidates compete — so whoever reads it knows what to do
     * without searching. Returns an empty string if there are no FKs.
     */
    public function relationshipsUse(?string $modelsPath = null): string
    {
        $imports = $this->relationshipImports($modelsPath);

        if ($imports === []) {
            return '';
        }

        $rootNs = rtrim($this->rootNamespace, '\\').'\\Models';

        $lines = array_unique(array_filter(array_map(
            function (array $i) use ($rootNs): ?string {
                return match ($i['status']) {
                    'package', 'resolved' => "use {$i['fqcn']};",
                    // Mesmo namespace do model gerado: o PHP resolve sozinho.
                    'same_namespace' => null,
                    'ambiguous' => "// TODO: use ??\\{$i['model']}; // more than one {$i['model']} class: "
                        .implode(', ', $i['candidates']).' — pick the right one',
                    default => "// TODO: use {$rootNs}\\{$i['model']}; // {$i['model']} does not exist in app/Models yet"
                        .' — generate it, or adjust the namespace',
                };
            },
            $imports
        )));

        return $lines === [] ? '' : implode("\n", $lines)."\n";
    }

    private function namespaceOf(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? '' : substr($fqcn, 0, $pos);
    }

    /**
     * Generates the toArray() fields for the Resource.
     */
    public function resourceFields(): string
    {
        $lines = ["'id' => \$this->id,"];

        foreach ($this->fields as $field) {
            $lines[] = "            '{$field->name}' => \$this->{$field->name},";
        }

        $lines[] = "            'created_by' => \$this->created_by,";
        $lines[] = "            'updated_by' => \$this->updated_by,";

        if ($this->withSoftDeletes) {
            $lines[] = "            'deleted_by' => \$this->deleted_by,";
        }

        $lines[] = "            'created_at' => \$this->created_at,";
        $lines[] = "            'updated_at' => \$this->updated_at,";

        return implode("\n", $lines);
    }
}
