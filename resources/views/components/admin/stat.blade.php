@props(['label', 'value', 'note' => null, 'noteClass' => 'text-brand-600', 'valueClass' => ''])

<div class="card p-6">
    <p class="text-[15px] text-slate-500">{{ $label }}</p>
    <p class="mt-2 text-3xl font-bold tracking-tight sm:text-4xl {{ $valueClass }}">{{ $value }}</p>
    @if ($note)
        <p class="mt-2 text-sm {{ $noteClass }}">{{ $note }}</p>
    @endif
</div>
