<?php

declare(strict_types=1);

namespace Ptah\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ptah\Models\Company;

/**
 * Cria a empresa padrão do sistema, se ainda não existir.
 *
 * Idempotente: seguro para rodar múltiplas vezes.
 */
class DefaultCompanySeeder extends Seeder
{
    public function run(): void
    {
        $name = config('app.name', 'Company');

        $company = Company::withTrashed()
            ->where('is_default', true)
            ->first();

        if (!$company) {
            // Gera label automático: primeiras 4 letras do nome em maiúsculo
            $autoLabel = strtoupper(Str::substr(Str::ascii($name), 0, 4));

            Company::create([
                'name'       => $name,
                'slug'       => Str::slug($name),
                'label'      => $autoLabel,
                'is_default' => true,
                'is_active'  => true,
            ]);

            $this->command?->getOutput()?->writeln(
                "  <info>✔</info> Empresa padrão criada: <comment>{$name}</comment>"
            );
        } else {
            $this->command?->getOutput()?->writeln(
                "  <comment>→</comment> Empresa padrão já existe: <comment>{$company->name}</comment>"
            );
        }
    }
}
