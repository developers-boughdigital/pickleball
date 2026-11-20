{{-- [button url="…" text="…"] --}}
@php($external = str_starts_with($url, 'http') && ! str_starts_with($url, config('app.url')))
<p class="not-prose my-6">
    <a href="{{ $url }}" class="site-btn-ball min-h-14 px-6 text-lg" @if ($external) target="_blank" rel="noopener" @endif>{{ $text }}</a>
</p>
