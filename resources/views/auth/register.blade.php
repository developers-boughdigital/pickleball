<x-layout title="Register">
    <div class="mx-auto max-w-md">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
            <h1 class="text-2xl font-bold">Create your account</h1>
            <p class="mt-1 text-sm text-slate-500">Join {{ config('app.name') }} in a few seconds.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
                @csrf

                <x-input name="name" label="Name" required autofocus autocomplete="name" />
                <x-input name="email" label="Email" type="email" required autocomplete="username" />
                <x-input name="password" label="Password" type="password" required autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

                <button type="submit" class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                    Register
                </button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-500">
                Already have an account?
                <a href="{{ route('login') }}" class="font-medium text-emerald-600 hover:underline">Log in</a>
            </p>
        </div>
    </div>
</x-layout>
