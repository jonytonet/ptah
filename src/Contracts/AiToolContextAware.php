<?php

declare(strict_types=1);

namespace Ptah\Contracts;

use Ptah\Support\AI\AiToolContext;

/**
 * Optional companion to AiToolInterface: receive who is asking before running.
 *
 * `execute(array $arguments)` cannot grow a parameter without breaking every
 * tool already written, so the context arrives through this method, called by
 * the registry right before each `execute()`. With it a tool scopes its query
 * to the active company instead of summing every branch, and does not reach
 * for `session()` itself.
 */
interface AiToolContextAware
{
    public function withContext(AiToolContext $context): void;
}
