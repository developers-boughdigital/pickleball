{{--
    The app name with its last part in gold: "PickleBall" -> Pickle + Ball,
    "Riverside Club" -> Riverside + Club. A single plain word stays white.
--}}
@props(['accent' => 'bg-linear-to-r from-brand-400 to-brand-300 bg-clip-text text-transparent'])

@php
    $name = config('app.name');
    preg_match('/^(.*\S)(\s+\S+|[A-Z][^A-Z\s]*)$/u', $name, $parts);
@endphp

<span {{ $attributes }}>@if ($parts){{ $parts[1] }}<span class="{{ $accent }}">{{ $parts[2] }}</span>@else{{ $name }}@endif</span>
