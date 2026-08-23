<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * i18n coverage for the FASE 3 notification bell (ptah-notification-bell):
 * every notif_* key must exist, be non-empty and actually differ between
 * en and pt_BR. Molde de SearchDropdownSurfaceLangParityTest.
 */
class NotificationLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        return [
            'notif_bell_title',
            'notif_empty',
            'notif_action_default',
            'notif_mark_all_read',
            'notif_view_all',
            'notif_dismiss',
            'notif_unread_badge_label',
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
