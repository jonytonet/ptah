<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Crud;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Livewire\BaseCrud\BaseCrud;
use Ptah\Models\CrudConfig;
use Ptah\Tests\TestCase;

class OracleAccount extends Model
{
    protected $table = 'oracle_accounts';

    protected $fillable = ['name', 'role', 'password', 'two_factor_secret', 'internal_code'];

    // O que a aplicacao declarou que nunca sai do servidor.
    protected $hidden = ['password', 'two_factor_secret'];
}

/**
 * A filter on a column no config mentioned read its value out of the database.
 *
 * `$filters` is public and client-writable, and `buildActiveFilters()`
 * accepted ANY key in it — the only barrier was `deniedColumns`. On a screen
 * over the users table, a forged
 *
 *     filters[password] = '$2y$10$a'
 *
 * filtered the listing by the password hash, and whether a row survived said
 * whether the hash started that way. One character at a time, the whole hash;
 * the same for `remember_token` and `two_factor_secret` — and with the TOTP
 * secret, the second factor protects nothing. Reproduced before the fix: two
 * users, one prefix, one left on screen.
 *
 * It had four doors, and all four are closed here: the filter panel, the
 * IS NULL / IS NOT NULL branch (a boolean oracle — who has 2FA on), the
 * advanced search, and the global search box over a configured column.
 *
 * ── The rule ─────────────────────────────────────────────────────────────
 *
 * The client may filter by a column the config declares, or by a field the
 * HOST named in `initialFilter`. Nobody may filter, search or sort by an
 * attribute in the model's `$hidden` — the application's own statement that
 * the value never leaves the server.
 */
class CrudFilterOracleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('oracle_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('password')->nullable();
            $table->string('two_factor_secret')->nullable();
            $table->string('internal_code')->nullable();
            $table->timestamps();
        });

        OracleAccount::create(['name' => 'conta-A', 'role' => 'admin', 'password' => '$2y$10$alpha', 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'internal_code' => 'X1']);
        OracleAccount::create(['name' => 'conta-B', 'role' => 'user', 'password' => '$2y$10$beta', 'two_factor_secret' => null, 'internal_code' => 'Y2']);
    }

    /**
     * @param  list<array<string, mixed>>  $extraCols
     */
    private function configure(array $extraCols = []): void
    {
        CrudConfig::updateOrCreate(
            ['model' => OracleAccount::class, 'route' => ''],
            ['config' => [
                'crud' => OracleAccount::class,
                'cols' => array_merge([
                    ['colsNomeFisico' => 'name', 'colsNomeLogico' => 'Nome', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true, 'colsOrderBy' => 'name'],
                    ['colsNomeFisico' => 'role', 'colsNomeLogico' => 'Papel', 'colsTipo' => 'text', 'colsVisibleList' => true, 'colsGravar' => true],
                ], $extraCols),
                'permissions' => [],
            ]]
        );
    }

    private function crud(array $params = [])
    {
        return Livewire::test(BaseCrud::class, ['model' => OracleAccount::class] + $params);
    }

    private function assertBothVisible(string $html, string $why): void
    {
        $this->assertStringContainsString('conta-A', $html, $why);
        $this->assertStringContainsString('conta-B', $html, $why);
    }

    // ── The four doors ─────────────────────────────────────────────────────

    #[Test]
    public function a_filter_on_the_password_hash_does_not_narrow_the_listing(): void
    {
        $this->configure();

        $html = $this->crud()->set('filters', ['password' => '$2y$10$al'])->html();

        $this->assertBothVisible($html, 'O filtro no hash de senha reduziu a listagem — o oraculo esta aberto.');
    }

    #[Test]
    public function a_column_outside_the_config_is_not_filterable_either(): void
    {
        // Mesmo sem $hidden: a config e o que o host decidiu mostrar.
        $this->configure();

        $html = $this->crud()->set('filters', ['internal_code' => 'X1'])->html();

        $this->assertBothVisible($html, 'Filtro em coluna fora da config foi aplicado.');
    }

    #[Test]
    public function is_not_null_does_not_reveal_who_has_two_factor_on(): void
    {
        // O oraculo booleano: o ramo de IS NULL rodava sem coluna configurada.
        $this->configure();

        $html = $this->crud()->set('filterOperators', ['two_factor_secret' => 'IS NOT NULL'])->html();

        $this->assertBothVisible($html, 'IS NOT NULL no segredo do 2FA revelou quem o tem ligado.');
    }

    #[Test]
    public function the_advanced_search_is_held_to_the_same_rule(): void
    {
        $this->configure();

        $html = $this->crud()
            ->call('addAdvancedSearchField', 'password', 'LIKE', '$2y$10$al')
            ->set('advancedSearchActive', true)
            ->html();

        $this->assertBothVisible($html, 'A busca avancada foi a segunda porta para o oraculo.');
    }

    #[Test]
    public function a_hidden_attribute_configured_as_a_column_is_still_not_filterable(): void
    {
        // A senha declarada como coluna de formulario: esta na config, mas
        // continua em $hidden — e $hidden vence.
        $this->configure([
            ['colsNomeFisico' => 'password', 'colsNomeLogico' => 'Senha', 'colsTipo' => 'text', 'colsVisibleList' => false, 'colsGravar' => true],
        ]);

        $html = $this->crud()->set('filters', ['password' => '$2y$10$al'])->html();

        $this->assertBothVisible($html, 'Coluna configurada mas em $hidden foi filtrada.');
    }

    #[Test]
    public function the_global_search_box_does_not_search_a_hidden_attribute(): void
    {
        $this->configure([
            ['colsNomeFisico' => 'password', 'colsNomeLogico' => 'Senha', 'colsTipo' => 'text', 'colsVisibleList' => false, 'colsGravar' => true],
        ]);

        $html = $this->crud()->set('search', 'alpha')->html();

        // Nenhuma conta tem "alpha" num campo VISIVEL: se a A aparecer, a busca
        // encontrou o hash.
        $this->assertStringNotContainsString('conta-A', $html, 'A caixa de busca procurou dentro do hash de senha.');
    }

    #[Test]
    public function a_hidden_attribute_cannot_be_sorted_by(): void
    {
        // A ordem das linhas tambem revela o valor.
        $this->configure([
            ['colsNomeFisico' => 'password', 'colsNomeLogico' => 'Senha', 'colsTipo' => 'text', 'colsVisibleList' => false, 'colsGravar' => true, 'colsOrderBy' => 'password'],
        ]);

        $this->crud()->call('sortBy', 'password')->assertNotSet('sort', 'password');
    }

    // ── What must keep working ─────────────────────────────────────────────

    #[Test]
    public function a_configured_column_still_filters(): void
    {
        $this->configure();

        $html = $this->crud()->set('filters', ['role' => 'admin'])->html();

        $this->assertStringContainsString('conta-A', $html);
        $this->assertStringNotContainsString('conta-B', $html, 'Filtro legitimo parou de funcionar.');
    }

    #[Test]
    public function a_host_initial_filter_on_a_column_outside_the_config_still_applies(): void
    {
        // O host pode filtrar por coluna fora da config no initialFilter — e o
        // codigo dele. Exigir coluna configurada faria esse filtro parar de
        // valer em silencio.
        $this->configure();

        $html = $this->crud(['initialFilter' => [['internal_code', '=', 'X1']]])->html();

        $this->assertStringContainsString('conta-A', $html);
        $this->assertStringNotContainsString('conta-B', $html, 'O initialFilter do host deixou de valer.');
    }

    #[Test]
    public function not_even_the_host_can_open_a_hidden_attribute_to_the_client(): void
    {
        // Depois de montado, o cliente muda o VALOR de um filtro. Um filtro de
        // host em $hidden viraria o oraculo com um passo a mais.
        $this->configure();

        $html = $this->crud(['initialFilter' => [['password', 'LIKE', '$2y$10$al']]])->html();

        $this->assertBothVisible($html, 'Um initialFilter em atributo $hidden foi aplicado.');
    }
}
