<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ViewGenerator;

/**
 * Covers the index view generation: BaseCrud livewire mount and the
 * api-only shouldRun gate.
 */
class ViewGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_generates_the_index_view_mounting_base_crud(): void
    {
        $result = (new ViewGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        // The view delegates the whole screen to the BaseCrud Livewire component.
        $this->assertStringContainsString('livewire', strtolower($content));
        $this->assertStringContainsString('Widget', $content);
        $this->assertStringContainsString('/widget/index.blade.php', str_replace('\\', '/', $result->path));
    }

    #[Test]
    public function it_does_not_run_in_api_only_mode(): void
    {
        $generator = new ViewGenerator($this->files);

        $this->assertFalse($generator->shouldRun($this->context(withApi: true, withViews: false)));
        $this->assertTrue($generator->shouldRun($this->context()));
    }
}
