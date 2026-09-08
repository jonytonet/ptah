<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\AI;

use PHPUnit\Framework\Attributes\Test;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Media\Text;
use Ptah\Services\AI\AiAttachmentService;
use Ptah\Tests\TestCase;

/**
 * Turning attached files into the parts a Prism message carries.
 *
 * The recurring theme is that a file which cannot be delivered must produce a
 * NOTE in the prompt rather than nothing. Silence is the dangerous outcome: the
 * model gets "analyse this" with no file, and answers about content it invented.
 */
class AiAttachmentPartsTest extends TestCase
{
    /** @var list<string> */
    private array $temp = [];

    protected function tearDown(): void
    {
        foreach ($this->temp as $path) {
            @unlink($path);
        }

        $this->temp = [];

        parent::tearDown();
    }

    /**
     * @return array{path: string, name: string, mime: string}
     */
    private function file(string $name, string $contents, string $mime): array
    {
        $path = sys_get_temp_dir().'/ptah-anexo-'.uniqid().'-'.$name;
        file_put_contents($path, $contents);
        $this->temp[] = $path;

        return ['path' => $path, 'name' => $name, 'mime' => $mime];
    }

    private function service(): AiAttachmentService
    {
        return $this->app->make(AiAttachmentService::class);
    }

    /**
     * As partes de um tipo, sem a linha do manifesto.
     *
     * O manifesto ("o usuario anexou X") entra como a PRIMEIRA parte, entao
     * afirmar sobre `parts[0]` passou a depender de uma decisao de ordem que
     * nada aqui quer fixar. Selecionar por tipo diz o que estes testes querem
     * dizer de verdade.
     *
     * @param  array{parts: array<int, mixed>, notes: list<string>}  $built
     * @return list<mixed>
     */
    private function partsOfType(array $built, string $class): array
    {
        return array_values(array_filter(
            $built['parts'],
            fn ($part): bool => $part instanceof $class
        ));
    }

    /** A 1x1 PNG, so Image::fromLocalPath has a real file to read. */
    private function png(string $name = 'print.png'): array
    {
        $bytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );

