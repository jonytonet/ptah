<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Support\UpgradeInspector;
use Ptah\Tests\TestCase;

/**
 * `ptah:upgrade-check` reads the HOST's published files. Each case builds the
 * state an older install leaves behind and asserts the command names it with
 * the action — and a clean install reports nothing, or the check is noise.
 */
class UpgradeCheckCommandTest extends TestCase
{
    private string $tmp;

    private Filesystem $files;

    private string $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new Filesystem;
        $this->package = dirname(__DIR__, 3);
        $this->tmp = sys_get_temp_dir().'/ptah-upgrade-'.uniqid();
        $this->files->ensureDirectoryExists($this->tmp.'/config');

        $this->app->setBasePath($this->tmp);
        $this->app->useConfigPath($this->tmp.'/config');
    }

    protected function tearDown(): void
    {
        $this->files->deleteDirectory($this->tmp);

        parent::tearDown();
    }

    private function publishConfig(callable $edit): void
    {
        $config = require $this->package.'/config/ptah.php';
        $edit($config);
        $this->files->put($this->tmp.'/config/ptah.php', '<?php return '.var_export($config, true).';');
    }

    private function checks(string $name): array
    {
        return array_values(array_filter(UpgradeInspector::inspect($this->package), fn ($f) => $f['check'] === $name));
    }

    #[Test]
    public function a_clean_install_has_nothing_to_report_from_published_files(): void
    {
        $this->assertSame([], $this->checks('config'));
        $this->assertSame([], $this->checks('views'));
        $this->assertSame([], $this->checks('stubs'));
    }

    #[Test]
    public function a_nested_key_missing_from_the_published_config_is_named(): void
    {
        // O merge raso nao traz `preferences.foreign_key` para quem publicou antes dela.
        $this->publishConfig(fn (array &$c) => Arr::forget($c, 'preferences.foreign_key'));

        $config = $this->checks('config');

        $this->assertCount(1, $config);
        $this->assertSame('warn', $config[0]['level']);
        $this->assertStringContainsString('preferences.foreign_key', $config[0]['message']);
    }

    #[Test]
    public function a_missing_top_level_key_is_not_reported_because_the_merge_fills_it(): void
    {
        $this->publishConfig(fn (array &$c) => Arr::forget($c, 'structure_editor'));

        $this->assertSame([], $this->checks('config'));
    }

    #[Test]
    public function a_key_the_package_no_longer_reads_is_named(): void
    {
        $this->publishConfig(function (array &$c) {
            $c['preferences']['driver'] = 'database';
        });

        $config = $this->checks('config');

        $this->assertCount(1, $config);
        $this->assertSame('info', $config[0]['level']);
        $this->assertStringContainsString('preferences.driver', $config[0]['message']);
    }

    #[Test]
    public function published_views_are_sorted_into_shadowing_identical_and_dead(): void
    {
        $vendor = $this->tmp.'/resources/views/vendor/ptah';
        $views = $this->package.'/resources/views';
        $sample = collect($this->files->allFiles($views.'/components'))->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))->take(2)->values();

        $this->files->ensureDirectoryExists($vendor.'/components');
        $this->files->copy($sample[0]->getPathname(), $vendor.'/components/'.$sample[0]->getFilename());
        $this->files->put($vendor.'/components/'.$sample[1]->getFilename(), '<div>minha versao antiga</div>');
        $this->files->put($vendor.'/components/removida.blade.php', '<div></div>');

        $byLevel = collect($this->checks('views'))->keyBy(fn ($f) => $f['level'].':'.(str_contains($f['message'], 'no longer exist') ? 'dead' : (str_contains($f['message'], 'identical') ? 'same' : 'diff')));

        $this->assertStringContainsString($sample[1]->getFilename(), $byLevel['warn:diff']['message']);
        $this->assertStringContainsString($sample[0]->getFilename(), $byLevel['info:same']['message']);
        $this->assertStringContainsString('removida.blade.php', $byLevel['info:dead']['message']);
    }

    #[Test]
    public function a_published_stub_that_differs_is_named(): void
    {
        $this->files->ensureDirectoryExists($this->tmp.'/stubs/ptah');
        $this->files->put($this->tmp.'/stubs/ptah/model.stub', '<?php // antigo');
        $this->files->copy($this->package.'/src/Stubs/dto.stub', $this->tmp.'/stubs/ptah/dto.stub');

        $stubs = $this->checks('stubs');

        $this->assertCount(1, $stubs);
        $this->assertStringContainsString('model.stub', $stubs[0]['message']);
        $this->assertStringNotContainsString('dto.stub', $stubs[0]['message']);
    }

    #[Test]
    public function strict_fails_when_there_is_something_to_act_on(): void
    {
        $this->publishConfig(fn (array &$c) => Arr::forget($c, 'preferences.foreign_key'));

        $this->artisan('ptah:upgrade-check', ['--strict' => true])
            ->expectsOutputToContain('preferences.foreign_key')
            ->assertExitCode(1);
    }

    #[Test]
    public function json_output_carries_the_version_and_findings(): void
    {
        $this->artisan('ptah:upgrade-check', ['--json' => true])
            ->expectsOutputToContain('"findings":')
            ->assertExitCode(0);
    }
}
