<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\Tool;
use Ptah\Contracts\AiToolInterface;
use Ptah\Contracts\AiToolSchemaInterface;
use Ptah\Livewire\AI\AiChatWidget;
use Ptah\Services\AI\AiChatService;
use Ptah\Services\AI\AiToolRegistry;
use Ptah\Services\AI\Tools\GetCurrentDateTimeTool;
use Ptah\Services\AI\Tools\GetSystemInfoTool;
use Ptah\Tests\TestCase;
use RuntimeException;

/**
 * A registered tool is a class name until the chat actually needs it.
 *
 * The registry used to be handed ready-made objects, built inside the container
 * closure. That closure runs when `AiChatService` is resolved, and
 * `AiChatService` is resolved in `AiChatWidget::boot()` — a widget that lives in
 * the authenticated layout, so it is constructed on every page of the
 * application. Two consequences, both reported from an ERP running twenty-six
 * domain tools:
 *
 *   Cost — every screen built twenty-six objects and their dependency graphs to
 *   serve a chat nobody had opened.
 *
 *   Blast radius — that construction happened inside the page's render, so one
 *   tool with a bad constructor returned 500 for the whole application rather
 *   than degrading the chat.
 *
 * These tests are about both, and about the isolation that makes the second one
 * survivable: a tool that cannot be built is logged with its class name and
 * dropped from the turn.
 */
class CountingTool implements AiToolInterface
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }

    public function name(): string
    {
        return 'counting_tool';
    }

    public function description(): string
    {
        return 'Counts how many times it was instantiated.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return ['ok' => true];
    }
}

class ExplodingTool implements AiToolInterface
{
    public function __construct()
    {
        throw new RuntimeException('ciclo de DI nesta tool');
    }

    public function name(): string
    {
        return 'exploding_tool';
    }

    public function description(): string
    {
        return 'Throws in its constructor.';
    }

    public function parameters(): array
    {
        return ['type' => 'object', 'properties' => []];
    }

    public function execute(array $arguments): array
    {
        return [];
    }
}

class StaticSchemaTool implements AiToolInterface, AiToolSchemaInterface
{
    public static int $built = 0;

    public function __construct()
    {
        self::$built++;
    }

    public static function toolSchema(): array
    {
        return [
            'name' => 'static_schema_tool',
            'description' => 'Described without being constructed.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'limit' => ['type' => 'integer', 'description' => 'Max rows'],
                ],
                'required' => ['limit'],
            ],
        ];
    }

    public function name(): string
    {
        return 'static_schema_tool';
    }

    public function description(): string
    {
        return 'Described without being constructed.';
    }

    public function parameters(): array
    {
        return self::toolSchema()['parameters'];
    }

    public function execute(array $arguments): array
    {
        return ['ran' => true, 'args' => $arguments];
    }
}

class NotATool
{
    public function name(): string
    {
        return 'not_a_tool';
    }
}

class LazyToolResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CountingTool::$built = 0;
        StaticSchemaTool::$built = 0;
    }

    /**
     * Binds the registry the way the application does — through
     * `AiToolRegistry::fromConfig()`, resolved by the container on demand.
     *
     * Deliberately not `$this->app->instance(...)` with a ready registry: that
     * hands the container an object already assembled, and the two tests about
     * resolving AiChatService and rendering the widget would then pass without
     * exercising the assembly at all — which is exactly where the eager
     * resolution used to live.
     *
     * The provider's own binding is out of reach here: under Testbench,
     * `getEnvironmentSetUp()` runs AFTER the package provider's `register()`, so
     * `config('ptah.modules.ai_agent')` reads false at binding time and
     * `AiToolRegistry::class` is never bound — the container would hand back a
     * bare autowired instance with no tools at all. (In a host the order is
     * reversed: config is loaded before providers register.) So the binding is
     * recreated here over the same factory, and `provider_delegates_to_from_config`
     * pins that the provider uses that factory and nothing else.
     *
     * @param  list<class-string>  $tools
     */
    private function bindRegistry(array $tools): void
    {
        config(['ptah.ai_agent.tools' => $tools]);

        $this->app->singleton(
            AiToolRegistry::class,
            static fn (): AiToolRegistry => AiToolRegistry::fromConfig()
        );
    }

    /**
     * @param  list<class-string>  $tools
     */
    private function registry(array $tools): AiToolRegistry
    {
        $this->bindRegistry($tools);

        return $this->app->make(AiToolRegistry::class);
    }

    private function pick(AiToolRegistry $registry, string $name): Tool
    {
        foreach ($registry->getPrismTools() as $tool) {
            if ($tool->name() === $name) {
                return $tool;
            }
        }

        $this->fail(sprintf('Tool %s nao esta na lista.', $name));
    }

    /**
     * @return list<string>
     */
    private function names(AiToolRegistry $registry): array
    {
        return array_map(fn ($t) => $t->name(), $registry->getPrismTools());
    }

    #[Test]
    public function registering_a_class_builds_nothing(): void
    {
        $this->registry([CountingTool::class]);

        $this->assertSame(0, CountingTool::$built);
    }

    #[Test]
    public function the_builtin_tools_are_also_lazy(): void
    {
        // They used to be `new`-ed in the provider. Left that way they would be
        // a path none of the laziness tests covers.
        $registry = $this->registry([]);

        $this->assertContains(GetSystemInfoTool::class, $registry->pendingClasses());
        $this->assertContains(GetCurrentDateTimeTool::class, $registry->pendingClasses());
        $this->assertContains('getCurrentDateTime', $this->names($registry));
        $this->assertContains('getSystemInfo', $this->names($registry));
    }

    #[Test]
    public function resolving_the_chat_service_builds_nothing(): void
    {
        // The path that used to cost twenty-six constructions per page load.
        $this->bindRegistry([CountingTool::class]);

        $this->app->make(AiChatService::class);

        $this->assertSame(0, CountingTool::$built);
    }

    #[Test]
    public function rendering_the_chat_widget_builds_nothing(): void
    {
        $this->bindRegistry([CountingTool::class]);

        Livewire::test(AiChatWidget::class);

        $this->assertSame(0, CountingTool::$built);
    }

    #[Test]
    public function asking_for_the_tool_list_builds_it_exactly_once(): void
    {
        $registry = $this->registry([CountingTool::class]);

        $this->assertContains('counting_tool', $this->names($registry));
        $this->assertSame(1, CountingTool::$built);

        // Memoised: a turn may consult the list more than once.
        $registry->getPrismTools();

        $this->assertSame(1, CountingTool::$built);
    }

    #[Test]
    public function registering_the_same_class_twice_keeps_one(): void
    {
        $registry = $this->registry([CountingTool::class, CountingTool::class]);

        $this->assertSame(
            1,
            count(array_filter($registry->pendingClasses(), fn (string $c): bool => $c === CountingTool::class))
        );
        $this->assertSame(
            1,
            count(array_filter($this->names($registry), fn (string $n): bool => $n === 'counting_tool'))
        );
        $this->assertSame(1, CountingTool::$built);
    }

    #[Test]
    public function a_tool_that_throws_in_its_constructor_is_skipped_not_fatal(): void
    {
        // The reason for the whole change. Before, this took the page with it.
        Log::shouldReceive('error')->once()->withArgs(function (string $msg, array $ctx): bool {
            return str_contains($ctx['tool'] ?? '', 'ExplodingTool')
                && ($ctx['exception'] ?? null) === RuntimeException::class;
        });
        Log::shouldReceive('warning')->never();

        $registry = $this->registry([ExplodingTool::class, CountingTool::class]);

        $names = $this->names($registry);

        $this->assertContains('counting_tool', $names, 'A tool boa tinha que sobreviver a vizinha ruim.');
        $this->assertNotContains('exploding_tool', $names);
    }

    #[Test]
    public function the_widget_still_renders_when_a_tool_is_broken(): void
    {
        // The user-visible contract: the chat loses a capability, the page does
        // not lose anything.
        $this->bindRegistry([ExplodingTool::class]);

        Livewire::test(AiChatWidget::class)->assertOk();
    }

    #[Test]
    public function a_class_that_does_not_exist_is_logged_and_skipped(): void
    {
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $msg, array $ctx): bool => ($ctx['tool'] ?? null) === 'App\\Tools\\GoneAfterRefactor'
        );

        $registry = $this->registry(['App\\Tools\\GoneAfterRefactor']);

        $this->assertNotContains('gone_after_refactor', $this->names($registry));
    }

    #[Test]
    public function a_class_that_is_not_a_tool_is_logged_and_skipped(): void
    {
        Log::shouldReceive('warning')->once();

        $registry = $this->registry([NotATool::class]);

        $this->assertNotContains('not_a_tool', $this->names($registry));
    }

    #[Test]
    public function a_non_string_non_tool_entry_is_logged_and_skipped(): void
    {
        Log::shouldReceive('warning')->once();

        $registry = new AiToolRegistry;
        $registry->registerMany([42]);

        $this->assertSame([], $registry->pendingClasses());
        $this->assertSame([], $registry->getPrismTools());
    }

    #[Test]
    public function a_ready_made_object_is_still_accepted(): void
    {
        // Backward compatibility: `register()` with an instance is the API that
        // existed, and a host may be calling it.
        $registry = new AiToolRegistry;
        $registry->register(new GetCurrentDateTimeTool);

        $this->assertCount(1, $registry->getPrismTools());
    }

    #[Test]
    public function a_static_schema_describes_the_tool_without_constructing_it(): void
    {
        $registry = $this->registry([StaticSchemaTool::class]);

        $tool = $this->pick($registry, 'static_schema_tool');

        $this->assertSame(['limit'], array_keys($tool->parameters()));
        $this->assertSame(['limit'], $tool->requiredParameters());

        $this->assertSame(
            0,
            StaticSchemaTool::$built,
            'Uma tool com schema estatico nao deve ser construida so para ser descrita.'
        );
    }

    #[Test]
    public function a_static_schema_tool_is_constructed_when_the_model_calls_it(): void
    {
        $registry = $this->registry([StaticSchemaTool::class]);

        $tool = $this->pick($registry, 'static_schema_tool');

        $this->assertSame(0, StaticSchemaTool::$built);

        $result = $tool->handle(limit: 5);

        $this->assertSame(1, StaticSchemaTool::$built);
        $this->assertSame(['ran' => true, 'args' => ['limit' => 5]], json_decode($result, true));
    }

    #[Test]
    public function a_tool_that_fails_while_executing_answers_the_model_instead_of_throwing(): void
    {
        // A throw here would abort the whole turn over one tool the model chose
        // to try — the user would see the chat error out, not a degraded answer.
        Log::shouldReceive('error')->once();

        $registry = new AiToolRegistry;
        $registry->register(new class implements AiToolInterface
        {
            public function name(): string
            {
                return 'angry_tool';
            }

            public function description(): string
            {
                return 'Fails on execute.';
            }

            public function parameters(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            public function execute(array $arguments): array
            {
                throw new RuntimeException('a consulta explodiu');
            }
        });

        $decoded = json_decode($registry->getPrismTools()[0]->handle(), true);

        $this->assertTrue($decoded['error']);
        $this->assertStringContainsString('angry_tool', $decoded['message']);
        $this->assertStringNotContainsString('a consulta explodiu', $decoded['message']);
    }

    #[Test]
    public function provider_delegates_to_from_config(): void
    {
        // Pins the provider's half, which the Testbench ordering above puts out
        // of reach at runtime: the binding must be the factory these tests
        // exercise, with no eager assembly of its own.
        $source = (string) file_get_contents(__DIR__.'/../../../src/PtahServiceProvider.php');

        $this->assertStringContainsString('AiToolRegistry::fromConfig()', $source);
        $this->assertStringNotContainsString('new GetSystemInfoTool', $source);
        $this->assertStringNotContainsString('new GetCurrentDateTimeTool', $source);
        $this->assertStringNotContainsString('app($tool)', $source);
    }
}
