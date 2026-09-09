<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * What `ptah:install` writes into a host's `app.css` must be what the config
 * says the colour is.
 *
 * It was not, for `primary`: the installer wrote `#1e40af` (blue) while
 * `config/ptah.php` defaults to `#5b21b6` (violet) and `core.md` documents
 * `#5b21b6`. The other three — success, danger, warn — matched, which is what
 * makes the odd one out a slip rather than a decision.
 *
 * Not a functional bug: the dashboard layout includes `@vite` and then the
 * `theme-colors` partial, so the `:root` built from `ptah.colors` comes later
 * and wins by order. Which is arguably worse than a bug — the value was
 * unreachable, sitting in the `app.css` of every new project, and anyone who
 * opened that file would conclude the brand colour is blue.
 *
 * A third instance of one shape, all found in the same review: a document or a
 * stub asserting something the code contradicts. The other two got
 * `GuidelinesCssParityTest` and `DocSignatureParityTest`; this is the cheapest
 * of the three to pin, so there is no reason not to.
 */
class InstallStubColorParityTest extends TestCase
{
    private const INSTALL = __DIR__.'/../../../src/Commands/InstallCommand.php';

    private const CONFIG = __DIR__.'/../../../config/ptah.php';

    /**
     * @return array<string, array{0: string}>
     */
    public static function accentProvider(): array
    {
        return [
            'primary' => ['primary'],
            'success' => ['success'],
            'danger' => ['danger'],
            'warn' => ['warn'],
        ];
    }

    private static function read(string $path): string
    {
        $raw = file_get_contents($path);

        if ($raw === false) {
            throw new RuntimeException('InstallStubColorParityTest: falha ao ler '.$path);
        }

        return str_replace("\r\n", "\n", $raw);
    }

    /**
     * The `env(..., '#hex')` default for one accent, read out of the config
     * SOURCE rather than out of `config()`.
     *
     * Reading the resolved config would let a `.env` in the test environment
     * decide the expectation, and the claim here is about the file that ships.
     */
    private static function configDefault(string $accent): string
    {
        $source = self::read(self::CONFIG);

        if (preg_match("/'{$accent}'\\s*=>\\s*env\\([^,]+,\\s*'(#[0-9A-Fa-f]{3,8})'\\)/", $source, $m) !== 1) {
            throw new RuntimeException("InstallStubColorParityTest: nao achei o default de '{$accent}' em config/ptah.php.");
        }

        return strtolower($m[1]);
    }

    private static function installStubValue(string $accent): string
    {
        $source = self::read(self::INSTALL);

        if (preg_match("/--color-{$accent}:\\s*(#[0-9A-Fa-f]{3,8});/", $source, $m) !== 1) {
            throw new RuntimeException("InstallStubColorParityTest: nao achei --color-{$accent} no InstallCommand.");
        }

        return strtolower($m[1]);
    }

    #[Test]
    #[DataProvider('accentProvider')]
    public function the_installer_writes_the_colour_the_config_documents(string $accent): void
    {
        $this->assertSame(
            self::configDefault($accent),
            self::installStubValue($accent),
            "O ptah:install grava um `--color-{$accent}` diferente do default de config/ptah.php.\n".
            'O :root do config vence em runtime, entao o literal do app.css e inalcancavel — '.
            'e quem abrir aquele arquivo vai acreditar nele.'
        );
    }

    #[Test]
    public function the_guideline_agrees_with_the_config_too(): void
    {
        // core.md quotes the brand colour in its token table and in the
        // anti-pattern row. Both were already right; pinned so the three cannot
        // drift apart again in any direction.
        $guideline = self::read(__DIR__.'/../../../resources/boost/guidelines/core.md');

        $this->assertStringContainsString(
            self::configDefault('primary'),
            $guideline,
            'core.md deveria citar a mesma cor primaria que o config.'
        );
    }
}
