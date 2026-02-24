<?php

declare(strict_types=1);

namespace Ptah\Base;

use Illuminate\Http\Request;

/**
 * DTO (Data Transfer Object) base abstrato.
 *
 * Todas as classes DTO devem estender esta classe e implementar
 * os métodos de conversão de/para array e Request.
 */
abstract class BaseDTO
{
    /**
     * Cria uma instância do DTO a partir de um array de dados.
     *
     * @param array<string, mixed> $data
     */
    abstract public static function fromArray(array $data): static;

    /**
     * Cria uma instância do DTO a partir de um objeto Request do Laravel.
     */
    abstract public static function fromRequest(Request $request): static;

    /**
     * Converte o DTO para um array associativo.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(): array;
}
