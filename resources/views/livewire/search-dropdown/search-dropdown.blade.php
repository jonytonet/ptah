<div wire:ignore.self>

    <div wire:key="sd-{{ $key }}">
        <div
            class="relative"
            x-data="{
                show: false,
                results: [],
                term: '',
                initWithData: {{ $initWithData ? 'true' : 'false' }},

                async doSearch(t) {
                    this.results = await $wire.search(t || null);
                    this.show = this.results.length > 0;
                },

                async onFocus() {
                    if (this.results.length > 0) {
                        this.show = true;
                        return;
                    }
                    if (this.initWithData) {
                        await this.doSearch(this.term || null);
                    }
                },

                select(item) {
                    this.term = String(item._value) + ' - ' + item._label;
                    this.show = false;
                    $wire.selectedItem(item._raw);
                },

                clear() {
                    this.term = '';
                    this.results = [];
                    this.show = false;
                    $wire.clearData();
                }
            }"
            x-on:ptah-sd-change-show-{{ $key }}.window="show = !show"
            x-on:ptah-sd-clear-{{ $key }}.window="term = ''; results = []; show = false;"
        >

            <div class="relative flex items-center">
                <input
                    x-model="term"
                    x-on:input.debounce.500ms="doSearch($event.target.value)"
                    x-on:focus="onFocus()"
                    x-on:keydown.escape="show = false"
                    x-on:blur.debounce.150ms="show = false"
                    wire:key="sd-input-{{ $key }}"
                    class="block w-full rounded border border-gray-300 bg-white px-3 py-1.5 pr-9 text-sm text-slate-800 placeholder-slate-400 outline-none transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-100 dark:bg-slate-700 dark:border-slate-600 dark:text-slate-200 dark:placeholder-slate-500 dark:focus:border-blue-400"
                    placeholder="{{ $placeholder }}"
                    autocomplete="off"
                />
                <button
                    type="button"
                    class="absolute right-2.5 text-gray-400 hover:text-gray-600 transition-colors"
                    style="cursor: pointer;"
                    x-on:click="clear()"
                    title="{{ __('ptah::ui.btn_clear') }}"
                >
                    <em class="bx bx-x fs-5"></em>
                </button>
            </div>

            <div
                x-show="show && results.length > 0"
                x-cloak
                id="sd-result-{{ $key }}"
                class="absolute mt-1 w-full min-w-[200px] max-w-[600px] max-h-[400px] overflow-y-auto bg-white border border-gray-200 rounded-md"
                style="z-index: 999; {{ $startList }}: 100%;"
            >
                <template x-for="(item, i) in results" :key="String(item._value) + '-' + i">
                    <span
                        x-on:mousedown.prevent="select(item)"
                        class="flex w-full px-3 py-2 text-left text-xs cursor-pointer hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-b-0"
                        role="button"
                    >
                        <strong x-text="item._value"></strong>
                        <span x-text="' - ' + item._label"></span>
                        <template x-if="item._labelTwo !== null">
                            <span x-text="' - ' + item._labelTwo"></span>
                        </template>
                        <template x-if="item._labelThree !== null">
                            <span x-text="' - ' + item._labelThree"></span>
                        </template>
                    </span>
                </template>
            </div>

        </div>
    </div>

</div>
