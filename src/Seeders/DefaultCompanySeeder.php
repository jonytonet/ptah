<?php

declare(strict_types=1);

namespace Ptah\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ptah\Models\Company;

/**
 * Creates the system default company if it does not yet exist.
 *
 * Idempotent: safe to run multiple times.
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
            // Auto-generate label: first 4 uppercase letters of the name
            $autoLabel = strtoupper(Str::substr(Str::ascii($name), 0, 4));

            Company::create([
                'name'       => $name,
                'slug'       => Str::slug($name),
                'label'      => $autoLabel,
                'is_default' => true,
                'is_active'  => true,
            ]);

            $this->command?->getOutput()?->writeln(
                "  <info>✔</info> Default company created: <comment>{$name}</comment>"
            );
        } else {
            $this->command?->getOutput()?->writeln(
                "  <comment>→</comment> Default company already exists: <comment>{$company->name}</comment>"
            );
        }
    }
}
