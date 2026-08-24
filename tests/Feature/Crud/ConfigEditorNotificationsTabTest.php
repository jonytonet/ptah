<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\CrudConfig as CrudConfigComponent;
use Ptah\Models\CrudConfig;
use Ptah\Services\Permission\PermissionService;
use Ptah\Tests\TestCase;
use Ptah\Traits\SendsCrudNotifications;

/**
 * Two identical-shape stub models for the "trait ausente" warning coverage:
 * one uses SendsCrudNotifications, the other does not. Neither needs a real
 * table — the editor only instantiates the class (getKeyName()) or inspects
 * its traits, it never queries the model's own table.
 */
class NotificationEditorStubWithTrait extends Model
{
    use SendsCrudNotifications;

    protected $table = 'notification_editor_stub_with_trait';
}

class NotificationEditorStubWithoutTrait extends Model
{
    protected $table = 'notification_editor_stub_without_trait';
}

/**
 * Covers the visual CrudConfig editor's Notifications tab (Fase 2 of the
 * config-driven CRUD notifications plan):
 *
 *  - round-trip of 2 rules; editing one preserves the other;
 *  - a config with no `notifications` key at all still loads fine;
 *  - the "trait ausente" warning appears/disappears based on the resolved
 *    model class;
 *  - the "module off" card renders instead of the rule form when
 *    ptah.notifications.enabled is false.
 *
 * Molde de ConfigEditorSdTabTest (master PermissionService stub).
 */
class ConfigEditorNotificationsTabTest extends TestCase
{
    private function editor(string $model = 'Widget')
    {
        return Livewire::test(CrudConfigComponent::class, ['model' => $model]);
    }

    /** Binds a master PermissionService stub so save()/openModal() are allowed. */
    private function actAsMaster(): void
    {
        $stub = new class extends PermissionService
        {
            public function isMaster(mixed $user = null): bool
            {
                return true;
            }
        };

        $this->app->instance(PermissionService::class, $stub);
    }

    private function ruleA(): array
    {
        return [
            'event' => 'created',
            'audience' => 'staff',
            'audienceValue' => '',
            'title' => 'Novo registro: %name%',
        ];
    }

    private function ruleB(): array
    {
        return [
            'event' => 'deleted',
            'audience' => 'role',
            'audienceValue' => 'Financeiro',
            'title' => 'Removido: %name%',
        ];
    }

    // ── Round-trip ────────────────────────────────────────────────────────────

    #[Test]
    public function two_rules_survive_a_save_and_reload_round_trip(): void
    {
        $this->actAsMaster();

        $this->editor()
            ->call('openModal')
            ->set('formDataNotification', $this->ruleA())
            ->call('addNotificationRule')
            ->set('formDataNotification', $this->ruleB())
            ->call('addNotificationRule')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $rules = $stored->config['notifications']['rules'];

        $this->assertCount(2, $rules);
        $this->assertSame('created', $rules[0]['event']);
        $this->assertSame('Novo registro: %name%', $rules[0]['title']);
        $this->assertSame('deleted', $rules[1]['event']);
        $this->assertSame('Financeiro', $rules[1]['audienceValue']);
    }

    #[Test]
    public function editing_one_rule_preserves_the_other(): void
    {
        $this->actAsMaster();

        $component = $this->editor()
            ->call('openModal')
            ->set('formDataNotification', $this->ruleA())
            ->call('addNotificationRule')
            ->set('formDataNotification', $this->ruleB())
            ->call('addNotificationRule');

        $component
            ->call('editNotificationRule', 0)
            ->set('formDataNotification.title', 'Novo registro (editado): %name%')
            ->call('addNotificationRule')
            ->call('save');

        $stored = CrudConfig::where('model', 'Widget')->first();
        $rules = $stored->config['notifications']['rules'];

        $this->assertCount(2, $rules);
        $this->assertSame('Novo registro (editado): %name%', $rules[0]['title']);
        // Rule B, never touched, must survive untouched.
        $this->assertSame('deleted', $rules[1]['event']);
        $this->assertSame('Removido: %name%', $rules[1]['title']);
        $this->assertSame('Financeiro', $rules[1]['audienceValue']);
    }

    // ── Backward compatibility ───────────────────────────────────────────────

    #[Test]
    public function a_config_without_the_notifications_key_still_loads_fine(): void
    {
        CrudConfig::create([
            'model' => 'LegacyWidget',
            'route' => '',
            'config' => ['cols' => []],
        ]);

        $component = $this->editor('LegacyWidget')->call('openModal');

        $component->assertSet('notificationRules', []);
        $component->assertOk();
    }

    // ── Trait-missing warning ─────────────────────────────────────────────────

    #[Test]
    public function the_trait_missing_warning_appears_when_the_model_lacks_sendscrudnotifications(): void
    {
        config(['ptah.notifications.enabled' => true]);
        $this->actAsMaster();

        $html = $this->editor(NotificationEditorStubWithoutTrait::class)
            ->call('openModal')
            ->html();

        $this->assertStringContainsString(__('ptah::ui.cfg_notif_trait_missing', ['trait' => 'SendsCrudNotifications']), $html);
    }

    #[Test]
    public function the_trait_missing_warning_is_absent_when_the_model_uses_sendscrudnotifications(): void
    {
        config(['ptah.notifications.enabled' => true]);
        $this->actAsMaster();

        $html = $this->editor(NotificationEditorStubWithTrait::class)
            ->call('openModal')
            ->html();

        $this->assertStringNotContainsString(__('ptah::ui.cfg_notif_trait_missing', ['trait' => 'SendsCrudNotifications']), $html);
    }

    // ── Module-off card ───────────────────────────────────────────────────────

    #[Test]
    public function the_module_off_card_renders_instead_of_the_rule_form(): void
    {
        config(['ptah.notifications.enabled' => false]);
        $this->actAsMaster();

        $html = $this->editor()->call('openModal')->html();

        $this->assertStringContainsString(__('ptah::ui.cfg_notif_off_title'), $html);
        $this->assertStringNotContainsString('cfg_notif_btn_add', $html);
    }

    #[Test]
    public function the_rule_form_renders_when_the_module_is_enabled(): void
    {
        config(['ptah.notifications.enabled' => true]);
        $this->actAsMaster();

        $html = $this->editor()->call('openModal')->html();

        $this->assertStringNotContainsString(__('ptah::ui.cfg_notif_off_title'), $html);
        $this->assertStringContainsString(__('ptah::ui.cfg_notif_btn_add'), $html);
    }
}
