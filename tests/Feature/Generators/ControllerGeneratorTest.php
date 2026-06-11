<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ControllerGenerator;
use Ptah\Support\EntityContext;

/**
 * Covers the generated web controller. Regression for a bug found while
 * validating a fresh install: the stub used Store/Update requests and
 * RedirectResponse without importing them, which fataled on store/update.
 */
class ControllerGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_imports_everything_the_method_signatures_reference(): void
    {
        $result = (new ControllerGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('use App\Http\Requests\StoreWidgetRequest;', $content);
        $this->assertStringContainsString('use App\Http\Requests\UpdateWidgetRequest;', $content);
        $this->assertStringContainsString('use Illuminate\Http\RedirectResponse;', $content);
        $this->assertStringContainsString('use App\Services\WidgetService;', $content);
        $this->assertStringContainsString('use Illuminate\View\View;', $content);
    }

    #[Test]
    public function it_gates_every_action_behind_the_permission_check(): void
    {
        $result = (new ControllerGenerator($this->files))->generate($this->context());
        $content = (string) file_get_contents($result->path);

        // index/show → read, store/create → create, update/edit → update, destroy → delete.
        $this->assertSame(7, substr_count($content, 'abort_if('));
        $this->assertStringContainsString("ptah_can('widget', 'read')", $content);
        $this->assertStringContainsString("ptah_can('widget', 'delete')", $content);
    }

    #[Test]
    public function subfolder_entities_import_requests_from_the_sub_namespace(): void
    {
        $context = new EntityContext(
            entity: 'Widget',
            entityLower: 'widget',
            entityPlural: 'widgets',
            entityPluralStudly: 'Widgets',
            table: 'widgets',
            rootNamespace: 'App\\',
            timestamp: date('Y_m_d_His'),
            withViews: true,
            withSoftDeletes: true,
            force: false,
            fields: $this->defaultFields(),
            subFolder: 'Catalog',
        );

        $result = (new ControllerGenerator($this->files))->generate($context);
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('namespace App\Http\Controllers\Catalog;', $content);
        $this->assertStringContainsString('use App\Http\Requests\Catalog\StoreWidgetRequest;', $content);
    }

    #[Test]
    public function it_does_not_run_in_api_only_mode(): void
    {
        $generator = new ControllerGenerator($this->files);

        $this->assertFalse($generator->shouldRun($this->context(withApi: true, withViews: false)));
        $this->assertTrue($generator->shouldRun($this->context()));
    }

    #[Test]
    public function generated_controller_is_valid_php(): void
    {
        $result = (new ControllerGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
