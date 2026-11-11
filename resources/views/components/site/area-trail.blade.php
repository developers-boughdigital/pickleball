{{-- "More places to play in: Victoria › Melbourne › Banyule › Greensborough", each one a link to its location page. --}}
@props(['listing', 'query' => []])

@if ($area = $listing->locationArea())
    <p {{ $attributes->class('flex flex-wrap items-center gap-x-2 gap-y-1 text-muted') }}>
        <span>More places to play in</span>
        @foreach (array_slice($area->trail(), 1) as $step)
            @unless ($loop->first) <span aria-hidden="true">›</span> @endunless
            <a href="{{ $step->url($query) }}" class="site-link">{{ $step->name() }}</a>
        @endforeach
    </p>
@endif
