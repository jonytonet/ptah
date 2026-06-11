<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ServiceGenerator;

/**
 * Covers the generated service: extends BaseService and receives the
 * repository through its interface (DIP), never the concrete class.
 */
class ServiceGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_generates_a_service_depending_on_the_interface(): void
    {
        $result = (new ServiceGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('namespace App\Services;', $content);
        $this->assertStringContainsString('class WidgetService extends BaseService', $content);
        $this->assertStringContainsString('use App\Repositories\Contracts\WidgetRepositoryInterface;', $content);
        $this->assertStringContainsString(
            'public function __construct(WidgetRepositoryInterface $repository)',
            $content,
        );
        // DIP: never depends on the concrete repository
        $this->assertStringNotContainsString('WidgetRepository $', $content);
    }

    #[Test]
    public function generated_service_is_valid_php(): void
    {
        $result = (new ServiceGenerator($this->files))->generate($this->context());

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
