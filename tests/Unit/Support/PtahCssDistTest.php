<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * resources/dist/ptah.css is GENERATED (`npm run build:css`) from the views,
 * stubs, renderers and CSS listed in scripts/css/sources.json, and committed:
 * a host without Tailwind gets exactly that file. A view changed without the
 * rebuild ships a class the stylesheet does not have — an unstyled element
 * that only a Bootstrap host would ever see. The build writes a hash of its
 * inputs into the header; this recomputes it the same way
 * (scripts/build-css.mjs, inputsHash()).
 */
class PtahCssDistTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__, 3);
    }

    private static function dist(): string
    {
        return (string) file_get_contents(self::root().'/resources/dist/ptah.css');
    }

    private static function inputsHash(): string
    {
        $root = self::root();
        $sources = json_decode((string) file_get_contents($root.'/scripts/css/sources.json'), true);
        $package = json_decode((string) file_get_contents($root.'/package.json'), true);
        $hash = hash_init('sha256');

        foreach ($sources['packages'] as $name) {
            hash_update($hash, "package:{$name}@".($package['devDependencies'][$name] ?? '')."\n");
        }

        $files = [];
        foreach ($sources['dirs'] as [$dir, $suffix]) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (str_ends_with($file->getFilename(), $suffix)) {
                    $files[] = substr(str_replace('\\', '/', $file->getPathname()), strlen($root) + 1);
                }
            }
        }
        foreach ($sources['files'] as $file) {
            $files[] = $file;
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        foreach ($files as $relative) {
            $content = str_replace("\r\n", "\n", (string) file_get_contents($root.'/'.$relative));
            hash_update($hash, "{$relative}\n{$content}\n");
        }

        return hash_final($hash);
    }

    #[Test]
    public function the_committed_stylesheet_was_built_from_the_current_sources(): void
    {
        $this->assertMatchesRegularExpression('/inputs-hash: ([0-9a-f]{64})/', self::dist());
        preg_match('/inputs-hash: ([0-9a-f]{64})/', self::dist(), $m);

        $this->assertSame(
            self::inputsHash(),
            $m[1],
            'resources/dist/ptah.css is older than its sources (scripts/css/sources.json). Run `npm run build:css` and commit the result.'
        );
    }

    /**
     * The isolation the stylesheet exists for: Tailwind's preflight and every
     * utility only inside a ptah scope, and stopped at a slot. If the build
     * step that wraps them broke, a Bootstrap host would get Tailwind's reset
     * over its whole page.
     */
    #[Test]
    public function preflight_and_utilities_only_apply_inside_the_ptah_scope(): void
    {
        $css = self::dist();
        $scope = '@scope (.ptah-scope, [data-ptah-scope]) to (.ptah-slot, [data-ptah-slot])';

        foreach (['base', 'utilities'] as $layer) {
            $this->assertMatchesRegularExpression('/@layer '.$layer.' \{\s*'.preg_quote($scope, '/').' \{/', $css, "@layer {$layer} is not scoped.");
        }

        // Bootstrap neutralised inside the same scope, unlayered.
        $this->assertMatchesRegularExpression('/^'.preg_quote($scope, '/').' \{/m', $css);
        $this->assertStringContainsString('padding-right: revert-layer !important', $css);

        // No bare preflight selector left behind.
        $this->assertDoesNotMatchRegularExpression('/^\s*html, :host \{/m', $css);
        $this->assertStringContainsString('.px-3:where(:scope,:scope *)', $css);
    }

    #[Test]
    public function the_overlays_sit_above_bootstraps_navbar_and_modal(): void
    {
        $css = self::dist();

        $this->assertMatchesRegularExpression('/\.z-50:where\(:scope,:scope \*\) \{\s*z-index: 1060;/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.z-50:where\(:scope,:scope \*\) \{\s*z-index: 50;/', $css);
        $this->assertStringContainsString('.ptah-navbar.z-50          { z-index: 1030; }', $css);
    }
}
