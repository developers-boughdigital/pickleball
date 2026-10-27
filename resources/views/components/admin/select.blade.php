{{-- Filter select that submits its form on change. $options is [value => label]; omit $placeholder for no "all" option. --}}
@props(['name', 'options' => [], 'selected' => null, 'placeholder' => null])

<div {{ $attributes->class('relative') }}>
    <select name="{{ $name }}" @change="$el.form.requestSubmit()" class="field w-full cursor-pointer appearance-none pr-8">
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $label)
            <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $label }}</option>
        @endforeach
    </select>
    <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
</div>
