<?php

declare(strict_types=1);

namespace Ptah\Tests\Unit\Services\Validation\Formatters;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Exceptions\PtahException;
use Ptah\Services\Validation\Formatters\CliErrorFormatter;
use Ptah\Tests\TestCase;

/**
 * Covers CliErrorFormatter's own default box-drawing formatter. Exceptions
 * that mix in Ptah\Exceptions\Concerns\FormatsError (e.g. ConfigValidationException)
 * define formatAsCliOutput() themselves, so format() delegates to THAT instead —
 * these tests use a bare PtahException subclass (no such method) specifically
 * to exercise CliErrorFormatter's own defaultFormat() path.
 */
class CliErrorFormatterTest extends TestCase
{
    private CliErrorFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new CliErrorFormatter;
    }

    private function exception(string $message = 'Something went wrong', array $context = []): PtahException
    {
        return new class($message, 0, null, $context) extends PtahException {};
    }

    #[Test]
    public function delegates_to_the_exceptions_own_cli_formatter_when_present(): void
    {
        $exception = new class('boom') extends PtahException
        {
            public function formatAsCliOutput(): string
            {
                return 'CUSTOM CLI OUTPUT';
            }
        };

        $this->assertSame('CUSTOM CLI OUTPUT', $this->formatter->format($exception));
    }

    #[Test]
    public function default_format_wraps_the_message_in_a_box_with_the_error_title(): void
    {
        $output = $this->formatter->format($this->exception('Something went wrong'));

        $this->assertStringContainsString('╔', $output);
        $this->assertStringContainsString('╗', $output);
        $this->assertStringContainsString('╚', $output);
        $this->assertStringContainsString('╝', $output);
        $this->assertStringContainsString('Something went wrong', $output);
    }

    #[Test]
    public function default_format_lists_context_entries_with_localized_labels(): void
    {
        $output = $this->formatter->format($this->exception('bad column', [
            'field' => 'colsTipo',
            'available_options' => ['text', 'number'],
        ]));

        $this->assertStringContainsString('Campo', $output);
        $this->assertStringContainsString('colsTipo', $output);
        $this->assertStringContainsString('Opções válidas', $output);
        $this->assertStringContainsString('text, number', $output);
    }

    #[Test]
    public function default_format_falls_back_to_a_headlined_label_for_unmapped_context_keys(): void
    {
        $output = $this->formatter->format($this->exception('x', ['custom_key' => 'value']));

        $this->assertStringContainsString('Custom Key', $output);
    }

    #[Test]
    public function default_format_renders_booleans_and_null_as_literal_strings(): void
    {
        $output = $this->formatter->format($this->exception('x', [
            'flag_on' => true,
            'flag_off' => false,
            'nothing' => null,
        ]));

        $this->assertStringContainsString('true', $output);
        $this->assertStringContainsString('false', $output);
        $this->assertStringContainsString('null', $output);
    }

    #[Test]
    public function default_format_wraps_long_message_lines_within_the_configured_width(): void
    {
        $longMessage = 'This is a deliberately long error message meant to exceed the default max width of the CLI box drawing output';
        $output = $this->formatter->format($this->exception($longMessage), maxWidth: 40);

        // Message/context lines are word-wrapped by wrapText() to the box
        // width; the title line is not (getErrorTitle() does not wrap), so it
        // is excluded here — an anonymous test class's generated name is far
        // longer than any real Ptah exception's, and asserting on it would
        // measure the test double's own class name, not the formatter.
        $lines = explode("\n", trim($output));
        $messageLines = array_slice($lines, 2, -1);

        $this->assertNotEmpty($messageLines);
        foreach ($messageLines as $line) {
            $this->assertLessThanOrEqual(40, mb_strlen($line));
        }
    }

    #[Test]
    public function format_multiple_numbers_each_exception_and_includes_its_output(): void
    {
        $output = $this->formatter->formatMultiple([
            $this->exception('first problem'),
            $this->exception('second problem'),
        ]);

        $this->assertStringContainsString('Error #1:', $output);
        $this->assertStringContainsString('first problem', $output);
        $this->assertStringContainsString('Error #2:', $output);
        $this->assertStringContainsString('second problem', $output);
        $this->assertStringContainsString('Multiple Errors Detected', $output);
    }
}
