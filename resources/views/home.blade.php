<x-layout title="Home">
    <section class="rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 px-8 py-12 text-white shadow">
        <h1 class="text-3xl font-bold">Welcome to {{ config('app.name') }}</h1>

        @auth
            <p class="mt-2 text-emerald-50">You're signed in as {{ auth()->user()->email }}.</p>
        @else
            <p class="mt-2 text-emerald-50">Log in or create an account to get started.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('login') }}" class="rounded-lg bg-white px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                    Log in
                </a>
                <a href="{{ route('register') }}" class="rounded-lg border border-white/70 px-4 py-2 text-sm font-semibold text-white hover:bg-white/10">
                    Register
                </a>
            </div>
        @endauth
    </section>
</x-layout>
