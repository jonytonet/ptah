<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ControllerApiGenerator;

/**
 * Covers the generated API controller. Regression for a bug found while
 * validating a fresh --api scaffold: the stub called $this->service->getDados()
 * (a PT-language typo), but BaseService only exposes getData() — every
 * generated index() action 500'd.
 */
class ControllerApiGeneratorTest extends GeneratorTestCase
{
    #[Test]
    public function it_calls_the_real_get_data_method_and_not_the_pt_typo(): void
    {
        $result = (new ControllerApiGenerator($this->files))->generate($this->context(withApi: true, withViews: false));

        $this->assertTrue($result->isDone(), $result->message ?? '');
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('$this->service->getData($request);', $content);
        $this->assertStringNotContainsString('getDados', $content);
    }

    #[Test]
    public function generated_api_controller_is_valid_php(): void
    {
        $result = (new ControllerApiGenerator($this->files))->generate($this->context(withApi: true, withViews: false));

        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($result->path), $output, $exitCode);

        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
