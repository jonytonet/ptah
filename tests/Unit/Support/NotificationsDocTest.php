<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Keeps docs/Notifications.md honest against the code it documents.
 *
 * The August 2026 documentation audit found 11 outright lies across the docs —
 * a CLI syntax section that was almost entirely fictional, config keys that
 * never existed, a command documented but absent. The cure that works is not
 * proofreading: it is a test that fails when the doc and the code disagree.
 */
class NotificationsDocTest extends TestCase
{
    private static function read(string $relative): string
    {
        $path = dirname(__DIR__, 3).'/'.$relative;
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException('NotificationsDocTest: falha ao ler '.$relative);
        }

        return $content;
    }

    #[Test]
    public function every_service_method_in_the_reference_block_exists(): void
    {
        $doc = self::read('docs/Notifications.md');
        $service = self::read('src/Services/Notification/NotificationService.php');
        $helpers = self::read('src/helpers.php');

        // Only the fenced reference block, not prose that happens to mention a
        // name — a guard that reads its own explanatory text is the single most
        // recurrent way these tests go wrong in this codebase.
        if (preg_match('/```php\n(push\(int.*?)```/s', $doc, $block) !== 1) {
            $this->fail('docs/Notifications.md: bloco de referencia do service nao encontrado (o doc mudou de forma?).');
        }

        preg_match_all('/^(\w+)\(/m', $block[1], $matches);
        $missing = [];

        foreach (array_unique($matches[1]) as $method) {
            if (! str_contains($service, "function {$method}(")) {
                $missing[] = $method;
            }
        }

        $this->assertSame([], $missing, 'Metodos documentados que nao existem no NotificationService: '.implode(', ', $missing));

        // The three global helpers are documented outside that block.
        foreach (['ptah_notify', 'ptah_notify_role', 'ptah_notify_all'] as $helper) {
            $this->assertStringContainsString("function {$helper}(", $helpers, "Helper documentado ausente: {$helper}");
            $this->assertStringContainsString($helper, $doc, "Helper existente mas nao documentado: {$helper}");
        }
    }

    #[Test]
    public function every_env_var_mentioned_exists_in_the_config(): void
    {
        $doc = self::read('docs/Notifications.md');
        $config = self::read('config/ptah.php');

        preg_match_all('/PTAH_[A-Z_]+/', $doc, $matches);
        $missing = [];

        foreach (array_unique($matches[0]) as $env) {
            if (! str_contains($config, $env)) {
                $missing[] = $env;
            }
        }

        $this->assertSame([], $missing, 'Envs documentados ausentes de config/ptah.php: '.implode(', ', $missing));
    }

    #[Test]
    public function the_documented_hidden_values_match_the_resolver(): void
    {
        $doc = self::read('docs/Notifications.md');
        $resolver = self::read('src/Support/NavbarSlot.php');

        if (preg_match("/HIDDEN_VALUES = \[(.*?)\]/s", $resolver, $m) !== 1) {
            $this->fail('NavbarSlot: HIDDEN_VALUES nao encontrado.');
        }

        preg_match_all("/'([a-z0-9]+)'/", $m[1], $values);

        foreach ($values[1] as $value) {
            $this->assertStringContainsString(
                $value,
                $doc,
                "O valor '{$value}' oculta o sino no codigo mas nao esta documentado — quem le a doc nao consegue prever o comportamento."
            );
        }
    }

    #[Test]
    public function the_readme_links_the_document(): void
    {
        $this->assertStringContainsString('docs/Notifications.md', self::read('README.md'));
    }
}
