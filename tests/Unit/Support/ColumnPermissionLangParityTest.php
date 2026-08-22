<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Wave 3 — i18n coverage for the column-level permission editor (the
 * `colsPermission` select, its hint, and the lock-badge title in the columns
 * list). Every key `en/ui.php` defines for this feature must also exist in
 * `pt_BR/ui.php` (and vice-versa), non-empty in both. Scoped to just these
 * keys on purpose — full-file key parity is a separate concern.
 */
class ColumnPermissionLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        return [
            'cfg_col_permission_label',
            'cfg_col_permission_none',
            'cfg_col_permission_hint',
            'cfg_col_permission_badge_title',
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
    public function the_badge_title_key_carries_the_key_placeholder_in_both_locales(): void
    {
        $ptBr = self::lang('pt_BR');
        $en = self::lang('en');

        $this->assertStringContainsString(':key', $ptBr['cfg_col_permission_badge_title']);
        $this->assertStringContainsString(':key', $en['cfg_col_permission_badge_title']);
    }
}
