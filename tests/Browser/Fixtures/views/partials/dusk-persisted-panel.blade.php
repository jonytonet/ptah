{{-- A persisted block that scrolls and has a field, like the assistant's chat:
     the same element is carried from page to page by the single-page
     navigation (SpaNavigationBrowserTest). Included by dusk-other and
     dusk-persist. --}}
@persist('dusk-persisted-panel')
    <div id="dusk-persisted-panel" style="position: fixed; right: 16px; bottom: 16px; width: 220px; z-index: 30; background: #fff; border: 1px solid #ccc;">
        <div id="dusk-persisted-list" style="height: 120px; overflow-y: auto;">
            @for ($i = 1; $i <= 40; $i++)
                <p style="margin: 0; padding: 2px 6px;">Mensagem {{ $i }}</p>
            @endfor
        </div>
        <textarea id="dusk-persisted-draft" rows="2" style="width: 100%;"></textarea>
    </div>
@endpersist
