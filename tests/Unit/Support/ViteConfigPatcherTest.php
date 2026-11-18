<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ptah\Support\ViteConfigPatcher;

/**
 * vite.config.js of the React/Vue stack (1.59.0): the plugin, the entry, the
 *
 * @ptah alias and the dedupe — added once, and reported when they cannot be.
 */
class ViteConfigPatcherTest extends TestCase
{
    private const LARAVEL_DEFAULT = <<<'JS'
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
JS;

    #[Test]
    public function react_is_added_to_the_default_laravel_config(): void
    {
        $result = ViteConfigPatcher::patch(self::LARAVEL_DEFAULT, 'react', 'resources/js/ptah-app.jsx');
        $js = $result['content'];

        $this->assertSame([], $result['missing']);
        $this->assertStringContainsString("import react from '@vitejs/plugin-react';", $js);
        $this->assertStringContainsString("import { fileURLToPath, URL } from 'node:url';", $js);
        $this->assertMatchesRegularExpression('/plugins: \[\s+react\(\),\s+laravel\(/', $js);
        $this->assertStringContainsString("input: ['resources/js/ptah-app.jsx', 'resources/css/app.css', 'resources/js/app.js']", $js);
        $this->assertStringContainsString("'@ptah': fileURLToPath(new URL('./vendor/jonytonet/ptah/resources/js', import.meta.url))", $js);
        $this->assertStringContainsString("dedupe: ['react', 'react-dom']", $js);
        // The imports stay together, above the config.
        $this->assertLessThan(strpos($js, 'export default'), strpos($js, 'import react from'));
    }

    #[Test]
    public function vue_gets_its_plugin_and_dedupe(): void
    {
        $js = ViteConfigPatcher::patch(self::LARAVEL_DEFAULT, 'vue', 'resources/js/ptah-app.js')['content'];

        $this->assertStringContainsString("import vue from '@vitejs/plugin-vue';", $js);
        $this->assertStringContainsString('vue({ template: { transformAssetUrls: { base: null, includeAbsolute: false } } }),', $js);
        $this->assertStringContainsString("input: ['resources/js/ptah-app.js', ", $js);
        $this->assertStringContainsString("dedupe: ['vue']", $js);
    }

    #[Test]
    public function running_it_twice_changes_nothing(): void
    {
        $once = ViteConfigPatcher::patch(self::LARAVEL_DEFAULT, 'react', 'resources/js/ptah-app.jsx')['content'];
        $twice = ViteConfigPatcher::patch($once, 'react', 'resources/js/ptah-app.jsx');

        $this->assertSame($once, $twice['content']);
        $this->assertSame([], $twice['missing']);
    }

    #[Test]
    public function a_config_that_already_has_the_plugin_keeps_one(): void
    {
        $kit = str_replace(
            ["import tailwindcss from '@tailwindcss/vite';", 'tailwindcss(),'],
            ["import tailwindcss from '@tailwindcss/vite';\nimport react from '@vitejs/plugin-react';", "react(),\n        tailwindcss(),"],
            self::LARAVEL_DEFAULT
        );

        $js = ViteConfigPatcher::patch($kit, 'react', 'resources/js/ptah-app.jsx')['content'];

        $this->assertSame(1, substr_count($js, "from '@vitejs/plugin-react'"));
        $this->assertSame(1, substr_count($js, 'react()'));
    }

    #[Test]
    public function changing_stack_adds_the_other_entry_and_library(): void
    {
        $react = ViteConfigPatcher::patch(self::LARAVEL_DEFAULT, 'react', 'resources/js/ptah-app.jsx')['content'];
        $result = ViteConfigPatcher::patch($react, 'vue', 'resources/js/ptah-app.js');
        $js = $result['content'];

        $this->assertSame([], $result['missing']);
        // `ptah-app.js` is the start of `ptah-app.jsx`: it must still be added.
        $this->assertStringContainsString("'resources/js/ptah-app.js', 'resources/js/ptah-app.jsx'", $js);
        $this->assertStringContainsString("dedupe: ['vue', 'react', 'react-dom']", $js);
        $this->assertSame(1, substr_count($js, "'@ptah'"));
        $this->assertSame($js, ViteConfigPatcher::patch($js, 'vue', 'resources/js/ptah-app.js')['content']);
    }

    #[Test]
    public function a_single_string_input_becomes_a_list(): void
    {
        $config = str_replace("input: ['resources/css/app.css', 'resources/js/app.js'],", "input: 'resources/js/app.js',", self::LARAVEL_DEFAULT);

        $js = ViteConfigPatcher::patch($config, 'react', 'resources/js/ptah-app.jsx')['content'];

        $this->assertStringContainsString("input: ['resources/js/ptah-app.jsx', 'resources/js/app.js'],", $js);
    }

    #[Test]
    public function a_resolve_block_of_the_host_is_left_alone_and_reported(): void
    {
        $config = str_replace('export default defineConfig({', "export default defineConfig({\n    resolve: { alias: { '@': '/resources/js' } },", self::LARAVEL_DEFAULT);

        $result = ViteConfigPatcher::patch($config, 'react', 'resources/js/ptah-app.jsx');

        $this->assertSame(1, substr_count($result['content'], 'resolve:'));
        $this->assertCount(1, $result['missing']);
        $this->assertStringContainsString("'@ptah'", $result['missing'][0]);
    }

    #[Test]
    public function a_config_it_cannot_read_reports_everything(): void
    {
        $result = ViteConfigPatcher::patch("module.exports = require('./vite.shared');\n", 'vue', 'resources/js/ptah-app.js');

        $this->assertCount(3, $result['missing']);
    }
}