        return $this->file($name, (string) $bytes, 'image/png');
    }

    #[Test]
    public function an_image_becomes_an_image_part_on_a_document_blind_provider(): void
    {
        // The headline case: a pasted screenshot has to work on Grok, which is
        // where the report came from.
        $built = $this->service()->toPrismParts([$this->png()], 'xai');

        $this->assertCount(1, $this->partsOfType($built, Image::class));
        $this->assertSame([], $built['notes']);
    }

    #[Test]
    public function a_pdf_becomes_a_document_part_where_the_provider_takes_one(): void
    {
        $built = $this->service()->toPrismParts(
            [$this->file('relatorio.pdf', '%PDF-1.4 conteudo', 'application/pdf')],
            'openai'
        );

        $this->assertCount(1, $this->partsOfType($built, Document::class));
        $this->assertSame([], $built['notes']);
    }

    #[Test]
    public function a_text_file_travels_inline_on_every_provider(): void
    {
        foreach (['xai', 'openai'] as $provider) {
            $built = $this->service()->toPrismParts(
                [$this->file('dados.csv', "cliente;total\nAcme;1200", 'text/csv')],
                $provider
            );

            $texts = $this->partsOfType($built, Text::class);

            // Manifesto + conteudo.
            $this->assertCount(2, $texts, "Falhou em {$provider}.");

            $joined = implode(PHP_EOL, array_map(fn ($t): string => $t->text, $texts));

            $this->assertStringContainsString('Acme;1200', $joined);
            // The model must know which file the text came from.
            $this->assertStringContainsString('dados.csv', $joined);
        }
    }

    #[Test]
    public function a_pdf_on_a_blind_provider_produces_a_note_and_no_part(): void
    {
        // Not reachable through the widget, which narrows the allowlist — but
        // send() is a public Livewire method, so the service still has to be
        // honest about it. A note, never silence.
        $built = $this->service()->toPrismParts(
            [$this->file('relatorio.pdf', '%PDF-1.4', 'application/pdf')],
            'xai'
        );

        $this->assertSame([], $built['parts']);
        $this->assertCount(1, $built['notes']);
        $this->assertStringContainsString('relatorio.pdf', $built['notes'][0]);
    }

    #[Test]
    public function a_file_that_vanished_produces_a_note(): void
    {
        // The temporary upload is gone by the time the second request runs —
        // the disk was cleaned, the worker moved. The message still sends.
        $built = $this->service()->toPrismParts(
            [['path' => sys_get_temp_dir().'/nao-existe-'.uniqid().'.pdf', 'name' => 'sumiu.pdf', 'mime' => 'application/pdf']],
            'openai'
        );

        $this->assertSame([], $built['parts']);
        $this->assertStringContainsString('sumiu.pdf', $built['notes'][0]);
    }

    #[Test]
    public function an_empty_text_file_produces_a_note_rather_than_an_empty_part(): void
    {
        $built = $this->service()->toPrismParts(
            [$this->file('vazio.txt', "   \n ", 'text/plain')],
            'xai'
        );

        $this->assertSame([], $built['parts']);
        $this->assertCount(1, $built['notes']);
    }

    #[Test]
    public function one_bad_file_does_not_cost_the_others(): void
    {
        $built = $this->service()->toPrismParts([
            $this->png('bom.png'),
            ['path' => '/caminho/que/nao/existe.pdf', 'name' => 'ruim.pdf', 'mime' => 'application/pdf'],
            $this->file('notas.txt', 'conteudo real', 'text/plain'),
        ], 'xai');

        $this->assertCount(1, $this->partsOfType($built, Image::class), 'A imagem boa tinha de passar.');
        $this->assertCount(1, $built['notes']);
    }

    #[Test]
    public function a_long_text_file_is_truncated_with_a_visible_marker(): void
    {
        // Silent truncation is how a model comes to summarise a fragment as the
        // whole document.
        config(['ptah.ai_agent.attachments.max_extracted_chars' => 1000]);

        $built = $this->service()->toPrismParts(
            [$this->file('grande.txt', str_repeat('a', 5000), 'text/plain')],
            'xai'
        );

        $texts = $this->partsOfType($built, Text::class);
        $text = end($texts)->text;

        $this->assertLessThan(5000, mb_strlen($text));
        $this->assertStringContainsString(trans('ptah::ui.ai_attach_truncated'), $text);
    }

    #[Test]
    public function a_latin1_text_file_is_converted_rather_than_sent_as_broken_utf8(): void
    {
        // Invalid UTF-8 in the JSON body is a hard error from the provider, so
        // the whole turn would fail on an accented filename's worth of bytes.
        $latin1 = (string) mb_convert_encoding('relatório com acentuação', 'ISO-8859-1', 'UTF-8');

        $this->assertFalse(mb_check_encoding($latin1, 'UTF-8'), 'O arranjo do teste precisa ser invalido em UTF-8.');

        $built = $this->service()->toPrismParts(
            [$this->file('latin.txt', $latin1, 'text/plain')],
            'xai'
        );

        $texts = $this->partsOfType($built, Text::class);
        $joined = implode(PHP_EOL, array_map(fn ($t): string => $t->text, $texts));

        $this->assertTrue(mb_check_encoding($joined, 'UTF-8'));
        $this->assertStringContainsString('acentuação', $joined);
    }

    #[Test]
    public function an_image_is_recognised_by_extension_when_the_mime_is_unhelpful(): void
    {
        // A pasted screenshot arrives with no filename and browsers sometimes
        // label an upload application/octet-stream.
        $png = $this->png('captura.png');
        $png['mime'] = 'application/octet-stream';

        $built = $this->service()->toPrismParts([$png], 'xai');

        $this->assertCount(1, $this->partsOfType($built, Image::class));
    }

    #[Test]
    public function no_files_means_no_parts_and_no_notes(): void
    {
        $built = $this->service()->toPrismParts([], 'xai');

        $this->assertSame([], $built['parts']);
        $this->assertSame([], $built['notes']);
    }
}
