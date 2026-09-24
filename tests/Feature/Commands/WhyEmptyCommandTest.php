<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Models\UserPreference;
use Ptah\Support\WhyEmptyInspector;
use Ptah\Tests\TestCase;

class WhyEmptyUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}

class WhyEmptyOrder extends Model
{
    use SoftDeletes;

    protected $table = 'why_orders';

    protected $guarded = [];
}

/**
 * `ptah:why-empty` — each case is a real way a screen ends up empty for one
 * user while it is full for another, and the report must point at the layer
 * where the rows disappear.
 */
class WhyEmptyCommandTest extends TestCase
{
    private WhyEmptyUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.providers.users.model' => WhyEmptyUser::class]);

        Schema::create('why_orders', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('status');
            $t->unsignedBigInteger('company_id');
            $t->timestamps();
            $t->softDeletes();
        });

        foreach ([['A1', 'open', 1], ['A2', 'open', 1], ['A3', 'closed', 1], ['B1', 'open', 2]] as [$code, $status, $company]) {
            WhyEmptyOrder::create(['code' => $code, 'status' => $status, 'company_id' => $company]);
        }
        WhyEmptyOrder::create(['code' => 'X9', 'status' => 'open', 'company_id' => 1])->delete();

        CrudConfig::create(['model' => WhyEmptyOrder::class, 'route' => '', 'config' => [
            'crud' => WhyEmptyOrder::class,
            'cols' => [
                ['colsNomeFisico' => 'code', 'colsNomeLogico' => 'Code', 'colsTipo' => 'text'],
                ['colsNomeFisico' => 'status', 'colsNomeLogico' => 'Status', 'colsTipo' => 'text'],
            ],
            'permissions' => [],
        ]]);

        $this->user = WhyEmptyUser::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'x']);
        $this->actingAs($this->user);
    }

    private function savePrefs(array $filters): void
    {
        UserPreference::set(userId: $this->user->id, key: 'crud.'.WhyEmptyOrder::class, value: [
            '_version' => '2.2.0',
            'table' => ['perPage' => 25],
            'filters' => $filters,
        ], group: 'crud');
    }

    /**
     * @return array<string, int|null>
     */
    private function counts(array $report): array
    {
        return array_column($report['layers'], 'rows', 'layer');
    }

    #[Test]
    public function a_filter_saved_in_the_preferences_is_named_as_the_cause(): void
    {
        // O usuario filtrou "cancelled" semana passada; a preferencia volta sozinha.
        $this->savePrefs(['lastUsed' => ['status' => 'cancelled']]);

        $report = WhyEmptyInspector::inspect(WhyEmptyOrder::class);
        $counts = $this->counts($report);

        $this->assertSame(5, $counts['table why_orders'], 'Conta tudo, inclusive o apagado.');
        $this->assertSame(4, $counts['model global scopes'], 'SoftDeletes tira o apagado.');
        $this->assertSame(0, $counts['column filters']);
        $this->assertSame(0, $report['final']);
        $this->assertStringContainsString("status='cancelled'", $report['layers'][array_key_last($report['layers'])]['note']);
    }

    #[Test]
    public function the_active_company_is_named_when_it_has_no_rows(): void
    {
        session([config('ptah.permissions.company_session_key', 'ptah_company_id') => 7]);

        $report = WhyEmptyInspector::inspect(WhyEmptyOrder::class);
        $counts = $this->counts($report);

        $this->assertSame(4, $counts['screen base (locked, whereHas, custom)']);
        $this->assertSame(0, $counts['company filter']);
    }

    #[Test]
    public function a_failing_listing_query_is_reported_not_shown_as_empty(): void
    {
        // Um filtro salvo sobre uma coluna que saiu da tabela: rows() engole a
        // QueryException, mostra vazio e apaga as preferencias do usuario.
        CrudConfig::where('model', WhyEmptyOrder::class)->update(['config' => json_encode([
            'crud' => WhyEmptyOrder::class,
            'cols' => [['colsNomeFisico' => 'legacy_flag', 'colsNomeLogico' => 'Legacy', 'colsTipo' => 'text']],
            'permissions' => [],
        ])]);
        $this->savePrefs(['search' => 'abc']);

        $report = WhyEmptyInspector::inspect(WhyEmptyOrder::class);

        $this->assertNotNull($report['error']);
        $this->assertStringContainsString('the listing query fails', (string) $report['error']);
    }

    #[Test]
    public function a_full_screen_reports_its_rows_and_the_sql(): void
    {
        $report = WhyEmptyInspector::inspect(WhyEmptyOrder::class);

        $this->assertSame(4, $report['final']);
        $this->assertNull($report['error']);
        $this->assertStringContainsString('why_orders', (string) $report['sql']);
    }

    #[Test]
    public function the_command_marks_the_layer_that_emptied_it(): void
    {
        $this->savePrefs(['lastUsed' => ['status' => 'cancelled']]);

        $this->artisan('ptah:why-empty', ['model' => WhyEmptyOrder::class, '--as' => $this->user->id])
            ->expectsOutputToContain('column filters')
            ->expectsOutputToContain('rows on screen: 0')
            ->assertExitCode(0);
    }
}
