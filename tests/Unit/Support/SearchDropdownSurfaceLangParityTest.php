<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * i18n coverage for the BaseCrud SearchDropdown configuration surface added
 * by this feature: the new CrudConfig editor "sd" tab fields (initWithData,
 * labelThree, start list, arraySearch, masks) and the form-preview lazy-load
 * badge.
 */
class SearchDropdownSurfaceLangParityTest extends TestCase
{
    /** @return list<string> */
    private static function expectedKeys(): array
    {
        return [
            'cfg_col_sd_label_three',
            'cfg_col_sd_init_with_data',
            'cfg_col_sd_init_with_data_hint',
            'cfg_col_sd_start_list',
            'cfg_col_sd_start_list_bottom',
            'cfg_col_sd_start_list_top',
            'cfg_col_sd_array_search',
            'cfg_col_sd_array_search_hint',
            'cfg_col_sd_mask_one',
            'cfg_col_sd_mask_two',
            'cfg_col_sd_mask_three',
            'cfg_preview_sd_lazy',
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
