<?php

declare(strict_types=1);

namespace Ptah\Tests\Factories;

use Illuminate\Support\Str;
use Ptah\Models\Company;

/**
 * Factory simples para testes — sem depender do sistema de factories do Laravel.
 *
 * Uso:
 *   CompanyFactory::make(['label' => 'BETA']);
 *   CompanyFactory::create(['is_default' => true]);
 */
class CompanyFactory
{
    private array $attributes = [];

    private function __construct(array $attributes = [])
    {
        $this->attributes = array_merge([
            'name'       => 'Empresa Teste ' . Str::random(4),
            'label'      => strtoupper(Str::random(3)),
            'slug'       => null,
            'email'      => null,
            'phone'      => null,
            'tax_id'     => null,
            'tax_type'   => 'cnpj',
            'address'    => null,
            'settings'   => null,
            'is_default' => false,
            'is_active'  => true,
        ], $attributes);
    }

    public static function new(array $attributes = []): self
    {
        return new self($attributes);
    }

    public function make(array $attributes = []): Company
    {
        return new Company(array_merge($this->attributes, $attributes));
    }

    public function create(array $attributes = []): Company
    {
        $data = array_merge($this->attributes, $attributes);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return Company::create($data);
    }
}
