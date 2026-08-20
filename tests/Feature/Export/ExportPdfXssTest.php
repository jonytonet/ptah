<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/** Unsaved stub on the shared `items` table — only used to exercise toArray()/data_get() in the view. */
class PdfExportStub extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

/**
 * exports/pdf.blade.php renders every cell through two raw @php blocks (the
 * "no columns configured" branch and the "visible columns" branch) that echo
 * the DB value directly. A crafted string field (e.g. a free-text name/notes
 * column) could inject markup/script into the HTML DomPDF turns into the PDF.
 */
class ExportPdfXssTest extends TestCase
{
    private function renderPdfView(array $data, array $columns = []): string
    {
        return view('ptah::exports.pdf', [
            'data' => collect($data),
            'columns' => $columns,
            'modelName' => 'Items',
            'date' => now()->format('d/m/Y H:i:s'),
            'totalizers' => [],
        ])->render();
    }

    #[Test]
    public function the_no_columns_branch_escapes_a_malicious_string_value(): void
    {
        $row = new PdfExportStub(['name' => '<script>alert(1)</script>']);

        $html = $this->renderPdfView([$row]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    #[Test]
    public function the_visible_columns_branch_escapes_a_malicious_string_value(): void
    {
        $row = new PdfExportStub(['name' => '<script>alert(1)</script>']);

        $html = $this->renderPdfView([$row], [
            ['field' => 'name', 'label' => 'Name'],
        ]);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    #[Test]
    public function a_truncated_long_string_is_still_escaped(): void
    {
        $row = new PdfExportStub(['name' => '<b>'.str_repeat('a', 120).'</b>']);

        $html = $this->renderPdfView([$row], [
            ['field' => 'name', 'label' => 'Name'],
        ]);

        $this->assertStringNotContainsString('<b>', $html);
        $this->assertStringContainsString('&lt;b&gt;', $html);
    }
}
