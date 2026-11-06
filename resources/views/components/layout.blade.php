{{-- Log in and register: the public site's header and footer around a narrow page. --}}
@props(['title' => null])

<x-site :title="$title">
    <div class="site-wrap py-8 sm:py-12">
        @if (session('status'))
            <div class="mx-auto mb-6 max-w-md rounded-xl bg-gum-soft px-4 py-3 font-bold text-gum-text">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot }}
    </div>
</x-site>
