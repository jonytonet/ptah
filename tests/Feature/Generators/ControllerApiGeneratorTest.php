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

    /**
     * Swagger paths are absolute (@OA\Server carries only the host), so they
     * must spell out the app's api mount point + the version prefix — and honour
     * ptah.api.prefix instead of hard-coding "api".
     */
    #[Test]
    public function swagger_paths_honour_the_configured_api_prefix(): void
    {
        config(['ptah.api.prefix' => 'backend']);

        $result = (new ControllerApiGenerator($this->files))->generate($this->context(withApi: true, withViews: false));
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('path="/backend/v1/widgets"', $content);
        $this->assertStringNotContainsString('/api/v1/', $content);
    }

    #[Test]
    public function swagger_paths_default_to_the_api_mount_point(): void
    {
        $result = (new ControllerApiGenerator($this->files))->generate($this->context(withApi: true, withViews: false));
        $content = (string) file_get_contents($result->path);

        $this->assertStringContainsString('path="/api/v1/widgets"', $content);
        // The prefix must appear exactly once per path — @OA\Server + path used
        // to both carry it, documenting /api/v1/api/v1/… (a 404).
        $this->assertStringNotContainsString('/api/v1/api/v1/', $content);
    }
}
