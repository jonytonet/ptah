<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class ImpCategory extends Model
{
    protected $table = 'imp_categories';

    protected $guarded = [];
}

class ImpProduct extends Model
{
    protected $table = 'imp_products';

    protected $fillable = ['sku', 'name', 'price', 'status', 'active', 'imp_category_id', 'company_id'];
}

/**
 * Spreadsheet import — each case is a file a real user sends, and the rule is
 * that nothing gets into the table that the screen's own form would refuse,
 * and a file is either imported whole or not at all.
 */
class CrudImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('imp_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->unsignedBigInteger('company_id');
            $t->timestamps();
        });

        Schema::create('imp_products', function (Blueprint $t) {
            $t->id();
            $t->string('sku')->unique();
            $t->string('name');
            $t->decimal('price', 10, 2)->nullable();
            $t->string('status');
            $t->boolean('active')->default(true);
            $t->unsignedBigInteger('imp_category_id')->nullable();
            $t->unsignedBigInteger('company_id')->nullable();
            $t->timestamps();
        });

        ImpCategory::create(['name' => 'Parafusos', 'company_id' => 1]);  // id 1
        ImpCategory::create(['name' => 'Porcas', 'company_id' => 1]);     // id 2
        ImpCategory::create(['name' => 'Porcas', 'company_id' => 2]);     // id 3 — outra empresa

        session(['ptah_company_id' => 1]);
        $this->configure();
    }

    private function configure(array $import = ['enabled' => true], array $permissions = []): void
    {
        CrudConfig::updateOrCreate(['model' => ImpProduct::class, 'route' => ''], ['config' => [
            'crud' => ImpProduct::class,
            'cols' => [
                ['colsNomeFisico' => 'sku', 'colsNomeLogico' => 'SKU', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsGravar' => true, 'colsRequired' => true],
                ['colsNomeFisico' => 'price', 'colsNomeLogico' => 'Preço', 'colsTipo' => 'number', 'colsGravar' => true, 'colsMaskTransform' => 'money_to_float'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Situação', 'colsTipo' => 'select', 'colsGravar' => true, 'colsRequired' => true, 'colsSelect' => ['Ativo' => 'active', 'Inativo' => 'inactive']],
                ['colsNomeFisico' => 'active', 'colsNomeLogico' => 'Disponível', 'colsTipo' => 'boolean', 'colsGravar' => true],
                ['colsNomeFisico' => 'imp_category_id', 'colsNomeLogico' => 'Categoria', 'colsTipo' => 'searchdropdown', 'colsGravar' => true, 'colsSDModel' => ImpCategory::class, 'colsSDLabel' => 'name', 'colsSDValor' => 'id'],
            ],
            'permissions' => $permissions,
            'importConfig' => $import,
        ]]);
    }

    private function csv(string $content, string $name = 'produtos.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function crud()
    {
        return Livewire::test(BaseCrud::class, ['model' => ImpProduct::class]);
    }

    /**
     * O CSV como o Excel brasileiro salva: `;`, Windows-1252, "1.234,56".
     */
    private function brazilianCsv(string ...$lines): UploadedFile
    {
        $content = implode("\r\n", array_merge(['SKU;Nome;Preço;Situação;Disponível;Categoria'], $lines))."\r\n";

        return $this->csv((string) mb_convert_encoding($content, 'Windows-1252', 'UTF-8'));
    }

    #[Test]
    public function import_is_off_unless_the_screen_enables_it(): void
    {
        $this->configure(import: []);

        $this->crud()
            ->assertDontSee(__('ptah::ui.btn_import'))
            ->call('openImport')
            ->assertSet('showImportModal', false);
    }

    #[Test]
    public function a_brazilian_excel_csv_imports_with_labels_names_and_money_resolved(): void
    {
        $crud = $this->crud()
            ->call('openImport')
            ->set('importFile', $this->brazilianCsv(
                'P-001;Parafuso sextavado;1.234,56;Ativo;sim;Parafusos',
                'P-002;Porca M8;0,90;Inativo;não;Porcas',
            ))
            ->assertSet('importStep', 2);

        // Cabecalhos com acento casam com o rotulo dos campos.
        $this->assertSame(['sku', 'name', 'price', 'status', 'active', 'imp_category_id'], array_values($crud->get('importMapping')));

        $crud->call('previewImport')
            ->assertSet('importStep', 3)
            ->assertSet('importPreview.valid', 2)
            ->call('runImport')
            ->assertSet('importStep', 4)
            ->assertSet('importResult.created', 2);

        $p1 = ImpProduct::where('sku', 'P-001')->first();
        $this->assertSame(1234.56, (float) $p1->price);
        $this->assertSame('active', $p1->status);
        $this->assertSame(1, (int) $p1->active);
        $this->assertSame(1, (int) $p1->imp_category_id);
        $this->assertSame(1, (int) $p1->company_id, 'O registro importado deveria cair na empresa ativa.');

        // "Porcas" existe nas empresas 1 e 2; a importacao usa a da empresa ativa.
        $this->assertSame(2, (int) ImpProduct::where('sku', 'P-002')->value('imp_category_id'));
    }

    #[Test]
    public function one_invalid_row_is_reported_by_line_and_nothing_is_imported(): void
    {
        $crud = $this->crud()
            ->call('openImport')
            ->set('importFile', $this->brazilianCsv(
                'P-001;Parafuso;10;Ativo;sim;Parafusos',
                'P-002;;10;Suspenso;talvez;Arruelas',
            ))
            ->call('previewImport');

        $errors = collect($crud->get('importPreview.errors'));
        $this->assertSame([3], $errors->pluck('line')->unique()->values()->all(), 'A linha 3 da planilha (2a de dados) tem os problemas.');
        $this->assertEqualsCanonicalizing(['name', 'status', 'active', 'imp_category_id'], $errors->pluck('field')->all());

        $crud->call('runImport');
        $this->assertSame(0, ImpProduct::count(), 'Com uma linha invalida, nada entra.');
    }

    #[Test]
    public function a_required_field_without_a_column_blocks_the_whole_import(): void
    {
        // A planilha nao tem a coluna "Nome" (obrigatoria): nada entra, e a
        // revisao diz qual campo falta — mesmo com todas as linhas "validas".
        $this->crud()
            ->call('openImport')
            ->set('importFile', $this->csv("SKU;Situação\nP-001;Ativo\nP-002;Inativo\n"))
            ->call('previewImport')
            ->assertSet('importPreview.unmapped_required', ['Nome'])
            ->assertSee('Nome')
            ->call('runImport');

        $this->assertSame(0, ImpProduct::count());
    }

    #[Test]
    public function a_row_the_database_refuses_rolls_the_whole_file_back(): void
    {
        ImpProduct::create(['sku' => 'P-002', 'name' => 'Existente', 'status' => 'active', 'company_id' => 1]);

        $this->crud()
            ->call('openImport')
            ->set('importFile', $this->brazilianCsv(
                'P-001;Parafuso;10;Ativo;sim;Parafusos',
                'P-002;Duplicado;10;Ativo;sim;Parafusos',
            ))
            ->call('previewImport')
            ->call('runImport')
            ->assertSet('importStep', 4)
            ->assertSee('3');

        $this->assertSame(1, ImpProduct::count(), 'A primeira linha nao pode ficar gravada sozinha.');
        $this->assertNull(ImpProduct::where('sku', 'P-001')->first());
    }

    #[Test]
    public function a_forged_mapping_cannot_write_a_field_outside_the_form(): void
    {
        $this->crud()
            ->call('openImport')
            ->set('importFile', $this->csv("sku,name,status,company_id\nP-001,Parafuso,active,99\n"))
            ->set('importMapping', [0 => 'sku', 1 => 'name', 2 => 'status', 3 => 'company_id'])
            ->call('previewImport')
            ->call('runImport');

        $this->assertSame(1, (int) ImpProduct::where('sku', 'P-001')->value('company_id'), 'company_id veio do mapeamento forjado.');
    }

    #[Test]
    public function upsert_updates_by_key_inside_the_active_company_only(): void
    {
        $this->configure(import: ['enabled' => true, 'mode' => 'upsert', 'key' => 'sku']);
        ImpProduct::create(['sku' => 'P-001', 'name' => 'Antigo', 'status' => 'inactive', 'company_id' => 1]);
        ImpProduct::create(['sku' => 'X-002', 'name' => 'De outra empresa', 'status' => 'active', 'company_id' => 2]);

        $this->crud()
            ->call('openImport')
            ->set('importFile', $this->csv("SKU,Nome,Situação\nP-001,Novo nome,Ativo\nP-003,Criado,Ativo\n"))
            ->call('previewImport')
            ->call('runImport')
            ->assertSet('importResult.updated', 1)
            ->assertSet('importResult.created', 1);

        $this->assertSame('Novo nome', ImpProduct::where('sku', 'P-001')->value('name'));
        $this->assertSame('De outra empresa', ImpProduct::where('sku', 'X-002')->value('name'));
    }

    #[Test]
    public function an_xlsx_file_imports_too(): void
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([['SKU', 'Nome', 'Preço', 'Situação'], ['X-1', 'Chave', 12.5, 'Ativo']]);
        $path = sys_get_temp_dir().'/ptah-import-'.uniqid().'.xlsx';
        (new Xlsx($sheet))->save($path);

        try {
            $this->crud()
                ->call('openImport')
                ->set('importFile', UploadedFile::fake()->createWithContent('planilha.xlsx', (string) file_get_contents($path)))
                ->assertHasNoErrors('importFile')
                ->call('previewImport')
                ->call('runImport')
                ->assertSet('importResult.created', 1);
        } finally {
            @unlink($path);
        }

        $this->assertSame(12.5, (float) ImpProduct::where('sku', 'X-1')->value('price'));
    }

    #[Test]
    public function the_import_gate_is_enforced(): void
    {
        $this->configure(permissions: ['import' => 'products.import']);

        $this->crud()
            ->call('openImport')
            ->assertSet('showImportModal', false);
    }

    #[Test]
    public function a_file_over_the_row_limit_is_refused(): void
    {
        $this->configure(import: ['enabled' => true, 'maxRows' => 1]);

        $this->crud()
            ->call('openImport')
            ->set('importFile', $this->csv("sku,name,status\nA,a,active\nB,b,active\n"))
            ->assertHasErrors('importFile')
            ->assertSet('importStep', 1);
    }
}
