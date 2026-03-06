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
 * Creates the full admin chain:
 *
 *   Default company → Administration Department → MASTER Role
 *   → Admin User → UserRole (admin × MASTER × default company)
 *
 * Idempotent: uses firstOrCreate on all steps.
 * Credentials are read from config/env, never hard-coded.
 */
class DefaultAdminSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Default company ─────────────────────────────────────────────
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
            $this->line("  <info>✔</info> Default company created: <comment>{$company->name}</comment>");
        } else {
            $this->line("  <comment>→</comment> Default company: <comment>{$company->name}</comment>");
        }

        // ── 2. Administration Department ────────────────────────────────────
        $department = Department::withTrashed()->firstOrCreate(
            ['name' => 'Administration'],
            ['is_active' => true]
        );

        if ($department->wasRecentlyCreated) {
            $this->line("  <info>✔</info> Department created: <comment>Administration</comment>");
        } else {
            $this->line("  <comment>→</comment> Department: <comment>Administration</comment>");
        }

        // ── 3. MASTER Role ────────────────────────────────────────────────
        $masterRole = Role::withTrashed()
            ->where('is_master', true)
            ->first();

        if (!$masterRole) {
            $masterRole = Role::create([
                'name'          => 'MASTER',
                'description'   => 'Role with full system access. Cannot be deleted.',
                'color'         => '#fbbf24',
                'department_id' => $department->id,
                'is_master'     => true,
                'is_active'     => true,
            ]);
            $this->line("  <info>✔</info> MASTER role created.");
        } else {
            $this->line("  <comment>→</comment> MASTER role: <comment>{$masterRole->name}</comment>");
        }

        // ── 4. Admin User ────────────────────────────────────────────────
        $adminEmail    = config('ptah.permissions.admin_email', 'admin@admin.com');
        $adminName     = config('ptah.permissions.admin_name', 'Administrator');
        $adminPassword = config('ptah.permissions.admin_password') ?: 'admin@123';

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $userModel */
        $userModel = config('ptah.permissions.user_model', 'App\Models\User');

        if (!class_exists($userModel)) {
            $this->line("  <error>✘ User model not found: {$userModel}\n    Set PTAH_USER_MODEL in .env</error>");
            return;
        }

        // Check whether the model uses SoftDeletes before calling withTrashed()
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
            $this->line("  <info>✔</info> Admin user created: <comment>{$adminEmail}</comment>");
        } else {
            $this->line("  <comment>→</comment> Admin user: <comment>{$adminEmail}</comment>");
        }

        // ── 5. UserRole: admin × MASTER × default company ─────────────────────
        $userRole = UserRole::withTrashed()->firstOrCreate(
            [
                'user_id'    => $admin->id,
                'role_id'    => $masterRole->id,
                'company_id' => $company->id,
            ],
            ['is_active' => true]
        );

        if ($userRole->wasRecentlyCreated) {
            $this->line("  <info>✔</info> Binding admin × MASTER × {$company->name} created.");
        } else {
            $this->line("  <comment>→</comment> Binding already exists.");
        }

        // Restore soft-deleted if necessary
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
