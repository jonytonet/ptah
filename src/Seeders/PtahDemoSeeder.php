<?php

declare(strict_types=1);

namespace Ptah\Seeders;

use Illuminate\Database\Seeder;
use Ptah\Models\Company;
use Ptah\Models\Department;
use Ptah\Models\Menu;
use Ptah\Models\Role;

/**
 * Ptah demo data.
 *
 * Called via:  php artisan ptah:install --demo
 *
 * Creates:
 *   - 2 extra companies (beta and corp) beyond the default
 *   - 3 departments (IT, Commercial, Financial)
 *   - 2 example roles (Editor, Viewer)
 *   - 5 demo menu items (when driver=database)
 *
 * Idempotent: skips data that already exists.
 */
class PtahDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCompanies();
        $this->seedDepartments();
        $this->seedRoles();
        $this->seedMenuItems();
    }

    // ── Empresas ──────────────────────────────────────────────────────

    private function seedCompanies(): void
    {
        $demos = [
            ['name' => 'Beta Tecnologia Ltda',    'label' => 'BETA', 'slug' => 'beta-tecnologia'],
            ['name' => 'Corp Solutions S/A',       'label' => 'CORP', 'slug' => 'corp-solutions'],
        ];

        foreach ($demos as $data) {
            if (Company::where('label', $data['label'])->doesntExist()) {
                Company::create(array_merge($data, [
                    'is_default' => false,
                    'is_active'  => true,
                ]));
                $this->line("  <info>✔</info> Demo company: <comment>{$data['name']}</comment>");
            }
        }
    }

    // ── Departments ───────────────────────────────────────────────────────────

    private function seedDepartments(): void
    {
        if (! class_exists(Department::class)) {
            return;
        }

        $departments = [
            ['name' => 'IT',         'description' => 'Information Technology',    'is_active' => true],
            ['name' => 'Commercial', 'description' => 'Sales and commercial team', 'is_active' => true],
            ['name' => 'Financial',  'description' => 'Financial management',      'is_active' => true],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->line('  <info>✔</info> Demo departments created.');
    }

    // ── Roles ─────────────────────────────────────────────────────────

    private function seedRoles(): void
    {
        if (! class_exists(Role::class)) {
            return;
        }

        $roles = [
            ['name' => 'Editor', 'description' => 'Can create and edit records', 'is_active' => true],
            ['name' => 'Viewer', 'description' => 'Read-only access',            'is_active' => true],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->line('  <info>✔</info> Demo roles created.');
    }

    // ── Itens de Menu ─────────────────────────────────────────────────

    private function seedMenuItems(): void
    {
        if (! class_exists(Menu::class)) {
            return;
        }

        if (! config('ptah.menu.driver') || config('ptah.menu.driver') !== 'database') {
            return;
        }

        $items = [
            ['text' => 'Users',    'url' => '/users',    'icon' => 'bx bx-user',      'type' => 'menuLink', 'link_order' => 1],
            ['text' => 'Products', 'url' => '/products', 'icon' => 'bx bx-cube',      'type' => 'menuLink', 'link_order' => 2],
            ['text' => 'Reports',  'url' => '/reports',  'icon' => 'bx bx-bar-chart', 'type' => 'menuLink', 'link_order' => 3],
        ];

        foreach ($items as $data) {
            Menu::firstOrCreate(
                ['text' => $data['text']],
                array_merge($data, ['target' => '_self', 'is_active' => true])
            );
        }

        $this->line('  <info>✔</info> Demo menu items created.');
    }

    private function line(string $msg): void
    {
        $this->command?->getOutput()?->writeln($msg);
    }
}
