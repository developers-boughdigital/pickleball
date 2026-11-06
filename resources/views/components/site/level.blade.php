{{-- A playing level in its colour from the design: New, Beginner … Open. --}}
@props(['level'])

@php
    $colour = [
        'new' => 'bg-level-new',
        'beginner' => 'bg-level-beginner',
        'intermediate' => 'bg-level-intermediate',
        'advanced' => 'bg-level-advanced',
        'open' => 'bg-level-open',
    ][$level] ?? null;
@endphp

<span {{ $attributes->class(['site-tag text-white', $colour ?? 'border-2 border-line-strong bg-white text-navy']) }}>{{ \App\Models\Club::LEVELS[$level] ?? $level }}</span>
