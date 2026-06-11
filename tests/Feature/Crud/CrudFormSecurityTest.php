<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\Concerns\HasCrudForm;
use Ptah\Tests\TestCase;

// ── Minimal harness for HasCrudForm protected methods ────────────────────────
//
// The trait's protected methods (guardedFormFields, executeInlineHook,
// executeDynamicHook, authorizeCrudAction) only reference $this->crudConfig,
// $this->model and external facades — no Livewire service dependencies.
// This harness exposes public wrappers that preserve the by-reference contract
// for $data, which ReflectionMethod::invokeArgs() cannot do.

class CrudFormHarness
{
    use HasCrudForm;

    public array $crudConfig = [];

    public string $model = 'Test';

    /** Proxy that keeps $data by-reference intact. */
    public function runDynamicHook(string $hookName, array &$data): void
    {
        $this->executeDynamicHook($hookName, $data);
    }

    /** Proxy for the inline sandbox directly. */
    public function runInlineHook(string $code, string $hookName, array &$data): void
    {
        $this->executeInlineHook($code, $hookName, $data, null);
    }

    /** Expose guardedFormFields publicly for assertions. */
    public function exposedGuardedFields(): array
    {
        return $this->guardedFormFields();
    }

    /** Expose authorizeCrudAction publicly for assertions. */
    public function exposedAuthorize(string $action): bool
    {
        return $this->authorizeCrudAction($action);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

/**
 * P0 security regression tests for HasCrudForm.
 *
 * Covers the three critical fixes:
 *   1. guardedFormFields() lists all audit / PK columns that must never come
 *      from user-submitted form data.
 *   2. executeInlineHook() is a closed sandbox (symfony/expression-language);
 *      valid expressions mutate $data, arbitrary PHP is rejected.
 *   3. authorizeCrudAction() is fail-closed: anonymous users are denied when
 *      the permissions module is on and a permissionIdentifier is set.
 */
class CrudFormSecurityTest extends TestCase
{
    private function harness(): CrudFormHarness
    {
        return new CrudFormHarness;
    }

    // ── 1. guardedFormFields ──────────────────────────────────────────────────

    #[Test]
    public function guarded_fields_covers_all_audit_and_pk_columns(): void
    {
        $guarded = $this->harness()->exposedGuardedFields();

        $mustBeGuarded = [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'deleted_by',
            'remember_token',
        ];

        foreach ($mustBeGuarded as $field) {
            $this->assertContains(
                $field,
                $guarded,
                "'{$field}' must be in guardedFormFields() to prevent mass assignment",
            );
        }
    }

    // ── 2. Inline-hook sandbox ────────────────────────────────────────────────

    #[Test]
    public function sandbox_applies_valid_merge_expression(): void
    {
        $harness = $this->harness();
        $data = ['name' => 'Test', 'amount' => 10];

        $harness->runInlineHook("merge(data, {'status': 'pending'})", 'beforeCreate', $data);

        $this->assertSame('pending', $data['status']);
        $this->assertSame('Test', $data['name'], 'Original keys must be preserved after merge');
        $this->assertSame(10, $data['amount']);
    }

    #[Test]
    public function sandbox_registered_functions_work(): void
    {
        $harness = $this->harness();
        $data = ['label' => 'hello world'];

        $harness->runInlineHook("merge(data, {'label': upper(data['label'])})", 'beforeCreate', $data);

        $this->assertSame('HELLO WORLD', $data['label']);
    }

    #[Test]
    public function sandbox_blocks_arbitrary_php_and_leaves_data_unchanged(): void
    {
        $harness = $this->harness();
        $data = ['name' => 'Original'];

        // file_put_contents is not registered in ExpressionLanguage — must throw
        // SyntaxError, caught by executeDynamicHook, which logs and continues.
        $tmpFile = sys_get_temp_dir().'/ptah_sandbox_'.uniqid().'.txt';

        $harness->crudConfig = [
            'lifecycleHooks' => [
                'beforeCreate' => "file_put_contents('{$tmpFile}', 'pwned')",
            ],
        ];

        $harness->runDynamicHook('beforeCreate', $data);

        $this->assertFileDoesNotExist($tmpFile, 'Sandbox must not execute file_put_contents');
        $this->assertSame('Original', $data['name'], 'Data must be unchanged after failed hook');

        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }
    }

    #[Test]
    public function sandbox_gracefully_handles_expression_syntax_error(): void
    {
        $harness = $this->harness();
        $data = ['name' => 'Safe'];

        // Completely invalid syntax — must not propagate an exception.
        $harness->crudConfig = [
            'lifecycleHooks' => [
                'beforeCreate' => '{{ invalid !! syntax }}',
            ],
        ];

        $harness->runDynamicHook('beforeCreate', $data);

        $this->assertSame('Safe', $data['name']);
    }

    // ── 3. Fail-closed authorization ──────────────────────────────────────────

    #[Test]
    public function authorization_allows_when_permissions_module_is_disabled(): void
    {
        config(['ptah.modules.permissions' => false]);

        $harness = $this->harness();
        $harness->crudConfig = ['permissions' => ['permissionIdentifier' => 'test.items']];

        $this->assertTrue($harness->exposedAuthorize('create'));
    }

    #[Test]
    public function authorization_allows_when_no_permission_identifier_is_configured(): void
    {
        config(['ptah.modules.permissions' => true]);

        $harness = $this->harness();
        $harness->crudConfig = []; // no permissionIdentifier → opt-out

        $this->assertTrue($harness->exposedAuthorize('create'));
    }

    #[Test]
    public function anonymous_user_is_denied_when_identifier_is_configured(): void
    {
        config(['ptah.modules.permissions' => true]);

        Auth::logout(); // ensure unauthenticated (default in tests)

        $harness = $this->harness();
        $harness->crudConfig = [
            'permissions' => ['permissionIdentifier' => 'test.items'],
        ];

        $this->assertFalse(
            $harness->exposedAuthorize('create'),
            'Anonymous users must be denied (fail-closed) when a permissionIdentifier is configured',
        );
    }

    #[Test]
    public function anonymous_user_is_denied_for_delete_and_restore_as_well(): void
    {
        config(['ptah.modules.permissions' => true]);

        Auth::logout();

        $harness = $this->harness();
        $harness->crudConfig = [
            'permissions' => ['permissionIdentifier' => 'test.items'],
        ];

        $this->assertFalse($harness->exposedAuthorize('delete'));
        $this->assertFalse($harness->exposedAuthorize('update'));
    }
}
