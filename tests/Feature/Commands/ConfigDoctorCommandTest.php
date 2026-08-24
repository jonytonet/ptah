<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\PageObject;
use Ptah\Models\PtahPage;
use Ptah\Models\Role;
use Ptah\Support\ModelKey;
use Ptah\Tests\TestCase;
use Ptah\Traits\SendsCrudNotifications;

class DoctorStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

class DoctorStubTwo extends Model
{
    protected $table = 'items';

    protected $fillable = ['name'];
}

/**
 * The `user` audience is only checkable when a user model resolves — see
 * ConfigDoctorCommand::userExists(), which deliberately reports NOTHING when it
 * cannot answer the question (an unresolvable model must not be read as a broken
 * rule). These tests therefore point the config at this stub.
 */
class DoctorTestUser extends Model
{
    protected $table = 'users';

    protected $fillable = ['name', 'email', 'password'];
}

/**
 * Carries SendsCrudNotifications — the counterpart of DoctorStub, so check 9
 * can be exercised on both sides of the trait question.
 */
class DoctorStubNotified extends Model
{
    use SendsCrudNotifications;

    protected $table = 'items';

    protected $fillable = ['name'];
}

/**
 * Covers ptah:config:doctor — the author-time audit that catches the silent
 * failures the per-model tooling can't: orphan (non-canonical) keys, malformed
 * configs, empty screens and global-vs-route ambiguity.
 */
class ConfigDoctorCommandTest extends TestCase
{
    private function seedConfig(string $model, string $route, array $config): void
    {
        CrudConfig::create(['model' => $model, 'route' => $route, 'config' => $config]);
    }

