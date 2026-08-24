<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * i18n coverage for the CrudConfig editor's Notifications tab (Fase 2 of the
 * config-driven CRUD notifications plan): nav/tab labels, the "module off"
 * step-by-step card, the trait-missing warning, and every rule field.
 * Molde de NotificationLangParityTest.
 */
class CrudNotificationLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        return [
            'cfg_nav_notifications',
            'cfg_tab_title_notifications',
            'cfg_tab_desc_notifications',
            'cfg_notif_off_title',
            'cfg_notif_off_desc',
            'cfg_notif_off_step_env',
            'cfg_notif_off_step_publish',
            'cfg_notif_off_step_migrate',
            'cfg_notif_trait_missing',
            'cfg_notif_empty',
            'cfg_notif_empty_hint',
            'cfg_notif_edit_btn',
            'cfg_notif_remove_btn',
            'cfg_notif_remove_confirm',
            'cfg_notif_form_new',
            'cfg_notif_form_editing',
            'cfg_notif_event_label',
            'cfg_notif_event_created',
            'cfg_notif_event_updated',
            'cfg_notif_event_deleted',
            'cfg_notif_type_label',
            'cfg_notif_type_info',
            'cfg_notif_type_success',
            'cfg_notif_type_warning',
            'cfg_notif_type_danger',
            'cfg_notif_audience_label',
            'cfg_notif_audience_user',
            'cfg_notif_audience_role',
            'cfg_notif_audience_staff',
            'cfg_notif_audience_value_label',
            'cfg_notif_audience_value_placeholder',
            'cfg_notif_audience_count',
            'cfg_notif_title_label',
            'cfg_notif_body_label',
            'cfg_notif_placeholders_hint',
            'cfg_notif_url_label',
            'cfg_notif_action_label_label',
            'cfg_notif_category_label',
            'cfg_notif_icon_label',
            'cfg_notif_notify_self_label',
            'cfg_notif_notify_self_hint',
            'cfg_notif_cancel_edit',
            'cfg_notif_btn_update',
            'cfg_notif_btn_add',
        ];
    }

    /** @return array<string, mixed> */
    private static function lang(string $locale): array
    {
        return require dirname(__DIR__, 3)."/resources/lang/{$locale}/ui.php";
    }

    #[Test]
    public function every_expected_key_exists_in_pt_br_and_en_with_a_non_empty_value(): void
    {
        $ptBr = self::lang('pt_BR');
        $en = self::lang('en');

        foreach (self::expectedKeys() as $key) {
            $this->assertArrayHasKey($key, $ptBr, "Chave [{$key}] ausente em resources/lang/pt_BR/ui.php.");
            $this->assertArrayHasKey($key, $en, "Chave [{$key}] ausente em resources/lang/en/ui.php.");

            $this->assertNotSame('', trim((string) $ptBr[$key]), "Chave [{$key}] vazia em pt_BR/ui.php.");
            $this->assertNotSame('', trim((string) $en[$key]), "Chave [{$key}] vazia em en/ui.php.");
        }
    }

    #[Test]
    public function the_two_locales_actually_differ_for_every_key(): void
    {
        $ptBr = self::lang('pt_BR');
        $en = self::lang('en');

        foreach (self::expectedKeys() as $key) {
            $this->assertNotSame(
                $en[$key],
                $ptBr[$key],
                "{$key}: pt_BR e en tem o mesmo texto — provavel copia esquecida sem traducao."
            );
        }
    }
}
