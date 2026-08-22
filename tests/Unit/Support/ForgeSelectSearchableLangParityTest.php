<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * i18n coverage for forge-select's `searchable` prop: the filter input's
 * aria-label (new key) and the "no results" message it reuses (pre-existing
 * key, already used elsewhere in the package — checked here too since this
 * is the first Blade component consumer of it).
 */
class ForgeSelectSearchableLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        return [
            'forge_select_filter_aria',
            'no_results',
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
    public function the_two_locales_actually_differ(): void
    {
        $ptBr = self::lang('pt_BR');
        $en = self::lang('en');

        $this->assertNotSame(
            $en['forge_select_filter_aria'],
            $ptBr['forge_select_filter_aria'],
            'forge_select_filter_aria: pt_BR e en tem o mesmo texto — provavel copia esquecida sem traducao.'
        );
    }
}
