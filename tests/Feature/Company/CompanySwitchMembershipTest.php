<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Company;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Support\Facades\Session;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\Company\CompanySwitcher;
use Ptah\Models\Company;
use Ptah\Models\Role;
use Ptah\Models\UserRole;
use Ptah\Services\Company\CompanyService;
use Ptah\Tests\Factories\CompanyFactory;
use Ptah\Tests\TestCase;

class SwitchUser extends AuthUser
{
    protected $table = 'users';

    protected $guarded = [];
}

/**
 * Switching company did not check that the user belonged to it.
 *
 * `CompanySwitcher::switchTo(int $id)` called `setActive()`, which checked only
 * that the company existed and was active. The switcher also LISTED every
 * company, to every user. `BaseCrud::mount()` takes `ptah_company_id()` as its
 * tenant scope, so `switchTo(7)` put any user into company 7 — its listing, its
 * export. In a multi-tenant host that is the isolation between customers.
 *
 * `initSession()`, which the switcher calls on mount, had the same flaw by
 * another road: it put the user in the DEFAULT company, whether or not they had
 * any role there.
 *
 * ── Two traps the fix had to avoid ───────────────────────────────────────
 *
 * 1. `getUserCompanies()` returns only roles with a non-null company. A user
 *    whose only role is GLOBAL (`company_id` NULL) — which
 *    `UserRole::scopeForCompany()` applies in every company — would have been
 *    locked out of every company by a fix built on it alone.
 * 2. In BaseCrud, `companyFilter = 0` means NO tenant scope. Clearing the
 *    session for a user with no company would have turned "sees one company it
 *    should not" into "sees all of them".
 */
class CompanySwitchMembershipTest extends TestCase
{
    private Company $mine;

    private Company $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ptah.modules.company', true);

        $this->mine = CompanyFactory::new()->create(['name' => 'Minha Empresa', 'is_default' => false]);
        $this->theirs = CompanyFactory::new()->create(['name' => 'Empresa Alheia', 'is_default' => true]);
    }

    private function user(): SwitchUser
    {
        return SwitchUser::forceCreate([
            'name' => 'Operador',
            'email' => 'op'.uniqid().'@example.com',
            'password' => 'x',
        ]);
    }

    private function grant(SwitchUser $user, ?Company $company, bool $master = false): void
    {
        $role = Role::create(['name' => 'Papel '.uniqid(), 'is_active' => true, 'is_master' => $master]);

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'company_id' => $company?->id,
            'is_active' => true,
        ]);
    }

    private function service(): CompanyService
    {
        return app(CompanyService::class);
    }

    // ── The attack ─────────────────────────────────────────────────────────

    #[Test]
    public function a_user_cannot_switch_into_a_company_they_do_not_belong_to(): void
    {
        $user = $this->user();
        $this->grant($user, $this->mine);
        $this->actingAs($user);

        Session::put(config('ptah.permissions.company_session_key', 'ptah_company_id'), $this->mine->id);

        $this->service()->setActive($this->theirs->id);

        $this->assertSame($this->mine->id, ptah_company_id(), 'O usuario entrou numa empresa de que nao participa.');
    }

    #[Test]
    public function the_switcher_does_not_even_list_a_foreign_company(): void
    {
        // A lista inteira de tenants ia no snapshot publico do componente, para
        // o navegador de qualquer usuario.
        $user = $this->user();
        $this->grant($user, $this->mine);
        $this->actingAs($user);

        $component = Livewire::test(CompanySwitcher::class);

        // Medido na PROPRIEDADE, que e publica e vai no snapshot: e ali que a
        // lista de tenants vazava, renderizada ou nao. A primeira versao deste
        // teste procurava o nome no HTML e falhou na ancora — com uma empresa
        // so, o switcher nem desenha a lista.
        $ids = collect($component->get('companies'))->pluck('id')->all();

        $this->assertSame([$this->mine->id], $ids, 'O switcher ofereceu empresa de que o usuario nao participa.');
        $this->assertStringNotContainsString('Empresa Alheia', $component->html());
    }

    #[Test]
    public function an_anonymous_visitor_is_offered_no_company_at_all(): void
    {
        // Achado pelo MobileNavbarConsolidationTest, que renderizava o
        // switcher SEM usuario logado e esperava ver todas as empresas: no
        // codigo publicado, quem nao estava logado recebia a lista de tenants.
        $component = Livewire::test(CompanySwitcher::class);

        $this->assertSame([], collect($component->get('companies'))->pluck('id')->all());
        $this->assertStringNotContainsString('Empresa Alheia', $component->html());
        $this->assertStringNotContainsString('Minha Empresa', $component->html());
    }

    #[Test]
    public function first_entry_does_not_land_the_user_in_a_company_they_are_not_in(): void
    {
        // A outra estrada: initSession() escolhia a empresa PADRAO, que aqui e a
        // alheia, sem olhar pertencimento.
        $user = $this->user();
        $this->grant($user, $this->mine);
        $this->actingAs($user);

        $this->service()->initSession();

        $this->assertSame($this->mine->id, ptah_company_id());
    }

    #[Test]
    public function a_session_already_pointing_at_a_foreign_company_is_moved(): void
    {
        // Gravada antes desta correcao, ou por um papel revogado depois.
        $user = $this->user();
        $this->grant($user, $this->mine);
        $this->actingAs($user);

        Session::put(config('ptah.permissions.company_session_key', 'ptah_company_id'), $this->theirs->id);

        $this->service()->initSession();

        $this->assertSame($this->mine->id, ptah_company_id());
    }

    // ── Who may switch anywhere ────────────────────────────────────────────

    #[Test]
    public function a_member_switches_into_their_own_company(): void
    {
        $user = $this->user();
        $this->grant($user, $this->mine);
        $this->actingAs($user);

        $this->service()->setActive($this->mine->id);

        $this->assertSame($this->mine->id, ptah_company_id());
    }

    #[Test]
    public function a_master_may_switch_into_any_company(): void
    {
        $user = $this->user();
        $this->grant($user, null, master: true);
        $this->actingAs($user);

        $this->service()->setActive($this->theirs->id);

        $this->assertSame($this->theirs->id, ptah_company_id());
    }

    #[Test]
    public function a_global_role_is_not_locked_out(): void
    {
        // A armadilha 1: um papel global vale em toda empresa, e uma correcao
        // construida so sobre getUserCompanies() o trancaria fora de todas.
        $user = $this->user();
        $this->grant($user, null);
        $this->actingAs($user);

        $this->service()->setActive($this->theirs->id);

        $this->assertSame($this->theirs->id, ptah_company_id());
    }

    #[Test]
    public function a_user_with_no_company_is_not_widened_to_every_company(): void
    {
        // A armadilha 2: no BaseCrud, companyFilter = 0 e SEM escopo de tenant.
        // Deixar a sessao vazia faria este usuario ver os dados de TODAS as
        // empresas — pior do que o fallback de antes, que e a padrao.
        $user = $this->user();
        $this->actingAs($user);

        $this->service()->initSession();

        $this->assertGreaterThan(0, ptah_company_id(), 'A sessao ficou sem empresa: o BaseCrud listaria todos os tenants.');
    }

    // ── Without the permissions module ─────────────────────────────────────

    #[Test]
    public function without_rbac_every_active_company_is_still_switchable(): void
    {
        // Sem papeis nao ha como saber a quem a empresa pertence: o
        // comportamento de antes, preservado.
        config()->set('ptah.modules.permissions', false);
        $this->actingAs($this->user());

        $this->service()->setActive($this->theirs->id);

        $this->assertSame($this->theirs->id, ptah_company_id());
    }
}
