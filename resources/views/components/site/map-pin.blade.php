{{-- The map pin from resources/js/map.js, for legends. Green when a club plays there. --}}
@props(['club' => false])

@php($fill = $club ? '#5dc293' : '#1f4fa3')
<svg viewBox="0 0 34 44" aria-hidden="true" {{ $attributes->class('w-auto shrink-0') }}>
    <path d="M17 1.5C8.4 1.5 1.5 8.3 1.5 16.8c0 11.2 13.3 23 14.4 24a1.6 1.6 0 0 0 2.2 0c1.1-1 14.4-12.8 14.4-24C32.5 8.3 25.6 1.5 17 1.5Z" fill="{{ $fill }}" stroke="#fff" stroke-width="2.5"/>
    <circle cx="17" cy="16.5" r="7.5" fill="{{ $club ? '#0f1b2d' : '#ffc93c' }}"/>
</svg>