    private function goodConfig(): array
    {
        return ['cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Name', 'colsTipo' => 'text']]];
    }

    #[Test]
    public function clean_configs_pass(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')->assertExitCode(0);
    }

    #[Test]
    public function orphan_key_is_reported_then_fixed(): void
    {
        // Stored under the FQCN (backslashes) — the runtime reads the slash form.
        $this->seedConfig(DoctorStub::class, '', $this->goodConfig());
        $canonical = ModelKey::canonical(DoctorStub::class);

        // Without --fix: reported as an error.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('orphan key')
            ->assertExitCode(1);

        // With --fix: key rewritten to canonical, now clean.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $this->assertDatabaseHas('crud_configs', ['model' => $canonical]);
        $this->assertDatabaseMissing('crud_configs', ['model' => DoctorStub::class]);
    }

    #[Test]
    public function malformed_config_is_an_error(): void
    {
        // Column without colsNomeFisico → ConfigSchemaValidator rejects it.
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => [['colsTipo' => 'text']]]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('malformed')
            ->assertExitCode(1);
    }

    #[Test]
    public function empty_columns_warns_but_does_not_fail(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', ['cols' => []]);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('no columns')
            ->assertExitCode(0);
    }

    #[Test]
    public function global_plus_route_specific_is_flagged(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $this->seedConfig($canonical, '', $this->goodConfig());
        $this->seedConfig($canonical, 'invoices', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('route fallback')
            ->assertExitCode(0);
    }

    #[Test]
    public function legacy_rbac_key_is_reported_then_fixed(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['permissions'] = ['identifier' => 'pageDoctorStub'];
        $this->seedConfig($canonical, '', $config);

        // Without --fix: reported as an error, gate silently absent.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy RBAC key')
            ->assertExitCode(1);

        // With --fix: value migrated to the canonical key, legacy key removed.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $this->assertSame('pageDoctorStub', $row->config['permissions']['permissionIdentifier']);
        $this->assertArrayNotHasKey('identifier', $row->config['permissions']);

        // Re-run is clean.
        $this->artisan('ptah:config:doctor')->assertExitCode(0);
    }

    #[Test]
    public function legacy_styles_key_is_reported_then_migrated_idempotently(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['styles'] = [
            ['colsNomeFisico' => 'status', 'colsOperator' => 'eq', 'colsValue' => 'cancelled', 'colsCss' => 'background:red;'],
        ];
        $this->seedConfig($canonical, '', $config);

        // Without --fix: reported as an error, the rule never applies.
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy styles key')
            ->assertExitCode(1);

        // With --fix: the rule is normalised and folded into contitionStyles,
        // the legacy 'styles' key is dropped.
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $this->assertArrayNotHasKey('styles', $row->config);
        $this->assertSame(
            ['field' => 'status', 'condition' => '==', 'value' => 'cancelled', 'style' => 'background:red;'],
            $row->config['contitionStyles'][0]
        );

        // Re-run is clean and the stored config is byte-identical (idempotent).
        $before = $row->config;
        $this->artisan('ptah:config:doctor')->assertExitCode(0);
        $after = CrudConfig::where('model', $canonical)->first()->config;
        $this->assertSame($before, $after);
    }

    #[Test]
    public function unusable_row_style_warns_but_does_not_fail(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $config = $this->goodConfig();
        $config['contitionStyles'] = [
            ['field' => 'status', 'condition' => 'LIKE', 'value' => 'cancelled', 'style' => 'background:red;'],
        ];
        $this->seedConfig($canonical, '', $config);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unusable row style')
            ->assertExitCode(0);
    }

    #[Test]
    public function shared_permission_identifier_across_different_models_warns(): void
    {
        $configA = $this->goodConfig();
        $configA['permissions'] = ['permissionIdentifier' => 'pageShared'];
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $configA);

        $configB = $this->goodConfig();
        $configB['permissions'] = ['permissionIdentifier' => 'pageShared'];
        $this->seedConfig(ModelKey::canonical(DoctorStubTwo::class), '', $configB);

        // Warning only — does not fail the exit code, same as "no columns".
        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('permissionIdentifier collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function distinct_permission_identifiers_do_not_warn(): void
    {
        $configA = $this->goodConfig();
        $configA['permissions'] = ['permissionIdentifier' => 'pageStubA'];
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $configA);

        $configB = $this->goodConfig();
        $configB['permissions'] = ['permissionIdentifier' => 'pageStubB'];
        $this->seedConfig(ModelKey::canonical(DoctorStubTwo::class), '', $configB);

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('permissionIdentifier collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function shared_obj_key_across_different_pages_warns(): void
    {
        $pageA = PtahPage::create(['slug' => 'page-a', 'name' => 'Page A']);
        $pageB = PtahPage::create(['slug' => 'page-b', 'name' => 'Page B']);

        PageObject::create([
            'page_id' => $pageA->id,
            'section' => 'main',
            'obj_key' => 'shared.button',
            'obj_label' => 'Shared button',
            'obj_type' => 'button',
        ]);
        PageObject::create([
            'page_id' => $pageB->id,
            'section' => 'main',
            'obj_key' => 'shared.button',
            'obj_label' => 'Shared button',
            'obj_type' => 'button',
        ]);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('obj_key collision')
            ->assertExitCode(0);
    }

    #[Test]
    public function obj_key_scoped_to_a_single_page_does_not_warn(): void
    {
        $page = PtahPage::create(['slug' => 'page-solo', 'name' => 'Page Solo']);

        PageObject::create([
            'page_id' => $page->id,
            'section' => 'main',
            'obj_key' => 'solo.button',
            'obj_label' => 'Solo button',
            'obj_type' => 'button',
        ]);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->goodConfig());

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('obj_key collision')
            ->assertExitCode(0);
    }

    // ── colsPermission → unknown column permission key ─────────────────────

    #[Test]
    public function a_colspermission_naming_no_registered_pageobject_warns(): void
    {
        $config = $this->goodConfig();
        $config['cols'][0]['colsPermission'] = 'items.no_such_key';
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $config);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unknown column permission key')
            ->assertExitCode(0); // warning only — never fails the exit code
    }

    #[Test]
    public function a_colspermission_naming_a_registered_bare_obj_key_does_not_warn(): void
    {
        $page = PtahPage::create(['slug' => 'items-screen', 'name' => 'Items Screen']);
        PageObject::create([
            'page_id' => $page->id,
            'section' => 'main',
            'obj_key' => 'items.secret_amount',
            'obj_label' => 'Secret amount',
            'obj_type' => 'field',
        ]);

        $config = $this->goodConfig();
        $config['cols'][0]['colsPermission'] = 'items.secret_amount';
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $config);

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown column permission key')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_qualified_colspermission_resolving_to_a_registered_pageobject_does_not_warn(): void
    {
        $pageA = PtahPage::create(['slug' => 'page-a', 'name' => 'Page A']);
        $pageB = PtahPage::create(['slug' => 'page-b', 'name' => 'Page B']);

        // Same obj_key on two pages (a real collision) — only the QUALIFIED
        // form unambiguously names one of them.
        PageObject::create([
            'page_id' => $pageA->id, 'section' => 'main',
            'obj_key' => 'shared.button', 'obj_label' => 'Shared button', 'obj_type' => 'button',
        ]);
        PageObject::create([
            'page_id' => $pageB->id, 'section' => 'main',
            'obj_key' => 'shared.button', 'obj_label' => 'Shared button', 'obj_type' => 'button',
        ]);

        $config = $this->goodConfig();
        $config['cols'][0]['colsPermission'] = 'page-a::shared.button';
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $config);

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown column permission key')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_qualified_colspermission_naming_no_such_page_warns(): void
    {
        $config = $this->goodConfig();
        $config['cols'][0]['colsPermission'] = 'no-such-page::items.secret_amount';
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $config);

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unknown column permission key')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_empty_colspermission_never_warns(): void
    {
        $config = $this->goodConfig();
        $config['cols'][0]['colsPermission'] = '';
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $config);

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown column permission key')
            ->assertExitCode(0);
    }

    // ── 5e. SearchDropdown surface ───────────────────────────────────────────

    private function sdConfig(array $sdExtra): array
    {
        $config = $this->goodConfig();
        $config['cols'][] = array_merge([
            'colsNomeFisico' => 'supplier_id',
            'colsNomeLogico' => 'Supplier',
            'colsTipo' => 'searchdropdown',
            'colsSDModel' => 'Supplier',
        ], $sdExtra);

        return $config;
    }

    #[Test]
    public function invalid_cols_sd_limit_warns_but_does_not_fail(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDLimit' => 0]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('invalid colsSDLimit')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_valid_cols_sd_limit_does_not_warn(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDLimit' => 20]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('invalid colsSDLimit')
            ->assertExitCode(0);
    }

    #[Test]
    public function unknown_mask_warns(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDMaskOne' => 'not-a-real-mask']));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unknown mask')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_builtin_mask_does_not_warn(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDMaskOne' => 'cnpj']));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown mask')
            ->assertExitCode(0);
    }

    #[Test]
    public function legacy_sd_dialect_key_is_reported_then_fixed_idempotently(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $this->seedConfig($canonical, '', $this->sdConfig([
            'colsSDMode' => 'model',
            'colsSDValueField' => 'id',
            'colsSDLabelField' => 'name',
            'colsSDOrderBy' => 'name asc',
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('legacy dialect key')
            ->assertExitCode(0); // warning only

        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $col = collect($row->config['cols'])->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertSame('model', $col['colsSDTipo']);
        $this->assertSame('id', $col['colsSDValor']);
        $this->assertSame('name', $col['colsSDLabel']);
        $this->assertSame('name asc', $col['colsSDOrder']);
        // Legacy keys preserved — additive only.
        $this->assertSame('model', $col['colsSDMode']);

        // Idempotent: a second --fix run changes nothing further.
        $before = $row->config;
        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);
        $after = CrudConfig::where('model', $canonical)->first()->config;
        $this->assertSame($before, $after);
    }

    #[Test]
    public function cols_sd_mode_both_is_not_promoted_to_the_canonical_key(): void
    {
        $canonical = ModelKey::canonical(DoctorStub::class);
        $this->seedConfig($canonical, '', $this->sdConfig(['colsSDMode' => 'both']));

        $this->artisan('ptah:config:doctor --fix')->assertExitCode(0);

        $row = CrudConfig::where('model', $canonical)->first();
        $col = collect($row->config['cols'])->firstWhere('colsNomeFisico', 'supplier_id');

        $this->assertArrayNotHasKey('colsSDTipo', $col);
    }

    #[Test]
    public function malformed_cols_sd_filters_warns(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDFilters' => 'not-json{']));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('malformed colsSDFilters')
            ->assertExitCode(0);
    }

    #[Test]
    public function valid_cols_sd_filters_does_not_warn(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig([
            'colsSDFilters' => '[{"field":"active","value":"S"}]',
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('malformed colsSDFilters')
            ->assertExitCode(0);
    }

    #[Test]
    public function unsafe_array_search_column_warns(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig([
            'colsSDArraySearch' => 'name; DROP TABLE items',
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('unsafe arraySearch column')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_safe_array_search_column_does_not_warn(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig([
            'colsSDArraySearch' => 'name,status',
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unsafe arraySearch column')
            ->assertExitCode(0);
    }

    #[Test]
    public function invalid_cols_sd_mode_warns(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig(['colsSDMode' => 'both']));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('invalid colsSDMode')
            ->assertExitCode(0);
    }

    #[Test]
    public function service_class_outside_configured_namespaces_warns(): void
    {
        config()->set('ptah.crud.sd_service_namespaces', ['App\\Services']);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig([
            'colsSDTipo' => 'service',
            'colsSDService' => 'Evil\\Namespace\\ExfilService',
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('service outside sd_service_namespaces')
            ->assertExitCode(0);
    }

    #[Test]
    public function service_class_inside_configured_namespaces_does_not_warn(): void
    {
        config()->set('ptah.crud.sd_service_namespaces', ['App\\Services']);

        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->sdConfig([
            'colsSDTipo' => 'service',
            'colsSDService' => 'App\\Services\\SupplierService',
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('service outside sd_service_namespaces')
            ->assertExitCode(0);
    }

    /**
     * Builds a config carrying notification rules on top of the good column set,
     * so check 9 is the only thing under test.
     *
     * @param  array<int, array<string, mixed>>  $rules
     * @return array<string, mixed>
     */
    private function configWithRules(array $rules): array
    {
        return $this->goodConfig() + ['notifications' => ['rules' => $rules]];
    }

    /**
     * @return array<string, mixed>
     */
    private function staffRule(array $overrides = []): array
    {
        return array_merge([
            'event' => 'created',
            'audience' => 'staff',
            'audienceValue' => '',
            'title' => 'Created: %name%',
        ], $overrides);
    }

    #[Test]
    public function rules_on_a_model_without_the_trait_are_reported(): void
    {
        // The defect a real consumer hit: the rules look right in the editor and
        // nothing ever fires, because nothing hooks the Eloquent events.
        $this->seedConfig(ModelKey::canonical(DoctorStub::class), '', $this->configWithRules([$this->staffRule()]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: model without the trait')
            ->assertExitCode(0); // warning only — the config file itself is valid
    }

    #[Test]
    public function rules_on_a_model_with_the_trait_are_not_reported(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([$this->staffRule()]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('model without the trait')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_audience_left_without_a_value_is_reported(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'user', 'audienceValue' => '']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: audience without a value')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_audience_naming_an_unknown_role_is_reported(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'role', 'audienceValue' => 'Financeiro']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: unknown role')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_existing_role_matches_case_insensitively(): void
    {
        // Same identity rule as ptah_has_role: case-insensitive and trimmed, but
        // NOT slugged — 'financeiro' matches 'FINANCEIRO', never 'financeiro-x'.
        Role::create(['name' => 'FINANCEIRO', 'is_active' => true]);

        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'role', 'audienceValue' => '  financeiro ']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown role')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_audience_naming_an_unknown_user_is_reported(): void
    {
        config(['ptah.permissions.user_model' => DoctorTestUser::class]);

        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'user', 'audienceValue' => '99999']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: unknown user')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_existing_user_id_is_not_reported(): void
    {
        config(['ptah.permissions.user_model' => DoctorTestUser::class]);

        $user = DoctorTestUser::create(['name' => 'Ana', 'email' => 'ana@example.test', 'password' => 'x']);

        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'user', 'audienceValue' => (string) $user->getKey()]),
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown user')
            ->assertExitCode(0);
    }

    #[Test]
    public function an_unresolvable_user_model_reports_nothing(): void
    {
        // The helper's contract: when the question cannot be answered, stay
        // quiet. A host without the referenced user model must not be told its
        // rules are broken.
        config(['ptah.permissions.user_model' => 'App\Models\NoSuchUser']);

        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['audience' => 'user', 'audienceValue' => '99999']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('unknown user')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_rule_with_an_empty_title_is_reported(): void
    {
        // The runtime drops such a rule (dispatchRule returns early), so it is a
        // rule that silently delivers nothing.
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([
            $this->staffRule(['title' => '   ']),
        ]));

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: empty title')
            ->assertExitCode(0);
    }

    #[Test]
    public function the_queue_note_appears_only_on_a_non_sync_connection(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->configWithRules([$this->staffRule()]));

        config()->set('queue.default', 'database');

        $this->artisan('ptah:config:doctor')
            ->expectsOutputToContain('notifications: queue')
            ->assertExitCode(0);

        config()->set('queue.default', 'sync');

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('notifications: queue')
            ->assertExitCode(0);
    }

    #[Test]
    public function the_queue_note_is_silent_when_no_config_has_rules(): void
    {
        $this->seedConfig(ModelKey::canonical(DoctorStubNotified::class), '', $this->goodConfig());

        config()->set('queue.default', 'database');

        $this->artisan('ptah:config:doctor')
            ->doesntExpectOutputToContain('notifications: queue')
            ->assertExitCode(0);
    }
}
