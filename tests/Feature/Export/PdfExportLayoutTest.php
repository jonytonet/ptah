<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Support\PdfExportLayout;
use Ptah\Tests\TestCase;

class PdfLayoutItem extends Model
{
    protected $table = 'items';

    protected $fillable = ['name', 'status', 'amount'];
}

class OrientationRecordingDompdf
{
    public string $orientation = '';

    /** @var array<string, mixed> */
    public array $data = [];

    public function loadView(string $view, array $data = [], array $mergeData = [], ?string $encoding = null): static
    {
        $this->data = $data;

        return $this;
    }

    public function setPaper(string $paper, string $orientation = 'portrait'): static
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function download(string $filename = 'document.pdf')
    {
        return response('fake-pdf');
    }
}

/**
 * 1.43.2: the PDF was always A4 portrait — `exportConfig.orientation` was saved
 * by the editor and never read — so a wide listing lost its right-hand
 * columns. And the download opened a blank tab.
 */
class PdfExportLayoutTest extends TestCase
{
    #[Test]
    public function many_columns_turn_the_page_to_landscape(): void
    {
        $this->assertSame('portrait', PdfExportLayout::orientation(null, 4));
        $this->assertSame('landscape', PdfExportLayout::orientation(null, 9));
        $this->assertSame('landscape', PdfExportLayout::orientation('portrait', 9), 'Retrato com muitas colunas cortava.');
        $this->assertSame('landscape', PdfExportLayout::orientation('landscape', 2), 'Paisagem escolhida e respeitada.');
        $this->assertLessThan(PdfExportLayout::fontSize(4), PdfExportLayout::fontSize(15));
    }

    #[Test]
    public function the_configured_orientation_reaches_the_pdf(): void
    {
        CrudConfig::create(['model' => PdfLayoutItem::class, 'route' => '', 'config' => [
            'crud' => PdfLayoutItem::class,
            'cols' => [['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text']],
            'exportConfig' => ['enabled' => true, 'orientation' => 'landscape'],
            'permissions' => [],
        ]]);
        PdfLayoutItem::create(['name' => 'Alpha', 'status' => 'open', 'amount' => 1]);

        $url = null;
        Livewire::test(BaseCrud::class, ['model' => PdfLayoutItem::class])->call('export', 'pdf')
            ->assertDispatched('ptah:export-download', function ($e, $p) use (&$url) {
                $url = $p['url'] ?? null;

                return $url !== null;
            });

        $this->assertSame('landscape', Cache::get('ptah:export:'.basename((string) parse_url((string) $url, PHP_URL_PATH)))['orientation']);

        $pdf = new OrientationRecordingDompdf;
        $this->app->instance('dompdf.wrapper', $pdf);
        $this->get((string) $url)->assertOk();

        $this->assertSame('landscape', $pdf->orientation, 'Uma coluna so, mas a config pede paisagem.');
        $this->assertSame(9, $pdf->data['fontSize']);
    }

    #[Test]
    public function the_pdf_table_shares_the_page_width_instead_of_running_off_it(): void
    {
        $view = (string) file_get_contents(__DIR__.'/../../../resources/views/exports/pdf.blade.php');

        $this->assertStringContainsString('table-layout: fixed', $view);
    }

    #[Test]
    public function the_download_does_not_open_a_tab(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../../resources/views/livewire/base-crud/partials/_scripts.blade.php');
        $listener = substr($js, (int) strpos($js, "Livewire.on('ptah:export-download'"), 600);

        $this->assertStringNotContainsString('window.open', $listener, 'A aba aberta para um anexo ficava em branco.');
        $this->assertStringContainsString('a.download', $listener);
    }
}
