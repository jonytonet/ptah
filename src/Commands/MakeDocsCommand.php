<?php

declare(strict_types=1);

namespace Ptah\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Comando para gerar documentação Swagger/OpenAPI para uma entidade.
 *
 * Uso: php artisan ptah:docs {Entity}
 *
 * Requer o pacote darkaonline/l5-swagger para geração completa.
 */
class MakeDocsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ptah:docs
                            {entity? : Nome da entidade para documentar (opcional, documenta todas se omitido)}
                            {--force : Sobrescrever arquivos existentes}';

    /**
     * @var string
     */
    protected $description = 'Gera anotações Swagger/OpenAPI para uma entidade.';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Executa o comando.
     */
    public function handle(): int
    {
        $entity = $this->argument('entity');

        if ($entity) {
            $entity = Str::studly($entity);
            $this->generateDocs($entity);
        } else {
            $this->components->warn('Nenhuma entidade especificada. Especifique uma entidade com: ptah:docs {Entity}');
        }

        return self::SUCCESS;
    }

    /**
     * Gera as anotações Swagger para a entidade.
     */
    protected function generateDocs(string $entity): void
    {
        $entityLower = Str::snake($entity);
        $entityPlural = Str::plural($entityLower);

        $controllerPath = config('ptah.paths.controllers') . "/{$entity}Controller.php";

        if (! $this->files->exists($controllerPath)) {
            $this->components->error("Controller [{$entity}Controller] não encontrado. Execute ptah:make {$entity} primeiro.");
            return;
        }

        $annotations = $this->buildSwaggerAnnotations($entity, $entityLower, $entityPlural);

        // Insere as anotações no início do controller, após o declare(strict_types=1)
        $controllerContent = $this->files->get($controllerPath);

        if (str_contains($controllerContent, '@OA\\')) {
            if (! $this->option('force')) {
                $this->components->twoColumnDetail("Docs [{$entity}Controller]", '<fg=yellow;options=bold>SKIPPED</>');
                return;
            }
        }

        $this->components->twoColumnDetail("Docs [{$entity}Controller]", '<fg=green;options=bold>DONE</>');
        $this->newLine();
        $this->components->info('Anotações Swagger geradas:');
        $this->line($annotations);
        $this->newLine();
        $this->components->warn('Adicione as anotações acima à classe do controller manualmente.');

        if (! class_exists('L5Swagger\\L5SwaggerServiceProvider')) {
            $this->newLine();
            $this->components->warn('Para geração completa de docs, instale: composer require darkaonline/l5-swagger');
        }
    }

    /**
     * Constrói as anotações Swagger para a entidade.
     */
    protected function buildSwaggerAnnotations(
        string $entity,
        string $entityLower,
        string $entityPlural
    ): string {
        return <<<ANNOTATIONS
/**
 * @OA\\Tag(name="{$entity}", description="Operações de {$entity}")
 *
 * @OA\\Get(
 *     path="/api/{$entityPlural}",
 *     tags={"{$entity}"},
 *     summary="Lista todos os {$entityLower}s",
 *     @OA\\Response(response=200, description="Lista paginada de {$entityLower}s")
 * )
 *
 * @OA\\Post(
 *     path="/api/{$entityPlural}",
 *     tags={"{$entity}"},
 *     summary="Cria um novo {$entityLower}",
 *     @OA\\Response(response=201, description="{$entity} criado"),
 *     @OA\\Response(response=422, description="Erro de validação")
 * )
 *
 * @OA\\Get(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Retorna um {$entityLower} específico",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=200, description="{$entity} encontrado"),
 *     @OA\\Response(response=404, description="{$entity} não encontrado")
 * )
 *
 * @OA\\Put(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Atualiza um {$entityLower}",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=200, description="{$entity} atualizado"),
 *     @OA\\Response(response=404, description="{$entity} não encontrado")
 * )
 *
 * @OA\\Delete(
 *     path="/api/{$entityPlural}/{id}",
 *     tags={"{$entity}"},
 *     summary="Remove um {$entityLower}",
 *     @OA\\Parameter(name="id", in="path", required=true, @OA\\Schema(type="integer")),
 *     @OA\\Response(response=204, description="{$entity} removido"),
 *     @OA\\Response(response=404, description="{$entity} não encontrado")
 * )
 */
ANNOTATIONS;
    }
}
