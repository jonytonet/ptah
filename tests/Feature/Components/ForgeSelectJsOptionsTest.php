<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Components;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * 1.46.5 (Engepecas): `options: {{ json_encode($options) }}` — json_encode
 * returns false on invalid UTF-8 in a label, and the whole x-data became
 * `options: ,`, a syntax error that killed the select. Js::from with
 * JSON_INVALID_UTF8_SUBSTITUTE keeps the select alive (U+FFFD) and escapes
 * quotes/tags for the attribute.
 */
class ForgeSelectJsOptionsTest extends TestCase
{
    #[Test]
    public function options_are_emitted_through_js_from(): void
    {
        $html = Blade::render('<x-forge-select name="s" :options="$o" />', ['o' => [
            ['value' => 1, 'label' => "Pe\xB1as"],   // byte latin1 solto: UTF-8 invalido
            ['value' => 2, 'label' => "D'Avila \"SA\" </div>"],
        ]]);

        $this->assertStringNotContainsString('options: ,', $html, 'json_encode false: x-data quebrado.');
        $this->assertStringContainsString('Pe\\\\ufffdas', $html, 'O byte invalido vira U+FFFD.');
        $this->assertStringNotContainsString('</div>', strtok((string) strstr($html, 'options:'), "\n"), 'Rotulo escapado, nao fecha a tag.');
    }
}
