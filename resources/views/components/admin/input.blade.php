{{-- Labelled input with optional icon, hint and error. The slot sits inside the box (e.g. a show/hide button). --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'icon' => null, 'hint' => null, 'optional' => false])

@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
@endphp

<div>
    <label for="{{ $id }}" class="mb-1 flex items-baseline justify-between gap-2 text-sm font-medium text-ink-950">
        {{ $label }}
        @if ($optional)
            <span class="text-xs font-normal text-slate-400">Optional</span>
        @endif
    </label>

    <div class="relative">
        @if ($icon)
            <x-icon :name="$icon" :class="'pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 '.($error ? 'text-red-500' : 'text-slate-400')" />
        @endif

        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if ($value !== null) value="{{ $value }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except('id')->class([
                'field w-full transition',
                'pl-8' => $icon,
                'pr-9' => $slot->isNotEmpty(),
                'border-red-400 bg-red-50/40 focus:border-red-500 focus:ring-red-100' => $error,
            ]) }}
        >

        {{ $slot }}
    </div>

    @if ($error)
        <p id="{{ $id }}-error" class="mt-1 flex items-center gap-1 text-xs text-red-600">
            <x-icon name="triangle-alert" class="size-3.5" />
            {{ $error }}
        </p>
    @elseif ($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
