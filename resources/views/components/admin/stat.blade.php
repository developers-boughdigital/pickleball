@props(['label', 'value', 'note' => null, 'noteClass' => 'text-brand-600', 'valueClass' => ''])

<div class="card p-4">
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-bold tracking-tight {{ $valueClass }}">{{ $value }}</p>
    @if ($note)
        <p class="mt-0.5 text-xs {{ $noteClass }}">{{ $note }}</p>
    @endif
</div>
