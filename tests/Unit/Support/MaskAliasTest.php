<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\MaskPresets;
use Ptah\Support\PtahMask;
use Ptah\Tests\TestCase;

/**
 * Achado #9 do PetPlace: `colsMask: "phone"` com o preset br (que so tinha
 * `telefone`) renderizava sem mascara, calado.
 */
class MaskAliasTest extends TestCase
{
    protected function tearDown(): void
    {
        PtahMask::flush();

        parent::tearDown();
    }

    #[Test]
    public function the_br_preset_answers_to_the_english_names_too(): void
    {
        PtahMask::preset('br');

        foreach (MaskPresets::BR_ALIASES as $alias => $name) {
            $this->assertTrue(PtahMask::has($alias), "{$alias} deveria existir no preset br.");
            $without = fn (?array $m) => array_diff_key((array) $m, ['name' => true]);
            $this->assertSame($without(PtahMask::get($name)), $without(PtahMask::get($alias)), "{$alias} deveria ser o mesmo formato de {$name}.");
        }

        $this->assertSame('(11) 98765-4321', PtahMask::format('phone', '11987654321'));
        $this->assertSame('01310-100', PtahMask::format('zipcode', '01310100'));
    }

    #[Test]
    public function a_host_definition_under_the_alias_name_still_wins(): void
    {
        PtahMask::define('phone', ['pattern' => '000 000 000', 'store' => 'digits']);
        PtahMask::preset('br');

        $this->assertSame('119 876 543', PtahMask::format('phone', '119876543'));
    }
}
