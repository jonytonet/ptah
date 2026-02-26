<?php

declare(strict_types=1);

namespace Ptah\Support;

use Illuminate\Support\Str;

/**
 * Value object que representa a definição de um único campo.
 *
 * Formato da string de entrada (opção --fields):
 *   nome:tipo[(params)][:nullable][:unique]
 *
 * Exemplos:
 *   name:string
 *   price:decimal(10,2):nullable
 *   status:enum(active|inactive|pending)
 *   is_active:boolean
 *   email:string:unique
 *   user_id:unsignedBigInteger
 */
readonly class FieldDefinition
{
    public function __construct(
        public string $name,
        public string $type,
        public bool   $nullable,
        public bool   $unique,
        public int    $precision,   // decimal: total digits
        public int    $scale,       // decimal: casas decimais
        public array  $enumValues,  // enum: ['active', 'inactive']
    ) {}

    /**
     * Tipo para o $casts do Eloquent.
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
     * Se o campo termina em _id e o tipo é inteiro grande, é tratado como FK.
     */
    public function isForeignKey(): bool
    {
        return str_ends_with($this->name, '_id')
            && in_array($this->type, ['unsignedBigInteger', 'foreignId', 'bigInteger']);
    }

    /**
     * Nome sem o sufixo _id (ex: business_partner_id → business_partner).
     */
    public function relatedName(): string
    {
        return substr($this->name, 0, -3);
    }

    /**
     * Model relacionado em StudlyCase (ex: business_partner → BusinessPartner).
     */
    public function relatedModel(): string
    {
        return Str::studly($this->relatedName());
    }

    /**
     * Tabela relacionada em snake_case plural (ex: business_partner → business_partners).
     */
    public function relatedTable(): string
    {
        return Str::plural($this->relatedName());
    }

    /**
     * Linha de definição Blueprint para migration.
     */
    public function migrationLine(string $indent = '            '): string
    {
        // FK: gera foreignId()->constrained()->cascadeOnDelete() automaticamente
        if ($this->isForeignKey()) {
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

        return $indent . $line . ';';
    }

    /**
     * Regra de validação Laravel para criação (store).
     */
    public function validationRuleStore(): string
    {
        return "'{$this->name}' => '{$this->buildRules(isUpdate: false)}',";
    }

    /**
     * Regra de validação Laravel para atualização (update).
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
