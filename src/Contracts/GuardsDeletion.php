<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * Optional contract for a model that can refuse to be deleted.
 *
 * BaseCrud asks BEFORE opening the confirmation — the user learns why at once
 * instead of confirming and being refused — and again when deleting, single
 * or bulk (the blocked rows are skipped and counted). A brand in use by active
 * products, a stock line that the next invoice entry depends on:
 *
 *   class Brand extends Model implements GuardsDeletion
 *   {
 *       public function deletionBlockedReason(): ?string
 *       {
 *           return $this->products()->exists()
 *               ? 'Marca em uso por produtos ativos.'
 *               : null;
 *       }
 *   }
 */
interface GuardsDeletion
{
    /** Why this record cannot be deleted now, or null when it can. */
    public function deletionBlockedReason(): ?string;
}
