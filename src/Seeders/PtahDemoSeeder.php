<?php

declare(strict_types=1);

namespace Ptah\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Ptah\Models\Company;
use Ptah\Models\Department;
use Ptah\Models\Menu;
use Ptah\Models\Role;

/**
 * Dados de demonstração do Ptah.
 *
 * Chamado via:  php artisan ptah:install --demo
 *
 * Cria:
 *   - 2 empresas extras (beta e corp) além da padrão
 *   - 3 departamentos (TI, Comercial, Financeiro)
 *   - 2 roles exemplo (Editor, Viewer)
 *   - 5 itens de menu de demonstração (quando driver=database)
 *
 * Idempotente: skip em dados que já existem.
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
                $this->line("  <info>✔</info> Empresa demo: <comment>{$data['name']}</comment>");
            }
        }
    }

    // ── Departamentos ─────────────────────────────────────────────────

    private function seedDepartments(): void
    {
        if (! class_exists(Department::class)) {
            return;
        }

        $departments = [
            ['name' => 'TI',         'description' => 'Tecnologia da Informação', 'is_active' => true],
            ['name' => 'Comercial',  'description' => 'Equipe comercial e vendas', 'is_active' => true],
            ['name' => 'Financeiro', 'description' => 'Gestão financeira',         'is_active' => true],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(['name' => $data['name']], $data);
        }

        $this->line('  <info>✔</info> Departamentos demo criados.');
    }

    // ── Roles ─────────────────────────────────────────────────────────

    private function seedRoles(): void
    {
        if (! class_exists(Role::class)) {
            return;
        }

        $roles = [
            ['name' => 'Editor', 'slug' => 'editor', 'description' => 'Pode criar e editar registros', 'is_active' => true],
            ['name' => 'Viewer', 'slug' => 'viewer', 'description' => 'Apenas visualização',           'is_active' => true],
        ];

        foreach ($roles as $data) {
            Role::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->line('  <info>✔</info> Roles demo criados.');
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
            ['text' => 'Usuários',    'url' => '/users',    'icon' => 'bx bx-user',      'type' => 'menuLink', 'link_order' => 1],
            ['text' => 'Produtos',    'url' => '/products', 'icon' => 'bx bx-cube',      'type' => 'menuLink', 'link_order' => 2],
            ['text' => 'Relatórios',  'url' => '/reports',  'icon' => 'bx bx-bar-chart', 'type' => 'menuLink', 'link_order' => 3],
        ];

        foreach ($items as $data) {
            Menu::firstOrCreate(
                ['text' => $data['text']],
                array_merge($data, ['target' => '_self', 'is_active' => true])
            );
        }

        $this->line('  <info>✔</info> Itens de menu demo criados.');
    }

    private function line(string $msg): void
    {
        $this->command?->getOutput()?->writeln($msg);
    }
}
