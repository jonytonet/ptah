<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Lang;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\FileLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ptah\Tests\TestCase;

/**
 * 1.54.0: ptah ships Laravel's own lines (validation, auth, passwords,
 * pagination) in pt_BR, below the project's files.
 */
class LaravelTranslationsTest extends TestCase
{
    private const GROUPS = ['validation', 'auth', 'passwords', 'pagination'];

    /** @return iterable<string, array{string}> */
    public static function langGroups(): iterable
    {
        foreach (self::GROUPS as $group) {
            yield $group => [$group];
        }
    }

    private static function frameworkEn(string $group): array
    {
        $dir = dirname((new \ReflectionClass(FileLoader::class))->getFileName()).'/lang/en';

        return require "{$dir}/{$group}.php";
    }

    private static function ptahPtBr(string $group): array
    {
        return require dirname(__DIR__, 3)."/resources/laravel-lang/pt_BR/{$group}.php";
    }

    #[Test]
    #[DataProvider('langGroups')]
    public function every_line_of_the_installed_framework_is_translated_with_the_same_placeholders(string $group): void
    {
        $en = Arr::dot(Arr::except(self::frameworkEn($group), ['custom', 'attributes']));
        $pt = Arr::dot(Arr::except(self::ptahPtBr($group), ['custom', 'attributes']));

        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($pt))), "pt_BR/{$group}.php nao traduz estas chaves do Laravel instalado.");

        foreach ($en as $key => $line) {
            preg_match_all('/:[a-z_]+/', (string) $line, $want);
            preg_match_all('/:[a-z_]+/', (string) $pt[$key], $got);
            $want = array_unique($want[0]);
            $got = array_unique($got[0]);
            sort($want);
            sort($got);
            $this->assertSame($want, $got, "{$group}.{$key}: os placeholders mudaram na traducao.");
        }
    }

    #[Test]
    public function validation_speaks_portuguese_under_pt_br(): void
    {
        app()->setLocale('pt_BR');

        $errors = Validator::make(['nome' => '', 'email' => 'x'], ['nome' => 'required', 'email' => 'email'], [], ['nome' => 'Nome', 'email' => 'E-mail'])->errors();

        $this->assertSame('O campo Nome é obrigatório.', $errors->first('nome'));
        $this->assertSame('O campo E-mail deve ser um endereço de e-mail válido.', $errors->first('email'));
        $this->assertSame('Essas credenciais não correspondem aos nossos registros.', __('auth.failed'));
    }

    #[Test]
    public function english_stays_english(): void
    {
        app()->setLocale('en');

        $this->assertSame('The nome field is required.', Validator::make(['nome' => ''], ['nome' => 'required'])->errors()->first('nome'));
    }

    #[Test]
    public function the_projects_own_translation_wins_key_by_key(): void
    {
        $file = lang_path('pt_BR/validation.php');
        @mkdir(dirname($file), 0755, true);
        file_put_contents($file, "<?php return ['required' => 'Preencha :attribute.'];");

        try {
            app()->setLocale('pt_BR');
            $errors = Validator::make(['nome' => '', 'email' => 'x'], ['nome' => 'required', 'email' => 'email'], [], ['nome' => 'Nome', 'email' => 'E-mail'])->errors();

            $this->assertSame('Preencha Nome.', $errors->first('nome'), 'A linha do projeto vence.');
            $this->assertSame('O campo E-mail deve ser um endereço de e-mail válido.', $errors->first('email'), 'O que o projeto nao define vem do ptah.');
        } finally {
            @unlink($file);
            @rmdir(dirname($file));
        }
    }

    #[Test]
    public function it_can_be_turned_off(): void
    {
        config(['ptah.laravel_translations' => false]);
        foreach (['translation.loader', 'translator', 'validator'] as $abstract) {
            $this->app->forgetInstance($abstract);
        }
        app()->setLocale('pt_BR');

        $this->assertSame('The nome field is required.', Validator::make(['nome' => ''], ['nome' => 'required'])->errors()->first('nome'));
    }
}
