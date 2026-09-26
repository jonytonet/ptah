<?php

declare(strict_types=1);

namespace Ptah\Services\AI;

use Illuminate\Support\Facades\Log;
use Prism\Prism\Tool;
use Ptah\Contracts\AiToolAuthorizable;
use Ptah\Contracts\AiToolContextAware;
use Ptah\Contracts\AiToolInterface;
use Ptah\Contracts\AiToolSchemaInterface;
use Ptah\Services\AI\Tools\FindMenuTool;
use Ptah\Services\AI\Tools\GetCurrentDateTimeTool;
use Ptah\Services\AI\Tools\GetSystemInfoTool;
use Ptah\Support\AI\AiToolContext;
use Throwable;

/**
 * Registry for AI tools (function-calling) used by the Ptah AI Agent.
 *
 * Tools registered via AiToolInterface are automatically converted to
 * Prism Tool objects that the LLM provider can invoke.
 *
 * Built-in tools (GetSystemInfoTool, GetCurrentDateTimeTool) are registered
 * automatically by the ServiceProvider. Consumer applications can add their
 * own tools via config/ptah.php:
 *
 *   'ai_agent' => [
 *       'tools' => [
 *           App\Services\AI\Tools\MyCustomTool::class,
 *       ],
 *   ],
 *
 * ── Registration is lazy, and it matters ──────────────────────────────────
 *
 * A class name registered here is a string until something asks for the tool
 * list. Two reasons, both from a real ERP running twenty-six domain tools:
 *
 * 1. Cost. The chat widget lives in the authenticated layout, so it is
 *    constructed on every page load. It takes AiChatService, which takes this
 *    registry — so container-resolving every tool at registration time meant
 *    every screen in the application paid for twenty-six objects and their
 *    whole dependency graphs, to answer a chat nobody had opened.
 *
 * 2. Blast radius. That resolution happened while the widget was being built,
 *    which is to say inside the page's render. One tool with a DI cycle or a
 *    heavy constructor did not break the chat — it returned 500 for the entire
 *    application. A tool is host code the package cannot vet; it must not be
 *    able to take the page down.
 *
 * So resolution happens at send time, one tool at a time, each inside its own
 * try/catch: a tool that cannot be built is logged with its class name and left
 * out of that turn. The chat degrades — the model loses one capability — and
 * nothing else notices.
 *
 * A tool that also implements AiToolSchemaInterface is not constructed even
 * then: its schema is read statically and the object is built only if the model
 * actually calls it.
 *
 * ── Who may use a tool ─────────────────────────────────────────────────────
 *
 * A tool that implements AiToolAuthorizable is left out of the list when the
 * user lacks its permission, and checked again when the model calls it. A tool
 * that implements AiToolContextAware receives the user and active company
 * right before `execute()`.
 */
class AiToolRegistry
{
    /** Tools handed over as objects. @var array<string, AiToolInterface> */
    private array $tools = [];

    /** Class names not resolved yet. @var array<string, class-string> */
    private array $pending = [];

    /** Memoised result of getPrismTools(). @var Tool[]|null */
    private ?array $prismTools = null;

    /** User and company the memo was built for — the list depends on them. */
    private ?string $prismFor = null;

    /**
     * The registry the service provider binds: the two built-ins plus whatever
     * `ptah.ai_agent.tools` names, all by class name.
     *
     * A named factory rather than a closure inside the provider so that a test
     * can exercise the exact assembly the application runs. Under Testbench the
     * provider's binding never happens at all — `getEnvironmentSetUp()` runs
     * after `register()`, so the module gate reads false and the container hands
     * back a bare autowired instance — which would leave the eager-resolution
     * regression permanently untestable through the container.
     */
    public static function fromConfig(): self
    {
        $registry = new self;

        // The built-ins go in by name too. A privileged eager path for them
        // would be a path the laziness tests do not cover.
        $registry->registerClass(GetSystemInfoTool::class);
        $registry->registerClass(GetCurrentDateTimeTool::class);
        // "Onde fica X?" e a pergunta mais comum de quem usa um sistema que nao
        // construiu, e a resposta ja esta na aplicacao — a sidebar a desenha em
        // toda pagina. Sem esta tool o assistente adivinha caminho de menu, o
        // que e pior que nao responder: a pessoa segue o caminho inventado, nao
        // acha a tela, e para de confiar nas respostas que estavam certas.
        $registry->registerClass(FindMenuTool::class);
        $registry->registerMany(config('ptah.ai_agent.tools', []));

        return $registry;
    }

