<?php

declare(strict_types=1);

namespace Ptah\Generators\Contracts;

use Ptah\Generators\GeneratorResult;
use Ptah\Support\EntityContext;

/**
 * Contract for all Ptah artefact generators.
 *
 * Each implementation is responsible for a single artefact (SRP).
 * New generators can be added without modifying the command (OCP).
 */
interface GeneratorInterface
{
    /**
     * Runs the artefact generation.
     */
    public function generate(EntityContext $context): GeneratorResult;

    /**
     * Indicates whether the generator should run for the given context.
     * Allows skipping view generators when --api is active, for example.
     */
    public function shouldRun(EntityContext $context): bool;
}
