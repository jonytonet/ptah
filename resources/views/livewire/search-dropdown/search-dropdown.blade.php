<div wire:ignore.self>

    <div wire:key="sd-{{ $key }}">
        <div
            class="relative"
            x-data="{
                show: false,
                results: [],
                term: '',
                activeIndex: -1,
                initWithData: {{ $initWithData ? 'true' : 'false' }},

                async doSearch(t) {
                    this.results = await $wire.search(t || null);
                    this.show = this.results.length > 0;
                    this.activeIndex = this.show ? 0 : -1;
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
                    this.activeIndex = -1;
                    $wire.selectedItem(item);
                },

                clear() {
                    this.term = '';
                    this.results = [];
                    this.show = false;
                    this.activeIndex = -1;
                    $wire.clearData();
                },

                moveActive(delta) {
                    if (!this.show || !this.results.length) return;
                    const n = this.results.length;
                    this.activeIndex = (this.activeIndex + delta + n) % n;
                },

                selectActive() {
                    if (this.show && this.activeIndex >= 0 && this.results[this.activeIndex]) {
                        this.select(this.results[this.activeIndex]);
                    }
                }
            }"
            x-on:ptah-sd-change-show-{{ $key }}.window="show = !show"
            x-on:ptah-sd-clear-{{ $key }}.window="term = ''; results = []; show = false; activeIndex = -1;"
        >

            <div class="relative flex items-center">
                <input
                    x-model="term"
                    x-on:input.debounce.500ms="doSearch($event.target.value)"
                    x-on:focus="onFocus()"
                    x-on:keydown.escape="show = false; activeIndex = -1"
                    x-on:keydown.arrow-down.prevent="moveActive(1)"
                    x-on:keydown.arrow-up.prevent="moveActive(-1)"
                    x-on:keydown.enter.prevent="selectActive()"
                    x-on:blur.debounce.150ms="show = false; activeIndex = -1"
                    wire:key="sd-input-{{ $key }}"
                    role="combobox"
                    aria-haspopup="listbox"
                    aria-autocomplete="list"
                    :aria-expanded="show"
                    aria-controls="sd-result-{{ $key }}"
                    :aria-activedescendant="activeIndex >= 0 ? ('sd-opt-' + activeIndex + '-{{ $key }}') : null"
                    class="block w-full rounded border outline-none px-3 py-1.5 pr-9 text-sm transition-colors ptah-c-form_in"
                    placeholder="{{ $placeholder }}"
                    autocomplete="off"
                />
                <button
                    type="button"
                    class="absolute right-2.5 transition-colors ptah-c-search_x"
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
                role="listbox"
                class="absolute mt-1 w-full min-w-[200px] max-w-[600px] max-h-[400px] overflow-y-auto border rounded-md ptah-c-dd"
                style="z-index: 999; {{ $startList }}: 100%;"
            >
                <template x-for="(item, i) in results" :key="String(item._value) + '-' + i">
                    <span
                        :id="'sd-opt-' + i + '-{{ $key }}'"
                        role="option"
                        :aria-selected="activeIndex === i"
                        x-on:mouseenter="activeIndex = i"
                        x-on:mousedown.prevent="select(item)"
                        :class="activeIndex === i ? 'ptah-select-active' : ''"
                        class="flex w-full px-3 py-2 text-left text-xs cursor-pointer transition-colors border-b border-slate-100 last:border-b-0 ptah-c-dd_opt"
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
