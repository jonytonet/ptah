<?php

declare(strict_types=1);

namespace Ptah\Generators\Contracts;

use Ptah\Support\EntityContext;
use Ptah\Generators\GeneratorResult;

/**
 * Contrato para todos os geradores de artefatos do Ptah.
 *
 * Cada implementação é responsável por um único artefato (SRP).
 * Novos geradores podem ser adicionados sem modificar o comando (OCP).
 */
interface GeneratorInterface
{
    /**
     * Executa a geração do artefato.
     */
    public function generate(EntityContext $context): GeneratorResult;

    /**
     * Indica se o gerador deve ser executado dado o contexto atual.
     * Permite pular geradores de view quando --api está ativo, por exemplo.
     */
    public function shouldRun(EntityContext $context): bool;
}
