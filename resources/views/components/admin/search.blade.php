{{-- Search box that filters as you type. --}}
@props(['name' => 'search', 'value' => null, 'placeholder' => 'Search'])

<label {{ $attributes->class('relative block') }}>
    <span class="sr-only">{{ $placeholder }}</span>
    <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3.5 -translate-y-1/2 text-slate-500" />
    <input
        type="search"
        name="{{ $name }}"
        value="{{ $value }}"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        @input.debounce.400ms="$el.form.requestSubmit()"
        class="field w-full pl-11"
    >
</label>
