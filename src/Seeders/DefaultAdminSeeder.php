<?php

declare(strict_types=1);

namespace Ptah\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Ptah\Models\Company;
use Ptah\Models\Department;
use Ptah\Models\Role;
use Ptah\Models\UserRole;

/**
 * Cria a cadeia completa de admin:
 *
 *   Empresa padrão → Departamento Administração → Role MASTER
 *   → Usuário Admin → UserRole (admin × MASTER × empresa padrão)
 *
 * Idempotente: usa firstOrCreate em todos os passos.
 * As credenciais são lidas de config/env, nunca hard-coded.
 */
class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Empresa padrão ────────────────────────────────────
        $companyName = config('app.name', 'Company');
        $company     = Company::withTrashed()->where('is_default', true)->first();

        if (!$company) {
            $company = Company::create([
                'name'       => $companyName,
                'slug'       => Str::slug($companyName),
                'label'      => strtoupper(Str::substr(Str::ascii($companyName), 0, 4)),
                'is_default' => true,
                'is_active'  => true,
            ]);
            $this->line("  <info>✔</info> Empresa padrão criada: <comment>{$company->name}</comment>");
        } else {
            $this->line("  <comment>→</comment> Empresa padrão: <comment>{$company->name}</comment>");
        }

        // ── 2. Departamento Administração ────────────────────────
        $department = Department::withTrashed()->firstOrCreate(
            ['name' => 'Administração'],
            ['is_active' => true]
        );

        if ($department->wasRecentlyCreated) {
            $this->line("  <info>✔</info> Departamento criado: <comment>Administração</comment>");
        } else {
            $this->line("  <comment>→</comment> Departamento: <comment>Administração</comment>");
        }

        // ── 3. Role MASTER ───────────────────────────────────────
        $masterRole = Role::withTrashed()
            ->where('is_master', true)
            ->first();

        if (!$masterRole) {
            $masterRole = Role::create([
                'name'          => 'MASTER',
                'description'   => 'Role com acesso total ao sistema. Não pode ser excluído.',
                'color'         => '#fbbf24',
                'department_id' => $department->id,
                'is_master'     => true,
                'is_active'     => true,
            ]);
            $this->line("  <info>✔</info> Role MASTER criado.");
        } else {
            $this->line("  <comment>→</comment> Role MASTER: <comment>{$masterRole->name}</comment>");
        }

        // ── 4. Usuário Admin ─────────────────────────────────────
        $adminEmail    = config('ptah.permissions.admin_email', 'admin@admin.com');
        $adminName     = config('ptah.permissions.admin_name', 'Administrador');
        $adminPassword = config('ptah.permissions.admin_password', 'admin@123');

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('ptah.permissions.user_model', 'App\Models\User');

        if (!class_exists($userModel)) {
            $this->line("  <error>✘ Model de usuário não encontrado: {$userModel}\n    Defina PTAH_USER_MODEL no .env</error>");
            return;
        }

        // Verifica se o model usa SoftDeletes antes de chamar withTrashed()
        $usesSoftDeletes = in_array(
            \Illuminate\Database\Eloquent\SoftDeletes::class,
            class_uses_recursive($userModel),
            true
        );

        $adminQuery = $usesSoftDeletes
            ? $userModel::withTrashed()->where('email', $adminEmail)
            : $userModel::where('email', $adminEmail);

        $admin = $adminQuery->first()
            ?? $userModel::create([
                'name'     => $adminName,
                'email'    => $adminEmail,
                'password' => Hash::make($adminPassword),
            ]);

        if ($admin->wasRecentlyCreated ?? false) {
            $this->line("  <info>✔</info> Usuário admin criado: <comment>{$adminEmail}</comment>");
        } else {
            $this->line("  <comment>→</comment> Usuário admin: <comment>{$adminEmail}</comment>");
        }

        // ── 5. UserRole: admin × MASTER × empresa padrão ─────────
        $userRole = UserRole::withTrashed()->firstOrCreate(
            [
                'user_id'    => $admin->id,
                'role_id'    => $masterRole->id,
                'company_id' => $company->id,
            ],
            ['is_active' => true]
        );

        if ($userRole->wasRecentlyCreated) {
            $this->line("  <info>✔</info> Vínculo admin × MASTER × {$company->name} criado.");
        } else {
            $this->line("  <comment>→</comment> Vínculo admin já existe.");
        }

        // Restaura soft-deleted se necessário
        if ($userRole->trashed()) {
            $userRole->restore();
            $userRole->update(['is_active' => true]);
        }
    }

    protected function line(string $text): void
    {
        $this->command?->getOutput()?->writeln($text);
    }
}
