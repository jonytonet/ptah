<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Tests\TestCase;

class UsageCategory extends Model
{
    protected $table = 'usage_categories';

    protected $guarded = [];
}

class UsageTicket extends Model
{
    protected $table = 'usage_tickets';

    protected $guarded = [];

    protected $casts = ['resolved_at' => 'datetime', 'due_on' => 'date'];
}

/**
 * Found driving PetPlace's Ticket screen in a browser (1.60.5).
 *
 * - A model-mode searchdropdown without colsRelacao (what the editor writes)
 *   opened the edit form with no label — or with the label of the record
 *   edited before, since sdLabels were also saved in the user's preferences
 *   and came back on the next page load: "Diego" shown over Ana's id.
 * - A date cast reached <input type="date"> as ISO-8601 UTC, which it rejects:
 *   the edit form opened with the date blank.
 * - A NOT NULL column the screen does not mark as required came back as raw
 *   SQL (or "could not save") instead of an error on the field.
 */
class CrudFormUsageRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['ptah.modules.permissions' => false]);
        $this->actingAs(new GenericUser(['id' => 5]));

        Schema::create('usage_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->timestamps();
        });
        Schema::create('usage_tickets', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('description');
            $t->unsignedBigInteger('category_id')->nullable();
            $t->dateTime('resolved_at')->nullable();
            $t->date('due_on')->nullable();
            $t->string('at_time')->nullable();
            $t->timestamps();
        });

        UsageCategory::create(['name' => 'Banho']);
        UsageCategory::create(['name' => 'Vacinação']);
        UsageTicket::create(['title' => 'A', 'description' => 'x', 'category_id' => 1, 'resolved_at' => '2026-08-26 12:45:00', 'due_on' => '2026-03-15', 'at_time' => '14:30:00']);
        UsageTicket::create(['title' => 'B', 'description' => 'y', 'category_id' => 2]);

        CrudConfig::updateOrCreate(['model' => UsageTicket::class, 'route' => ''], ['config' => [
            'crud' => UsageTicket::class,
            'cols' => [
                ['colsNomeFisico' => 'title', 'colsNomeLogico' => 'Título', 'colsTipo' => 'text', 'colsGravar' => true],
                ['colsNomeFisico' => 'description', 'colsNomeLogico' => 'Descrição', 'colsTipo' => 'text', 'colsGravar' => true],
                [
                    'colsNomeFisico' => 'category_id', 'colsNomeLogico' => 'Categoria', 'colsTipo' => 'searchdropdown', 'colsGravar' => true,
                    'colsRelacao' => '', 'colsRelacaoExibe' => '',
                    'colsSDTipo' => 'model', 'colsSDModel' => UsageCategory::class, 'colsSDValor' => 'id', 'colsSDLabel' => 'name',
                ],
                ['colsNomeFisico' => 'resolved_at', 'colsNomeLogico' => 'Resolvido', 'colsTipo' => 'datetime', 'colsGravar' => true],
                ['colsNomeFisico' => 'due_on', 'colsNomeLogico' => 'Prazo', 'colsTipo' => 'date', 'colsGravar' => true],
                ['colsNomeFisico' => 'at_time', 'colsNomeLogico' => 'Hora', 'colsTipo' => 'time', 'colsGravar' => true],
            ],
            'permissions' => [],
        ]]);
    }

    #[Test]
    public function the_edit_form_shows_the_label_of_the_records_own_searchdropdown_value(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])->call('openEdit', 1);
        $this->assertSame('Banho', $crud->get('sdLabels')['category_id'] ?? null);

        // The next record gets ITS label, not the previous one.
        $crud->call('closeModal')->call('openEdit', 2);
        $this->assertSame('Vacinação', $crud->get('sdLabels')['category_id'] ?? null);

        // A duplicate shows the copied value's label.
        $crud->call('closeModal')->call('duplicateRecord', 1);
        $this->assertSame(1, $crud->get('formData.category_id'));
        $this->assertSame('Banho', $crud->get('sdLabels')['category_id'] ?? null);
    }

    #[Test]
    public function a_record_whose_value_is_empty_or_gone_opens_without_a_stale_label(): void
    {
        UsageTicket::create(['title' => 'C', 'description' => 'z', 'category_id' => null]);
        UsageTicket::create(['title' => 'D', 'description' => 'w', 'category_id' => 99]);

        $crud = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])->call('openEdit', 1);
        $crud->call('closeModal')->call('openEdit', 3);
        $this->assertArrayNotHasKey('category_id', $crud->get('sdLabels'));
        $crud->call('closeModal')->call('openEdit', 4);
        $this->assertArrayNotHasKey('category_id', $crud->get('sdLabels'));
    }

    #[Test]
    public function form_labels_are_not_saved_in_the_preferences_nor_restored_from_them(): void
    {
        Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])
            ->call('openEdit', 1)
            ->call('savePreferences');

        $stored = json_encode(UserPreference::query()->get()->toArray());
        $this->assertStringNotContainsString('Banho', (string) $stored);

        // A preference row written by an older release still carries them.
        $pref = UserPreference::query()->first();
        $value = $pref->value;
        $value['filters']['sdLabels'] = ['category_id' => 'Banho'];
        $pref->value = $value;
        $pref->save();

        $fresh = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class]);
        $this->assertSame([], $fresh->get('sdLabels'));
    }

    #[Test]
    public function date_datetime_and_time_fields_open_in_the_native_inputs_format(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])->call('openEdit', 1);

        $this->assertSame('2026-08-26T12:45', $crud->get('formData.resolved_at'));
        $this->assertSame('2026-03-15', $crud->get('formData.due_on'));
        $this->assertSame('14:30', $crud->get('formData.at_time'));

        // Saved untouched, the values round-trip.
        $crud->call('save')->assertSet('formErrors', []);
        $row = UsageTicket::find(1);
        $this->assertSame('2026-08-26 12:45', $row->resolved_at->format('Y-m-d H:i'));
        $this->assertSame('2026-03-15', $row->due_on->format('Y-m-d'));
    }

    #[Test]
    public function a_not_null_column_left_blank_is_an_error_on_that_field(): void
    {
        $crud = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])
            ->call('openCreate')
            ->set('formData.title', 'Sem descrição')
            ->call('save');

        $errors = $crud->get('formErrors');
        $this->assertSame(trans('ptah::ui.validation_required', ['label' => 'Descrição']), $errors['description'] ?? null);
        $this->assertArrayNotHasKey('_general', $errors);
        $this->assertSame(2, UsageTicket::count());
    }

    #[Test]
    public function the_filter_panels_searchdropdown_has_a_static_x_data_and_clears_the_typed_text_on_pick(): void
    {
        $config = CrudConfig::where('model', UsageTicket::class)->first();
        $cfg = $config->config;
        $cfg['cols'][2]['colsIsFilterable'] = true;
        $config->config = $cfg;
        $config->save();

        $crud = Livewire::test(BaseCrud::class, ['model' => UsageTicket::class])->set('showFilters', true);
        $before = $crud->html();
        $crud->call('filterSearchDropdown', 'category_id', 'Ban');
        $after = $crud->html();

        // The x-data of the filter's dropdown is the same before and after the
        // search: a changing attribute made the morph restart it (1.60.5).
        $xdata = function (string $html): string {
            $at = strpos($html, "filterSearchDropdown('category_id'");
            $this->assertNotFalse($at);
            $start = strrpos(substr($html, 0, $at), 'x-data="');

            return substr($html, $start, strpos($html, '"', $start + 8) - $start);
        };
        $this->assertSame($xdata($before), $xdata($after));
        $this->assertStringContainsString('open: false', $xdata($after));

        // Picking empties the search text, the chip shows the choice.
        $this->assertStringContainsString("\$root.querySelector('input').value = ''", $after);
    }

    #[Test]
    public function the_not_null_message_of_each_driver_names_the_column(): void
    {
        $crud = new class extends BaseCrud
        {
            /** @param list<array<string, mixed>> $cols */
            public function probe(\Throwable $e, array $cols): ?array
            {
                return $this->notNullViolationField($e, $cols);
            }
        };
        $cols = [['colsNomeFisico' => 'description', 'colsNomeLogico' => 'Descrição']];
        $pdo = fn (string $m) => new \PDOException($m);

        foreach ([
            'SQLSTATE[23000]: Integrity constraint violation: 19 NOT NULL constraint failed: tickets.description',
            "SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'description' cannot be null",
            "SQLSTATE[HY000]: General error: 1364 Field 'description' doesn't have a default value",
            'SQLSTATE[23502]: Not null violation: 7 ERROR:  null value in column "description" of relation "tickets" violates not-null constraint',
        ] as $message) {
            $this->assertSame('description', $crud->probe($pdo($message), $cols)['colsNomeFisico'] ?? null, $message);
        }

        // A column the form does not show, or another error: not a field error.
        $this->assertNull($crud->probe($pdo('NOT NULL constraint failed: tickets.company_id'), $cols));
        $this->assertNull($crud->probe($pdo('UNIQUE constraint failed: tickets.description'), $cols));
        $this->assertNull($crud->probe(new \RuntimeException('NOT NULL constraint failed: tickets.description'), $cols));
    }
}
