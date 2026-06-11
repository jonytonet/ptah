<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\RepositoryGenerator;
use Ptah\Generators\RepositoryInterfaceGenerator;

/**
 * Covers the repository pair: the concrete class must extend BaseRepository,
 * implement the generated interface and inject the right model.
 */
class RepositoryGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_generates_a_repository_implementing_the_interface(): void
    {
        $result = (new RepositoryGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('namespace App\Repositories;', $content);
        $this->assertStringContainsString(
            'class WidgetRepository extends BaseRepository implements WidgetRepositoryInterface',
            $content,
        );
        $this->assertStringContainsString('use App\Models\Widget;', $content);
        $this->assertStringContainsString('use App\Repositories\Contracts\WidgetRepositoryInterface;', $content);
        $this->assertStringContainsString('public function __construct(Widget $model)', $content);
    }

    #[Test]
    public function it_generates_the_matching_interface_in_contracts(): void
    {
        $result = (new RepositoryInterfaceGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('namespace App\Repositories\Contracts;', $content);
        $this->assertStringContainsString('WidgetRepositoryInterface', $content);
        $this->assertStringContainsString('Contracts/WidgetRepositoryInterface.php', str_replace('\\', '/', $result->path));
    }

    #[Test]
    public function generated_pair_is_valid_php(): void
    {
        $repo = (new RepositoryGenerator($this->files))->generate($this->context());
        $iface = (new RepositoryInterfaceGenerator($this->files))->generate($this->context());

        foreach ([$repo->path, $iface->path] as $path) {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $output, $exitCode);
            $this->assertSame(0, $exitCode, implode("\n", $output));
        }
    }
}
