<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Api\Admin;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\Company;
use Ptah\Models\Department;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

class CompanyApiUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];
}

/**
 * The company area through the API (1.59.0): companies, departments and the
 * company switcher. The Livewire components are the engine — these tests pin
 * the contract and that their guards hold through it.
 */
class CompanyApiTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('ptah.crud_api.enabled', true);
        $app['config']->set('ptah.crud_api.middleware', ['web', 'auth']);
        $app['config']->set('ptah.modules.company', true);
        $app['config']->set('ptah.modules.permissions', true);
        $app['config']->set('auth.providers.users.model', CompanyApiUser::class);
    }

    private function user(): CompanyApiUser
    {
        return CompanyApiUser::forceCreate([
            'name' => 'Ana',
            'email' => 'ana'.uniqid().'@example.test',
            'password' => 'x',
        ]);
    }

    private function grant(CompanyApiUser $user, ?Company $company, bool $master = false): void
    {
        $role = Role::create(['name' => 'Papel '.uniqid(), 'is_active' => true, 'is_master' => $master]);

        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'company_id' => $company?->id, 'is_active' => true]);
    }

    private function master(): CompanyApiUser
    {
        $user = $this->user();
        $this->grant($user, null, true);
        $this->actingAs($user);

        return $user;
    }

    // ── Companies ───────────────────────────────────────────────────────────

    #[Test]
    public function the_company_list_is_searched_sorted_and_paginated(): void
    {
        $this->master();
        CompanyFactory::new()->create(['name' => 'Beta Ltda', 'email' => 'b@beta.test', 'label' => 'BET']);
        CompanyFactory::new()->create(['name' => 'Alfa SA', 'label' => null, 'is_default' => true]);
        for ($i = 0; $i < 20; $i++) {
            CompanyFactory::new()->create(['name' => 'Zeta '.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $first = $this->getJson('/ptah/api/admin/companies')
            ->assertOk()
            ->assertJsonPath('meta.total', 22)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.name', 'Alfa SA')
            ->assertJsonPath('data.0.label_display', 'AS')
            ->assertJsonPath('data.0.is_default', true)
            ->assertJsonPath('tax_types', ['cnpj', 'cpf', 'ein', 'vat', 'other']);
        // Whitelisted: nothing internal of the component or the model.
        $this->assertSame(['id', 'name', 'label', 'label_display', 'email', 'phone', 'tax_id', 'tax_type', 'is_default', 'is_active'], array_keys($first->json('data.0')));

        $this->getJson('/ptah/api/admin/companies?page=2')->assertJsonPath('meta.page', 2)->assertJsonCount(2, 'data');
        // A page past the last one shows the last one.
        $this->getJson('/ptah/api/admin/companies?page=9')->assertJsonPath('meta.page', 2);
        $this->getJson('/ptah/api/admin/companies?sort=name&direction=desc')->assertJsonPath('data.0.name', 'Zeta 19');
        $this->getJson('/ptah/api/admin/companies?search=beta.test')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.label_display', 'BET');
    }

    #[Test]
    public function a_forged_sort_column_or_direction_is_refused(): void
    {
        $this->master();

        $this->getJson('/ptah/api/admin/companies?sort=password')->assertStatus(422)->assertJsonValidationErrors(['sort']);
        $this->getJson('/ptah/api/admin/companies?direction=up')->assertStatus(422)->assertJsonValidationErrors(['direction']);
        $this->getJson('/ptah/api/admin/departments?sort=id')->assertStatus(422)->assertJsonValidationErrors(['sort']);
    }

    #[Test]
    public function a_company_is_created_with_the_screens_rules(): void
    {
        $this->master();

        $this->postJson('/ptah/api/admin/companies', ['name' => '', 'email' => 'nope'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);

        // A tax type outside the screen's list is refused.
        $this->postJson('/ptah/api/admin/companies', ['name' => 'Acme', 'tax_type' => 'ssn'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tax_type']);

        $this->postJson('/ptah/api/admin/companies', ['name' => 'Acme', 'label' => 'acm', 'tax_type' => 'cpf', 'tax_id' => '123', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('messages.0.level', 'success');

        $company = Company::where('name', 'Acme')->firstOrFail();
        $this->assertSame('ACM', $company->label);
        $this->assertSame('cpf', $company->tax_type);

        // The label is unique.
        $this->postJson('/ptah/api/admin/companies', ['name' => 'Outra', 'label' => 'ACM'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['label']);
    }

    #[Test]
    public function a_company_is_shown_and_updated(): void
    {
        $this->master();
        $company = CompanyFactory::new()->create(['name' => 'Acme', 'label' => 'ACM', 'email' => 'a@acme.test']);

        $this->getJson("/ptah/api/admin/companies/{$company->id}")
            ->assertOk()
            ->assertJsonPath('form.name', 'Acme')
            ->assertJsonPath('form.label', 'ACM')
            ->assertJsonPath('form.is_active', true);

        // Its own label does not collide with itself.
        $this->putJson("/ptah/api/admin/companies/{$company->id}", ['name' => 'Acme Corp', 'label' => 'ACM', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $company->refresh();
        $this->assertSame('Acme Corp', $company->name);
        $this->assertFalse($company->is_active);
        $this->assertSame('a@acme.test', $company->email, 'A field not sent keeps its value.');

        $this->getJson('/ptah/api/admin/companies/999999')->assertNotFound();
    }

    #[Test]
    public function the_default_company_cannot_be_deleted(): void
    {
        $this->master();
        $default = CompanyFactory::new()->create(['is_default' => true]);
        $other = CompanyFactory::new()->create();

        $this->deleteJson("/ptah/api/admin/companies/{$default->id}")
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'The default company cannot be deleted.');
        $this->assertNotSoftDeleted($default);

        $this->deleteJson("/ptah/api/admin/companies/{$other->id}")->assertOk()->assertJsonPath('ok', true);
        $this->assertSoftDeleted($other);
    }

    #[Test]
    public function only_who_may_manage_the_structure_reaches_the_companies(): void
    {
        $company = CompanyFactory::new()->create();

        // Signed out.
        $this->getJson('/ptah/api/admin/companies')->assertUnauthorized();

        // Signed in, not master: refused by the component's boot().
        $user = $this->user();
        $this->grant($user, $company);
        $this->actingAs($user);

        $this->getJson('/ptah/api/admin/companies')->assertForbidden();
        $this->postJson('/ptah/api/admin/companies', ['name' => 'Hack'])->assertForbidden();
        $this->putJson("/ptah/api/admin/companies/{$company->id}", ['name' => 'Hack'])->assertForbidden();
        $this->deleteJson("/ptah/api/admin/companies/{$company->id}")->assertForbidden();

        $this->assertDatabaseMissing('ptah_companies', ['name' => 'Hack']);
        $this->assertNotSoftDeleted($company);
    }

    // ── Departments ─────────────────────────────────────────────────────────

    #[Test]
    public function departments_are_listed_created_updated_and_deleted(): void
    {
        $this->master();
        $sales = Department::create(['name' => 'Vendas', 'description' => 'Time comercial', 'is_active' => true]);
        Role::create(['name' => 'Vendedor', 'department_id' => $sales->id, 'is_active' => true]);
        Department::create(['name' => 'Compras', 'is_active' => false]);

        $this->getJson('/ptah/api/admin/departments')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Compras')
            ->assertJsonPath('data.0.is_active', false)
            ->assertJsonPath('data.1.roles_count', 1)
            ->assertJsonPath('data.1.description', 'Time comercial');
        $this->getJson('/ptah/api/admin/departments?search=Vend')->assertJsonPath('meta.total', 1);

        $this->postJson('/ptah/api/admin/departments', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
        $this->postJson('/ptah/api/admin/departments', ['name' => 'RH', 'description' => 'Pessoas', 'is_active' => true])
            ->assertOk()
            ->assertJsonPath('ok', true);
        $hr = Department::where('name', 'RH')->firstOrFail();

        $this->getJson("/ptah/api/admin/departments/{$hr->id}")->assertOk()->assertJsonPath('form.description', 'Pessoas');
        $this->putJson("/ptah/api/admin/departments/{$hr->id}", ['name' => 'Recursos Humanos', 'description' => '', 'is_active' => false])->assertOk();
        $hr->refresh();
        $this->assertSame('Recursos Humanos', $hr->name);
        $this->assertNull($hr->description);
        $this->assertFalse($hr->is_active);

        $this->deleteJson("/ptah/api/admin/departments/{$hr->id}")->assertOk()->assertJsonPath('ok', true);
        $this->assertSoftDeleted($hr);

        // A record that is gone: the screen's own error toast, refused.
        $this->deleteJson("/ptah/api/admin/departments/{$hr->id}")->assertStatus(422)->assertJsonPath('ok', false);
    }

    #[Test]
    public function departments_are_master_only(): void
    {
        $dept = Department::create(['name' => 'Vendas', 'is_active' => true]);
        $user = $this->user();
        $this->grant($user, null);
        $this->actingAs($user);

        $this->getJson('/ptah/api/admin/departments')->assertForbidden();
        $this->postJson('/ptah/api/admin/departments', ['name' => 'Hack'])->assertForbidden();
        $this->deleteJson("/ptah/api/admin/departments/{$dept->id}")->assertForbidden();
        $this->assertDatabaseMissing('ptah_departments', ['name' => 'Hack']);
        $this->assertNotSoftDeleted($dept);
    }

    // ── Company switcher ────────────────────────────────────────────────────

    #[Test]
    public function the_switcher_lists_only_my_companies_and_switches_among_them(): void
    {
        $mine = CompanyFactory::new()->create(['name' => 'Minha', 'label' => null]);
        $also = CompanyFactory::new()->create(['name' => 'Segunda', 'label' => 'SEG']);
        $theirs = CompanyFactory::new()->create(['name' => 'Alheia', 'is_default' => true]);
        $user = $this->user();
        $this->grant($user, $mine);
        $this->grant($user, $also);
        $this->actingAs($user);

        $answer = $this->getJson('/ptah/api/admin/company-switcher')->assertOk();
        $this->assertEqualsCanonicalizing([$mine->id, $also->id], array_column($answer->json('companies'), 'id'));
        $this->assertContains($answer->json('active_id'), [$mine->id, $also->id]);
        $this->assertSame(['id', 'name', 'label', 'is_default'], array_keys($answer->json('companies.0')));

        $this->postJson('/ptah/api/admin/company-switcher', ['id' => $also->id])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('active_id', $also->id);
        $this->assertSame($also->id, ptah_company_id());

        // A forged id: a company the user does not belong to is refused.
        $this->postJson('/ptah/api/admin/company-switcher', ['id' => $theirs->id])->assertForbidden();
        $this->assertSame($also->id, ptah_company_id());

        $this->postJson('/ptah/api/admin/company-switcher', ['id' => 'x'])->assertStatus(422)->assertJsonValidationErrors(['id']);
    }

    #[Test]
    public function the_switcher_needs_a_signed_in_user(): void
    {

        $this->getJson('/ptah/api/admin/company-switcher')->assertUnauthorized();
        $this->postJson('/ptah/api/admin/company-switcher', ['id' => 1])->assertUnauthorized();
    }
}
