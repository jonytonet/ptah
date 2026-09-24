<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Mcp;

use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Mcp\PtahMcpTools;
use Ptah\Mcp\Tools\PtahCheck;
use Ptah\Models\CrudConfig;
use Ptah\PtahServiceProvider;
use Ptah\Tests\TestCase;
use ReflectionClass;

/**
 * The ptah tools as Laravel Boost's MCP server sees them.
 *
 * An agent connected to Boost only learns a tool exists from its name,
 * description and input schema, and only gets value from it if handle()
 * answers. So: every listed class is a read-only Tool, serializes to a valid
 * tool definition, answers with text for a realistic call, and is appended to
 * `boost.mcp.tools.include` — the one list Boost reads.
 */
class PtahMcpToolsTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<Tool>}>
     */
    public static function tools(): iterable
    {
        foreach (PtahMcpTools::CLASSES as $class) {
            yield class_basename($class) => [$class];
        }
    }

    #[Test]
    #[DataProvider('tools')]
    public function each_tool_is_a_read_only_tool_with_a_usable_definition(string $class): void
    {
        $this->assertTrue(class_exists($class), "{$class} nao existe.");
        $this->assertTrue(is_subclass_of($class, Tool::class));
        $this->assertNotEmpty((new ReflectionClass($class))->getAttributes(IsReadOnly::class), "{$class} precisa ser #[IsReadOnly] — nenhuma tool do ptah escreve.");

        /** @var Tool $tool */
        $tool = new $class;
        $definition = $tool->toArray();

        $this->assertStringStartsWith('ptah-', $definition['name']);
        $this->assertGreaterThan(80, strlen($definition['description']), 'A descricao e tudo que o agente le para decidir chamar a tool.');
        $this->assertSame('object', $definition['inputSchema']['type']);
    }

    #[Test]
    public function the_tools_are_offered_to_boost(): void
    {
        $include = (array) config('boost.mcp.tools.include', []);

        foreach (PtahMcpTools::CLASSES as $class) {
            $this->assertContains($class, $include);
        }
    }

    #[Test]
    public function registration_keeps_what_the_host_listed_and_can_be_turned_off(): void
    {
        $register = fn () => \Closure::bind(fn () => $this->registerBoostTools(), new PtahServiceProvider($this->app), PtahServiceProvider::class)();

        config(['boost.mcp.tools.include' => ['App\\Mcp\\HostTool']]);
        $register();
        $register();
        $include = config('boost.mcp.tools.include');
        $this->assertSame('App\\Mcp\\HostTool', $include[0]);
        $this->assertCount(1 + count(PtahMcpTools::CLASSES), $include, 'Registrar duas vezes nao pode duplicar.');

        config(['boost.mcp.tools.include' => [], 'ptah.mcp_tools' => false]);
        $register();
        $this->assertSame([], config('boost.mcp.tools.include'));
    }

    #[Test]
    public function the_tools_answer_with_the_commands_text(): void
    {
        CrudConfig::create(['model' => 'Catalog/Widget', 'route' => '', 'config' => [
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true]],
            'permissions' => ['permissionIdentifier' => 'pageWidget'],
        ]]);

        $cases = [
            'PtahMap' => [[], '## Screens'],
            'PtahScreen' => [['model' => 'Catalog/Widget'], 'permission: pageWidget'],
            'PtahDocs' => [['topic' => 'filter'], 'filter'],
            'PtahLastError' => [[], ''],
            'PtahUpgradeCheck' => [[], ''],
            'PtahCheck' => [['model' => 'Widget'], 'Catalog/Widget'],
        ];

        foreach ($cases as $name => [$args, $expected]) {
            $class = 'Ptah\\Mcp\\Tools\\'.$name;
            $response = (new $class)->handle(new Request($args));
            $text = (string) $response->content();

            $this->assertFalse($response->isError(), "{$name} respondeu erro: {$text}");
            $this->assertNotSame('', trim($text), "{$name} respondeu vazio.");
            if ($expected !== '') {
                $this->assertStringContainsString($expected, $text, "{$name}: resposta inesperada.");
            }
        }
    }

    #[Test]
    public function a_failing_screen_is_an_answer_not_a_tool_error(): void
    {
        // ptah:check sai com 1 quando uma tela falha — isso e a resposta.
        CrudConfig::create(['model' => 'Nowhere/Ghost', 'route' => '', 'config' => ['cols' => []]]);

        $response = (new PtahCheck)->handle(new Request(['model' => 'Ghost']));

        $this->assertFalse($response->isError());
        $this->assertStringContainsString('does not resolve', (string) $response->content());
    }
}
