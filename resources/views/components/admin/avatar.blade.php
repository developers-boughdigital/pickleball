@props(['initials', 'tone' => 'gray'])

<span {{ $attributes->class([
    'grid size-11 shrink-0 place-items-center rounded-full text-sm font-semibold',
    'bg-brand-500 text-ink-950' => $tone === 'solid',
    'bg-brand-50 text-brand-500' => $tone === 'gold',
    'bg-slate-100 text-slate-700' => $tone === 'gray',
]) }}>{{ $initials }}</span>
