<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Commands;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class CheckShelf extends Model
{
    protected $table = 'check_shelves';

    protected $fillable = ['title'];
}

class CheckBook extends Model
{
    protected $table = 'check_books';

    protected $fillable = ['title', 'check_shelf_id'];

    public function checkShelf(): BelongsTo
    {
        return $this->belongsTo(CheckShelf::class);
    }

    public function getShoutAttribute(): string
    {
        return strtoupper((string) $this->title);
    }
}

/**
 * `ptah:check` answers "does every screen work?" without a browser.
 *
 * Each case is a screen that RENDERS without complaint and still does not do
 * what its config says — the class of bug that reaches a user because nothing
 * ever threw — plus the ones that do throw, which the command must surface as
 * one line with a place to look, not as a stack trace.
 */
class CheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('check_shelves', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->timestamps();
        });

        Schema::create('check_books', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->foreignId('check_shelf_id')->nullable();
            $t->string('isbn')->nullable();
            $t->timestamps();
        });

        CheckBook::create(['title' => 'livro-um']);
    }

    /**
     * @param  list<array<string, mixed>>  $cols
     * @param  array<string, mixed>  $extra
     */
    private function screen(string $class, array $cols, array $extra = []): void
    {
        CrudConfig::updateOrCreate(['model' => $class, 'route' => ''], ['config' => [
            'crud' => $class,
            'cols' => $cols,
            'permissions' => [],
        ] + $extra]);
    }

    private static function col(string $field, array $extra = []): array
    {
        return $extra + ['colsNomeFisico' => $field, 'colsNomeLogico' => ucfirst($field), 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true];
    }

    #[Test]
    public function a_sound_screen_passes(): void
    {
        $this->screen(CheckBook::class, [self::col('title'), self::col('shout', ['colsGravar' => false])]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('1 screens: 1 ok, 0 with warnings, 0 failing')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_column_that_is_not_in_the_table_is_reported(): void
    {
        // Renderiza sem erro — a celula fica vazia. Por isso precisa do check.
        $this->screen(CheckBook::class, [self::col('title'), self::col('ghost', ['colsGravar' => false])]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('col "ghost" is not a column of check_books')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_form_field_outside_fillable_is_reported(): void
    {
        $this->screen(CheckBook::class, [self::col('title'), self::col('isbn')]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('form field "isbn" is not fillable')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_not_null_column_the_form_never_fills_is_reported(): void
    {
        // `title` e NOT NULL e o formulario so tem `isbn`: todo "Novo" falha no banco.
        $this->screen(CheckShelf::class, [self::col('id', ['colsGravar' => false])]);
        $this->screen(CheckBook::class, [self::col('check_shelf_id')]);

        $this->artisan('ptah:check', ['model' => 'CheckBook'])
            ->expectsOutputToContain('column "title" is NOT NULL without a default and not in the form')
            ->assertExitCode(0);
    }

    #[Test]
    public function a_relation_that_does_not_exist_fails_the_screen_with_a_place_to_look(): void
    {
        $this->screen(CheckBook::class, [self::col('check_shelf_id', ['colsRelacao' => 'shelfTypo', 'colsRelacaoExibe' => 'title'])]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('relation "shelfTypo" is not a relationship on CheckBook')
            ->expectsOutputToContain('render: RelationNotFoundException')
            ->assertExitCode(1);
    }

    #[Test]
    public function a_missing_table_fails_without_trying_to_render(): void
    {
        Schema::drop('check_shelves');
        $this->screen(CheckShelf::class, [self::col('title')]);

        $this->artisan('ptah:check')
            ->expectsOutputToContain('table "check_shelves" does not exist')
            ->doesntExpectOutputToContain('render:')
            ->assertExitCode(1);
    }

    #[Test]
    public function the_model_argument_narrows_to_one_screen(): void
    {
        $this->screen(CheckBook::class, [self::col('title')]);
        $this->screen(CheckShelf::class, [self::col('title')]);

        $this->artisan('ptah:check', ['model' => 'CheckShelf'])
            ->expectsOutputToContain('1 screens:')
            ->assertExitCode(0);
    }

    #[Test]
    public function write_round_trips_and_leaves_nothing_behind(): void
    {
        CheckShelf::create(['title' => 'estante']);
        $this->screen(CheckBook::class, [self::col('title'), self::col('check_shelf_id')]);

        $this->artisan('ptah:check', ['--write' => true])
            ->expectsOutputToContain('1 ok')
            ->assertExitCode(0);

        $this->assertSame(1, CheckBook::count(), 'O --write deixou registro para tras — o rollback falhou.');
    }

    #[Test]
    public function write_surfaces_what_the_database_refuses(): void
    {
        // O formulario grava so `isbn`; `title` NOT NULL faz o INSERT falhar.
        $this->screen(CheckBook::class, [self::col('isbn')]);
        CheckBook::unguard();

        try {
            $this->artisan('ptah:check', ['--write' => true])
                ->expectsOutputToContain('write failed on create')
                ->assertExitCode(1);
        } finally {
            CheckBook::reguard();
        }

        $this->assertSame(1, CheckBook::count());
    }

    #[Test]
    public function write_is_refused_in_production_without_force(): void
    {
        $this->app['env'] = 'production';
        $this->screen(CheckBook::class, [self::col('title')]);

        $this->artisan('ptah:check', ['--write' => true])
            ->expectsOutputToContain('--write is refused in production')
            ->assertExitCode(1);
    }

    #[Test]
    public function json_output_carries_status_and_findings(): void
    {
        $this->screen(CheckBook::class, [self::col('title'), self::col('ghost', ['colsGravar' => false])]);

        $this->artisan('ptah:check', ['--json' => true])
            ->expectsOutputToContain('"status":"warning"')
            ->assertExitCode(0);
    }
}
