{{-- Labelled dropdown for forms. $options is a list of values (shown as-is) or [value => label]. --}}
@props(['name', 'label', 'options' => [], 'selected' => null, 'placeholder' => '—', 'icon' => null])

@php
    $options = array_is_list($options) ? array_combine($options, $options) : $options;
    $error = $errors->first($name);
@endphp

<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
        @endif
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes->class(['field w-full cursor-pointer appearance-none pr-8', 'pl-8' => $icon, 'border-red-400 bg-red-50/40' => $error]) }}>
            @if ($placeholder !== false)
                <option value="">{{ $placeholder }}</option>
            @endif
            @foreach ($options as $value => $text)
                <option value="{{ $value }}" @selected((string) $selected === (string) $value)>{{ $text }}</option>
            @endforeach
        </select>
        <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
    </div>
    @if ($error)
        <p class="mt-1 flex items-center gap-1 text-xs text-red-600"><x-icon name="triangle-alert" class="size-3.5" /> {{ $error }}</p>
    @endif
</div>
