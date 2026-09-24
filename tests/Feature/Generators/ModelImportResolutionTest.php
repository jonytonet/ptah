<?php

declare(strict_types=1);

namespace Ptah\Tests\Feature\Generators;

use PHPUnit\Framework\Attributes\Test;
use Ptah\Generators\ModelGenerator;
use Ptah\Support\ModelLocator;

/**
 * The generator resolves a foreign key's import when there is exactly one
 * answer, and says why when there is not.
 *
 * Every `belongsTo(Category::class)` used to come with
 * `// TODO: use App\Models\Category;`, and the package's own skill made fixing
 * those TODOs a MANDATORY step after every `ptah:forge`: open each generated
 * model, find where each related class lives, write the import. For an agent
 * that is a read and an edit per foreign key, for every entity of a module —
 * token spent on something the generator could find by itself.
 *
 * The TODO was deliberate, and the reason still holds: an import guessed from
 * a convention looks right and fails at runtime. So the rule stays "never
 * guess" — it is only the non-guesses that are now answered:
 *
 *   one class of that name   → the import, read from the file's namespace
 *   in this model's namespace→ no import at all
 *   none yet                 → TODO, saying it has not been generated
 *   more than one            → TODO, listing the candidates
 */
class ModelImportResolutionTest extends GeneratorTestCase
{
    /**
     * Put a model file where the generator will look, declaring `$namespace`.
     */
    private function plant(string $relativeDir, string $namespace, string $class = 'Category'): void
    {
        $dir = $this->tmpPath.'/app/Models'.($relativeDir !== '' ? '/'.$relativeDir : '');
        $this->files->ensureDirectoryExists($dir);

        file_put_contents($dir."/{$class}.php", "<?php\n\nnamespace {$namespace};\n\nclass {$class} {}\n");
    }

    private function generate(): string
    {
        $result = (new ModelGenerator($this->files))->generate($this->context());

        $this->assertTrue($result->isDone(), $result->message ?? '');

        return (string) file_get_contents($result->path);
    }

    #[Test]
    public function a_single_match_in_another_folder_becomes_the_import(): void
    {
        $this->plant('Catalog', 'App\\Models\\Catalog');

        $content = $this->generate();

        $this->assertStringContainsString('use App\\Models\\Catalog\\Category;', $content);
        $this->assertStringNotContainsString('TODO', $content, 'Com uma resposta unica, nao pode sobrar TODO.');
    }

    #[Test]
    public function a_match_in_the_same_namespace_needs_no_import(): void
    {
        // O Widget gerado vive em App\Models: o PHP resolve Category sozinho.
        $this->plant('', 'App\\Models');

        $content = $this->generate();

        $this->assertStringNotContainsString('use App\\Models\\Category;', $content);
        $this->assertStringNotContainsString('TODO', $content);
        $this->assertStringContainsString("belongsTo(Category::class, 'category_id')", $content);
    }

    #[Test]
    public function the_namespace_is_read_from_the_file_not_guessed_from_its_path(): void
    {
        // Host que nao segue PSR-4 a risca: o arquivo esta em Legacy/, a classe
        // em outro namespace. Deduzir pelo caminho daria o import plausivel e
        // errado que o TODO existia para impedir.
        $this->plant('Legacy', 'App\\Domain\\Stock');

        $content = $this->generate();

        $this->assertStringContainsString('use App\\Domain\\Stock\\Category;', $content);
        $this->assertStringNotContainsString('App\\Models\\Legacy\\Category', $content);
    }

    #[Test]
    public function two_candidates_keep_the_todo_and_name_both(): void
    {
        $this->plant('Catalog', 'App\\Models\\Catalog');
        $this->plant('Blog', 'App\\Models\\Blog');

        $content = $this->generate();

        $this->assertStringContainsString('// TODO: use', $content, 'Ambiguo: o gerador nao pode escolher.');
        $this->assertStringContainsString('App\\Models\\Blog\\Category', $content);
        $this->assertStringContainsString('App\\Models\\Catalog\\Category', $content);
        $this->assertStringNotContainsString("\nuse App\\Models\\Blog\\Category;", $content);
        $this->assertStringNotContainsString("\nuse App\\Models\\Catalog\\Category;", $content);
    }

    #[Test]
    public function a_model_not_generated_yet_keeps_the_todo_and_says_so(): void
    {
        $content = $this->generate();

        $this->assertStringContainsString('// TODO: use App\\Models\\Category;', $content);
        $this->assertStringContainsString('does not exist in app/Models yet', $content);
    }

    #[Test]
    public function the_report_classifies_every_foreign_key(): void
    {
        $this->plant('Catalog', 'App\\Models\\Catalog');

        $imports = $this->context(fields: [
            $this->field('name', 'string'),
            $this->field('category_id', 'unsignedBigInteger'),
            $this->field('supplier_id', 'unsignedBigInteger'),
            $this->field('company_id', 'unsignedBigInteger'),
        ])->relationshipImports();

        $byField = array_column($imports, 'status', 'field');

        $this->assertSame([
            'category_id' => 'resolved',
            'supplier_id' => 'missing',
            'company_id' => 'package',
        ], $byField);
    }

    #[Test]
    public function the_locator_ignores_a_file_that_declares_no_namespace(): void
    {
        // Sem namespace nao ha como montar o import — nao e candidato.
        $dir = $this->tmpPath.'/app/Models/Scratch';
        $this->files->ensureDirectoryExists($dir);
        file_put_contents($dir.'/Category.php', "<?php\n\nclass Category {}\n");

        $this->assertSame([], ModelLocator::find('Category', $this->tmpPath.'/app/Models'));
    }
}
