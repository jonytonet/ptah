<?php

declare(strict_types=1);

namespace Ptah\Support\AI;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who is asking the AI tool: the signed-in user and the active company.
 */
final class AiToolContext
{
    public function __construct(
        public readonly ?Authenticatable $user,
        public readonly int $companyId,
    ) {}

    public static function current(): self
    {
        // O registry tambem roda sem a aplicacao inteira (testes unitarios,
        // console sem sessao): sem auth ou sessao, ninguem e empresa nenhuma.
        $app = app();
        $user = $app->bound('auth') ? $app['auth']->user() : null;
        $companyId = $app->bound('session') && function_exists('ptah_company_id') ? ptah_company_id() : 0;

        return new self($user instanceof Authenticatable ? $user : null, $companyId);
    }

    public function userId(): int|string|null
    {
        return $this->user?->getAuthIdentifier();
    }
}
