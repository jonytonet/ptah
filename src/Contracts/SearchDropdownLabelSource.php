<?php

declare(strict_types=1);

namespace Ptah\Contracts;

/**
 * A searchdropdown service (`colsSDTipo: service`) that can turn a stored
 * value back into its label (1.48.0).
 *
 * Editing a record opens the field with the label of the value already
 * saved. Model mode finds it through the relation; service mode has no
 * relation — the value points outside (an ERP branch) — and opened EMPTY,
 * so saving without noticing looked like clearing the field. A service that
 * implements this answers directly; one that does not is asked through
 * SearchDropdownSource::searchDropdown() with the value as the term (exact
 * `value` match only).
 */
interface SearchDropdownLabelSource
{
    /** The label for a stored value, or null when the value is unknown. */
    public function searchDropdownLabel(mixed $value): ?string;
}
