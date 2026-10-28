<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\ColumnKeys;
use Ptah\Support\IdeAssets;
use Ptah\Tests\TestCase;

/**
 * The files the VS Code extension reads from vendor/ must be what the code
 * generates — an editor completing a key the runtime dropped is the drift
 * this package keeps paying for. `PTAH_REGEN_IDE=1` rewrites them.
 */
class IdeAssetsTest extends TestCase
{
    #[Test]
    public function the_committed_ide_files_match_the_code(): void
    {
        foreach (IdeAssets::files() as $relative => $contents) {
            $path = IdeAssets::root().'/'.$relative;

            if (getenv('PTAH_REGEN_IDE') === '1') {
                @mkdir(dirname($path), 0755, true);
                file_put_contents($path, $contents);
            }

            $this->assertFileExists($path, "{$relative} nao existe — rode com PTAH_REGEN_IDE=1.");
            $this->assertSame(
                $contents,
                str_replace("\r\n", "\n", (string) file_get_contents($path)),
                "{$relative} divergiu do codigo — rode com PTAH_REGEN_IDE=1 e commite."
            );
        }
    }

    #[Test]
    public function the_schema_completes_every_column_key_the_runtime_reads(): void
    {
        $schema = json_decode((string) file_get_contents(IdeAssets::root().'/resources/ide/crud-config.schema.json'), true);
        $props = $schema['properties']['cols']['items']['properties'];

        foreach (ColumnKeys::KNOWN as $key) {
            $this->assertArrayHasKey($key, $props);
        }
        $this->assertArrayNotHasKey('colsSortable', $props, 'colsSortable nao e lido pelo runtime.');
        $this->assertArrayHasKey('deprecationMessage', $schema['properties']['actions']);
        $this->assertArrayHasKey('deprecationMessage', $schema['properties']['filters']);
        $this->assertSame(['table', 'first', 'second'], $schema['properties']['joins']['items']['required']);
    }

    #[Test]
    public function the_manifest_carries_each_component_with_its_props(): void
    {
        $manifest = IdeAssets::components()['components'];

        $this->assertSame('x-forge-button', $manifest['forge-button']['tag']);
        $this->assertContains('href', array_column($manifest['forge-button']['props'], 'name'));
        $this->assertContains('rows', array_column($manifest['forge-table']['props'], 'name'));
        $this->assertStringContainsString('Props:', $manifest['forge-stat-card']['doc']);

        $files = glob(IdeAssets::root().'/resources/views/components/forge-*.blade.php') ?: [];
        $this->assertCount(count($files), $manifest);
    }
}
