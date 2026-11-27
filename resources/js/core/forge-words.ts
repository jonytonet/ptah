/**
 * The few words the forge kit (react/forge.tsx, vue/forge.ts) says on its own
 * — close, back, the select's placeholder… They were fixed in English
 * (1.63.1): now they follow the page's `<html lang>`, like the Livewire
 * forge-* components follow the app locale.
 */
export interface ForgeWords {
    close: string;
    back: string;
    select: string;
    filter: string;
    previousPage: string;
    nextPage: string;
    search: string;
    searchPlaceholder: string;
    showPassword: string;
    hidePassword: string;
}

const pt: ForgeWords = {
    close: 'Fechar',
    back: 'Voltar',
    select: 'Selecione...',
    filter: 'Filtrar opções',
    previousPage: 'Página anterior',
    nextPage: 'Próxima página',
    search: 'Buscar',
    searchPlaceholder: 'Buscar…',
    showPassword: 'Mostrar senha',
    hidePassword: 'Ocultar senha',
};

const en: ForgeWords = {
    close: 'Close',
    back: 'Back',
    select: 'Select...',
    filter: 'Filter options',
    previousPage: 'Previous page',
    nextPage: 'Next page',
    search: 'Search',
    searchPlaceholder: 'Search…',
    showPassword: 'Show password',
    hidePassword: 'Hide password',
};

export function forgeWords(locale?: string | null): ForgeWords {
    const lang = (locale ?? (typeof document !== 'undefined' ? document.documentElement.lang : '') ?? '').toLowerCase().slice(0, 2);

    return lang === 'pt' ? pt : en;
}
