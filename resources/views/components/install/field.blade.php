{{-- Text input bound to the installer's form.{name}, with its server-side error underneath. --}}
@props(['name', 'label', 'type' => 'text', 'hint' => null])

<div>
    <label for="{{ $name }}" class="block text-base font-medium text-ink-950">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        x-model="form.{{ $name }}"
        :aria-invalid="Boolean(error('{{ $name }}')).toString()"
        {{ $attributes->class('mt-2 block h-12 w-full rounded-lg border border-slate-300 bg-white px-4 text-base text-ink-950 placeholder:text-slate-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 focus:outline-none aria-invalid:border-red-500') }}
    >
    @if ($hint)
        <p class="mt-1.5 text-sm text-slate-500">{{ $hint }}</p>
    @endif
    <p x-show="error('{{ $name }}')" x-text="error('{{ $name }}')" x-cloak class="mt-1.5 text-sm text-red-600"></p>
</div>