    public function register(AiToolInterface $tool): void
    {
        $this->tools[$tool->name()] = $tool;
        $this->prismTools = null;
    }

    /**
     * Registers a tool by class name, resolving nothing.
     *
     * `class_exists()` is deliberately NOT checked here: it triggers the
     * autoloader, and the point of this method is that registration touches no
     * files. A name that does not resolve is reported when the list is built.
     */
    public function registerClass(string $class): void
    {
        // Keyed by class name so registering the same tool twice is idempotent,
        // matching what register() does with the tool's name.
        $this->pending[$class] = $class;
        $this->prismTools = null;
    }

    /**
     * @param  iterable<mixed>  $tools  Class names, or ready AiToolInterface objects.
     */
    public function registerMany(iterable $tools): void
    {
        foreach ($tools as $tool) {
            if (is_string($tool)) {
                $this->registerClass($tool);
            } elseif ($tool instanceof AiToolInterface) {
                $this->register($tool);
            } else {
                Log::warning('ptah: entrada de tool de IA ignorada — nao e class-string nem AiToolInterface.', [
                    'type' => get_debug_type($tool),
                ]);
            }
        }
    }

    /**
     * Class names still awaiting resolution. For diagnostics and tests.
     *
     * @return list<class-string>
     */
    public function pendingClasses(): array
    {
        return array_values($this->pending);
    }

    /**
     * Convert all registered tools to Prism Tool objects.
     *
     * Memoised: a turn can consult this more than once, and the answer cannot
     * change within a request.
     *
     * @return Tool[]
     */
    public function getPrismTools(): array
    {
        $context = AiToolContext::current();
        $for = $context->userId().'|'.$context->companyId;

        if ($this->prismTools !== null && $this->prismFor === $for) {
            return $this->prismTools;
        }

        $prism = [];

        foreach ($this->tools as $tool) {
            if (self::allows($tool::class, $context)) {
                $prism[] = $this->convertToPrismTool($tool);
            }
        }

        foreach ($this->pending as $class) {
            $tool = $this->describe($class, $context);

            if ($tool !== null) {
                $prism[] = $tool;
            }
        }

        $this->prismFor = $for;

        return $this->prismTools = $prism;
    }

    /**
     * Whether the context's user may use the tool class. A tool that does not
     * implement AiToolAuthorizable is open (the pre-1.40 behaviour, reported
     * by `ptah:check`).
     */
    public static function allows(string $class, AiToolContext $context): bool
    {
        if (! is_subclass_of($class, AiToolAuthorizable::class)) {
            return true;
        }

        $permission = $class::permission();
        if ($permission === null) {
            return true;
        }

        $key = (string) ($permission[0] ?? '');
        $action = (string) ($permission[1] ?? 'read');

        if ($key === '') {
            return false;
        }

        // Sem o modulo de permissoes nao ha chave para conferir: a tool com
        // permissao declarada ainda exige alguem autenticado.
        if (! config('ptah.modules.permissions')) {
            return $context->user !== null;
        }

        return ptah_can($key, $action, $context->user, $context->companyId > 0 ? $context->companyId : null);
    }

