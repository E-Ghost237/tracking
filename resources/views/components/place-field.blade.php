@props(['field', 'label', 'placeholder' => null, 'initial' => ''])
<div x-data="placeField" data-field="{{ $field }}" data-initial="{{ $initial }}" class="relative" @keydown.escape="open = false">
    <label for="place-{{ $field }}" class="field-label">{{ $label }}</label>
    <div class="relative">
        <x-lucide name="map-pin" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-500" />
        <input id="place-{{ $field }}" type="text" x-model="query" @input="search()" @focus="search()" @blur="close()"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="choose()"
               role="combobox" :aria-expanded="open" aria-autocomplete="list" aria-controls="place-{{ $field }}-list" autocomplete="off"
               class="field !pl-10" placeholder="{{ $placeholder ?? __('Start typing a city') }}">
        <span x-show="loading" x-cloak class="absolute top-1/2 right-3 -translate-y-1/2"><x-lucide name="loader" class="size-4 animate-spin text-slate-500" /></span>
    </div>
    <ul id="place-{{ $field }}-list" x-show="open && results.length" x-cloak role="listbox"
        x-transition.opacity.duration.150ms
        class="absolute z-30 mt-2 max-h-72 w-full overflow-auto rounded-[6px] border border-line bg-white p-1.5 shadow-[var(--shadow-lift)]">
        <template x-for="(place, index) in results" :key="index">
            <li role="option" :aria-selected="index === active" @mousedown.prevent="choose(index)" @mouseenter="active = index"
                class="flex cursor-pointer items-center gap-3 rounded-[4px] px-3 py-2.5 text-sm" :class="index === active ? 'bg-surface' : ''">
                <span class="grid size-8 place-items-center rounded-[4px] bg-brand-50 text-brand-600"><x-lucide name="map-pin" class="size-4" /></span>
                <span class="flex-1 font-medium text-ink-900" x-text="place.label"></span>
                <span class="font-mono text-xs text-slate-500" x-text="place.country"></span>
            </li>
        </template>
    </ul>
    <p x-show="open && !loading && query.length > 1 && results.length === 0" x-cloak class="mt-2 text-xs text-slate-500">{{ __('No matching city. Try another spelling.') }}</p>
</div>
