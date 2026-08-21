<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Validation\Formatters;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\PtahException;
use Ptah\Services\Validation\Formatters\FlashMessageFormatter;
use Ptah\Tests\TestCase;

/**
 * Covers FlashMessageFormatter's own default HTML formatter. Exceptions that
 * mix in Ptah\Exceptions\Concerns\FormatsError (e.g. ConfigValidationException)
 * define formatAsFlashMessage() themselves, so format() delegates to THAT
 * instead — these tests use a bare PtahException subclass (no such method)
 * specifically to exercise FlashMessageFormatter's own defaultFormat() path.
 */
class FlashMessageFormatterTest extends TestCase
{
    private FlashMessageFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new FlashMessageFormatter;
    }

    private function exception(string $message = 'Something went wrong', array $context = []): PtahException
    {
        return new class($message, 0, null, $context) extends PtahException {};
    }

    #[Test]
    public function delegates_to_the_exceptions_own_flash_formatter_when_present(): void
    {
        $exception = new class('boom') extends PtahException
        {
            public function formatAsFlashMessage(): string
            {
                return '<div>CUSTOM</div>';
            }
        };

        $this->assertSame('<div>CUSTOM</div>', $this->formatter->format($exception));
    }

    #[Test]
    public function default_format_wraps_the_message_in_an_alert_div_with_the_given_class(): void
    {
        $html = $this->formatter->format($this->exception('Something went wrong'), 'alert-warning');

        $this->assertStringContainsString('class="alert alert-warning"', $html);
        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Something went wrong', $html);
    }

    #[Test]
    public function default_format_escapes_html_special_characters_in_the_message(): void
    {
        $html = $this->formatter->format($this->exception('<script>alert(1)</script>'));

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    #[Test]
    public function default_format_lists_context_entries_with_localized_labels(): void
    {
        $html = $this->formatter->format($this->exception('bad column', [
            'field' => 'colsTipo',
            'section' => 'cols',
        ]));

        $this->assertStringContainsString('<strong>Campo:</strong> colsTipo', $html);
        $this->assertStringContainsString('<strong>Seção:</strong> cols', $html);
    }

    #[Test]
    public function format_for_livewire_returns_a_structured_array_not_html(): void
    {
        $array = $this->formatter->formatForLivewire($this->exception('bad column', ['field' => 'colsTipo']));

        $this->assertSame('error', $array['type']);
        $this->assertSame('bad column', $array['message']);
        $this->assertSame(['Campo' => 'colsTipo'], $array['context']);
        $this->assertArrayHasKey('title', $array);
    }

    #[Test]
    public function format_tailwind_renders_a_red_alert_card_with_the_message_and_context(): void
    {
        $html = $this->formatter->formatTailwind($this->exception('bad column', ['field' => 'colsTipo']));

        $this->assertStringContainsString('bg-red-50', $html);
        $this->assertStringContainsString('bad column', $html);
        $this->assertStringContainsString('<strong>Campo:</strong> colsTipo', $html);
    }

    #[Test]
    public function format_multiple_lists_every_exceptions_message_inside_one_alert(): void
    {
        $html = $this->formatter->formatMultiple([
            $this->exception('first problem'),
            $this->exception('second problem'),
        ]);

        $this->assertStringContainsString('Multiple Errors Detected', $html);
        $this->assertStringContainsString('Error #1:', $html);
        $this->assertStringContainsString('first problem', $html);
        $this->assertStringContainsString('Error #2:', $html);
        $this->assertStringContainsString('second problem', $html);
    }
}