    /**
     * Builds the Prism tool for one registered class name, or null if it cannot
     * be described.
     *
     * This is the isolation boundary. Everything about a host-supplied tool that
     * can go wrong — the class is gone after a refactor, it does not implement
     * the interface, its constructor throws, its dependencies cannot be built,
     * its static schema is malformed — ends here, as a log line and one missing
     * capability.
     */
    private function describe(string $class, AiToolContext $context): ?Tool
    {
        try {
            if (! class_exists($class)) {
                Log::warning('ptah: tool de IA registrada nao existe.', ['tool' => $class]);

                return null;
            }

            // AiToolInterface first, and for the static-schema case too: the
            // static schema only describes the tool, `execute()` still runs it.
            // A class carrying just the schema interface would be described to
            // the model and then fail on the call.
            if (! is_subclass_of($class, AiToolInterface::class)) {
                Log::warning('ptah: tool de IA nao implementa AiToolInterface.', ['tool' => $class]);

                return null;
            }

            // Before constructing anything: a tool this user may not use is not
            // described to the model at all.
            if (! self::allows($class, $context)) {
                return null;
            }

            if (is_subclass_of($class, AiToolSchemaInterface::class)) {
                return $this->convertFromStaticSchema($class);
            }

            $instance = app($class);

            if (! $instance instanceof AiToolInterface) {
                Log::warning('ptah: o container devolveu algo que nao e AiToolInterface.', ['tool' => $class]);

                return null;
            }

            return $this->convertToPrismTool($instance);
        } catch (Throwable $e) {
            // The class name is the whole point of this log: without it the
            // operator sees a broken chat and has twenty-six suspects.
            Log::error('ptah: tool de IA ignorada neste turno — falhou ao ser resolvida.', [
                'tool' => $class,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    // ─────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────

    private function convertToPrismTool(AiToolInterface $tool): Tool
    {
        return $this->buildPrismTool(
            $tool->name(),
            $tool->description(),
            $tool->parameters(),
            static fn (): AiToolInterface => $tool,
            $tool::class,
        );
    }

    /**
     * @param  class-string  $class
     */
    private function convertFromStaticSchema(string $class): ?Tool
    {
        $schema = $class::toolSchema();

        $name = $schema['name'] ?? null;
        $description = $schema['description'] ?? null;

        if (! is_string($name) || $name === '' || ! is_string($description)) {
            Log::warning('ptah: toolSchema() sem name/description utilizaveis.', ['tool' => $class]);

            return null;
        }

        $parameters = $schema['parameters'] ?? [];

        return $this->buildPrismTool(
            $name,
            $description,
            is_array($parameters) ? $parameters : [],
            // Resolved on the model's first call, not before. A failure here is
            // an error the model reads, not an exception that ends the turn.
            static fn (): AiToolInterface => app($class),
            $class,
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  callable(): AiToolInterface  $resolve
     */
    private function buildPrismTool(string $name, string $description, array $schema, callable $resolve, string $class): Tool
    {
        $props = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $prismTool = (new Tool)->as($name)->for($description);

        if (is_array($props)) {
            foreach ($props as $prop => $def) {
                if (! is_array($def)) {
                    continue;
                }

                $isRequired = is_array($required) && in_array($prop, $required, true);
                $desc = $def['description'] ?? $prop;
                $type = $def['type'] ?? 'string';

                $prismTool = match ($type) {
                    'number', 'integer' => $prismTool->withNumberParameter($prop, $desc, required: $isRequired),
                    'boolean' => $prismTool->withBooleanParameter($prop, $desc, required: $isRequired),
                    default => $prismTool->withStringParameter($prop, $desc, required: $isRequired),
                };
            }
        }

        // PHP 8 variadic spread preserves named argument keys:
        // $fn(...['status' => 'active']) → $args = ['status' => 'active']
        return $prismTool->using(static function (mixed ...$args) use ($name, $resolve, $class): string {
            // De novo na chamada: a lista foi montada antes, e a sessao pode
            // ter mudado de usuario ou de empresa desde entao.
            $context = AiToolContext::current();
            if (! self::allows($class, $context)) {
                return (string) json_encode([
                    'error' => true,
                    'code' => 'forbidden',
                    'message' => trans('ptah::ui.ai_tool_forbidden', ['tool' => $name]),
                ]);
            }

            try {
                $tool = $resolve();
                if ($tool instanceof AiToolContextAware) {
                    $tool->withContext($context);
                }

                return (string) json_encode($tool->execute($args));
            } catch (Throwable $e) {
                Log::error('ptah: tool de IA falhou durante a execucao.', [
                    'tool' => $name,
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                ]);

                // Handed back to the model as data. A throw here would abort the
                // whole turn over one tool the model chose to try.
                return (string) json_encode([
                    'error' => true,
                    'message' => trans('ptah::ui.ai_tool_failed', ['tool' => $name]),
                ]);
            }
        });
    }
}
