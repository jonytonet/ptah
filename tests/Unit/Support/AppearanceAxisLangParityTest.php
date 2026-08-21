<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\AppearancePresets;

/**
 * Onda B — i18n coverage for the 2 new global appearance axes (densidade,
 * tamanho de fonte): every key `pt_BR/ui.php` defines for these axes must
 * also exist in `en/ui.php` (and vice-versa), and neither locale may ship an
 * empty translation for one of them. Scoped to just these axes' keys on
 * purpose — asserting full-file key parity is a separate concern this test
 * does not take on.
 */
class AppearanceAxisLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        $keys = ['profile_appearance_density_label', 'profile_appearance_fontsize_label'];

        foreach (AppearancePresets::DENSITY as $slug) {
            $keys[] = 'profile_appearance_density_'.$slug;
        }

        foreach (AppearancePresets::FONTSIZE as $slug) {
            $keys[] = 'profile_appearance_fontsize_'.$slug;
        }

        return $keys;
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
}
