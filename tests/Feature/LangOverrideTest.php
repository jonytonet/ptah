<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;

/**
 * Validates the per-key translation override path — customise `ptah::ui.*` strings
 * WITHOUT freezing the whole file. Laravel merges the app's
 * lang/vendor/ptah/{locale}/ui.php OVER the package's (array_replace_recursive),
 * so a partial override wins for its keys and everything else falls back.
 */
class LangOverrideTest extends TestCase
{
    private string $overrideFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->overrideFile = lang_path('vendor/ptah/pt_BR/ui.php');
    }

    protected function tearDown(): void
    {
        File::delete($this->overrideFile);
        parent::tearDown();
    }

    #[Test]
    public function overrides_tag_maps_the_stub_to_the_locale_path(): void
    {
        $paths = ServiceProvider::pathsToPublish(PtahServiceProvider::class, 'ptah-lang-overrides');

        $this->assertNotEmpty($paths, 'ptah-lang-overrides tag must be registered');

        $source = array_key_first($paths);
        $dest = str_replace('\\', '/', $paths[$source]);

        $this->assertFileExists($source, 'the override stub must ship in the package');
        $this->assertStringEndsWith('vendor/ptah/pt_BR/ui.php', $dest);
    }

    #[Test]
    public function a_partial_override_wins_for_its_keys_and_falls_back_for_the_rest(): void
    {
        File::ensureDirectoryExists(dirname($this->overrideFile));
        File::put($this->overrideFile, "<?php\n\nreturn ['bool_yes' => 'AFIRMATIVO'];\n");

        $this->app->setLocale('pt_BR');

        // Overridden key wins…
        $this->assertSame('AFIRMATIVO', trans('ptah::ui.bool_yes'));
        // …while a key NOT in the override still comes from the package.
        $this->assertSame('Não', trans('ptah::ui.bool_no'));
    }
}
