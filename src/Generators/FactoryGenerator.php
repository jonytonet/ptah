<?php

declare(strict_types=1);

namespace Ptah\Generators;

use Ptah\Support\EntityContext;

/**
 * Generates a model factory and a demo seeder (opt-in: `ptah:forge --factory`).
 *
 * The generated model already `use HasFactory`, and Laravel resolves
 * App\Models\Catalog\Product to Database\Factories\Catalog\ProductFactory —
 * so the factory goes exactly there, and `Product::factory()` works with no
 * `newFactory()` override. Values come from the field types (and names:
 * email, phone, cpf…), inside the declared length and enum; a foreign key
 * takes an existing row of the related model, or makes one.
 *
 * Stubs: factory.stub, seeder.stub
 */
class FactoryGenerator extends AbstractGenerator
{
    public function shouldRun(EntityContext $context): bool
    {
        return $context->withFactory;
    }

    public function generate(EntityContext $context): GeneratorResult
    {
        return $this->writeFile(
            path: $context->subPath(database_path('factories'))."/{$context->entity}Factory.php",
            stub: 'factory',
            replacements: [
                'namespace' => $context->subNs('Database\Factories'),
                'model_fqcn' => $context->modelFqn,
                'entity' => $context->entity,
                'definition' => $context->factoryDefinition(),
            ],
            force: $context->force,
            labelOverride: "Factory [{$context->entity}Factory]",
        );
    }

    public function generateSeeder(EntityContext $context): GeneratorResult
    {
        return $this->writeFile(
            path: $context->subPath(database_path('seeders'))."/{$context->entity}Seeder.php",
            stub: 'seeder',
            replacements: [
                'namespace' => $context->subNs('Database\Seeders'),
                'model_fqcn' => $context->modelFqn,
                'entity' => $context->entity,
            ],
            force: $context->force,
            labelOverride: "Seeder [{$context->entity}Seeder]",
        );
    }

    protected function label(): string
    {
        return 'Factory';
    }
}
