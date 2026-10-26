<x-layout title="Log in">
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold">Welcome back</h1>
            <p class="mt-1 text-sm text-slate-500">Log in to your {{ config('app.name') }} account.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
                @csrf

                <x-input name="email" label="Email" type="email" required autofocus autocomplete="username" />
                <x-input name="password" label="Password" type="password" required autocomplete="current-password" />

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="rounded border-slate-300 accent-emerald-600">
                    Remember me
                </label>

                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                    Log in
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-medium text-emerald-600 hover:underline">Register</a>
            </p>
        </div>
    </div>
</x-layout>
