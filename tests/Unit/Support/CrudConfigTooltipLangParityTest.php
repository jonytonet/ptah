<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * i18n coverage for the CrudConfig editor's field tooltips (`cfg_tip_*`
 * keys): every key actually referenced by crud-config.blade.php must exist
 * in both locales, hold a non-empty value, and differ between them — same
 * shape as CrudNotificationLangParityTest, but the key list is derived
 * straight from the view instead of hand-maintained, since this set is an
 * order of magnitude larger and a hand-kept list would drift immediately.
 */
class CrudConfigTooltipLangParityTest extends TestCase
{
    private const VIEW_RELATIVE = 'resources/views/livewire/base-crud/crud-config.blade.php';

    /**
     * `cfg_tip_valid_*` checkboxes resolve their key dynamically
     * (`'cfg_tip_valid_'.$rule`) from a loop over these ten validation
     * rules — grepping the view only finds the literal prefix, so the
     * concrete keys are listed here explicitly.
     *
     * @return list<string>
     */
    private static function dynamicValidationKeys(): array
    {
        return array_map(
            static fn (string $rule): string => 'cfg_tip_valid_'.$rule,
            ['email', 'url', 'integer', 'numeric', 'cpf', 'cnpj', 'phone', 'alpha', 'alphanum', 'ncm']
        );
    }

    /** @return list<string> */
    private static function expectedKeys(): array
    {
        $path = dirname(__DIR__, 3).'/'.self::VIEW_RELATIVE;
        $source = file_get_contents($path);

        if ($source === false) {
            throw new RuntimeException('CrudConfigTooltipLangParityTest: falha ao ler '.$path);
        }

        preg_match_all('/cfg_tip_[a-zA-Z_]*/', $source, $matches);

        $keys = array_unique($matches[0]);
        $keys = array_filter($keys, static fn (string $key): bool => $key !== 'cfg_tip_valid_');
        $keys = array_merge($keys, self::dynamicValidationKeys());
        $keys = array_unique($keys);
        sort($keys);

        if ($keys === []) {
            throw new RuntimeException('CrudConfigTooltipLangParityTest: nenhuma chave cfg_tip_* encontrada na view — o parser quebrou.');
        }

        return array_values($keys);
    }

    /** @return array<string, mixed> */
    private static function lang(string $locale): array
    {
        return require dirname(__DIR__, 3)."/resources/lang/{$locale}/ui.php";
    }

    #[Test]
    public function every_tooltip_key_exists_in_pt_br_and_en_with_a_non_empty_value(): void
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
    public function the_two_locales_actually_differ_for_every_tooltip_key(): void
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
