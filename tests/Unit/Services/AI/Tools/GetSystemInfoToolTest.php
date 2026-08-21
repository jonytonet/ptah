<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\AI\Tools;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Services\AI\Tools\GetSystemInfoTool;
use Ptah\Tests\TestCase;

/**
 * Covers GetSystemInfoTool's contract metadata and, most importantly, that
 * execute() never leaks laravel_version/php_version/environment unless the
 * host explicitly opts in via ptah.ai_agent.expose_system_details — any chat
 * user (including guests) can trigger this tool, so those version banners
 * are a fingerprinting risk by default.
 */
class GetSystemInfoToolTest extends TestCase
{
    private GetSystemInfoTool $tool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tool = new GetSystemInfoTool;
    }

    #[Test]
    public function name_and_description_are_stable_contract_metadata(): void
    {
        $this->assertSame('getSystemInfo', $this->tool->name());
        $this->assertStringContainsString('Laravel version', $this->tool->description());
    }

    #[Test]
    public function declares_no_required_parameters(): void
    {
        $params = $this->tool->parameters();

        $this->assertSame('object', $params['type']);
        $this->assertSame([], $params['required']);
        $this->assertInstanceOf(\stdClass::class, $params['properties']);
    }

    #[Test]
    public function exposes_only_basic_info_when_expose_system_details_is_disabled_by_default(): void
    {
        config(['ptah.ai_agent.expose_system_details' => false]);

        $info = $this->tool->execute([]);

        $this->assertArrayHasKey('app_name', $info);
        $this->assertArrayHasKey('timezone', $info);
        $this->assertArrayHasKey('locale', $info);
        $this->assertArrayNotHasKey('laravel_version', $info);
        $this->assertArrayNotHasKey('php_version', $info);
        $this->assertArrayNotHasKey('environment', $info);
    }

    #[Test]
    public function stays_closed_under_the_packages_own_unmodified_default_config(): void
    {
        // No explicit config() override here — this exercises the shipped
        // default in config/ptah.php (expose_system_details => false).
        $info = $this->tool->execute([]);

        $this->assertArrayNotHasKey('laravel_version', $info);
        $this->assertArrayNotHasKey('php_version', $info);
        $this->assertArrayNotHasKey('environment', $info);
    }

    #[Test]
    public function exposes_version_and_environment_details_when_explicitly_enabled(): void
    {
        config(['ptah.ai_agent.expose_system_details' => true]);

        $info = $this->tool->execute([]);

        $this->assertArrayHasKey('laravel_version', $info);
        $this->assertArrayHasKey('php_version', $info);
        $this->assertArrayHasKey('environment', $info);
        $this->assertSame(PHP_VERSION, $info['php_version']);
        $this->assertSame(app()->version(), $info['laravel_version']);
        $this->assertSame(app()->environment(), $info['environment']);
    }

    #[Test]
    public function arguments_passed_to_execute_are_ignored(): void
    {
        config(['ptah.ai_agent.expose_system_details' => false]);

        $info = $this->tool->execute(['anything' => 'goes here']);

        $this->assertArrayNotHasKey('anything', $info);
    }
}
