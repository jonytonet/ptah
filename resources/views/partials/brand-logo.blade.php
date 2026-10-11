{{-- O logo do host, com a variante escura opcional (ptah.brand.logo / logo_dark).
     A escura comeca escondida pelo style: sem a regra do ptah-components.css
     (CSS do host ainda nao recompilado) aparece so a clara, nunca as duas.
     Espera: $logoSrc, $logoDarkSrc (ou null), $logoAlt, $logoClass. --}}
<img src="{{ $logoSrc }}" alt="{{ $logoAlt }}" class="{{ $logoClass }} ptah-brand-logo {{ $logoDarkSrc ? 'ptah-brand-logo-light' : '' }}" />
@if ($logoDarkSrc)
    <img src="{{ $logoDarkSrc }}" alt="{{ $logoAlt }}" class="{{ $logoClass }} ptah-brand-logo ptah-brand-logo-dark" style="display:none" />
@endif
