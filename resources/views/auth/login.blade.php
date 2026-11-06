<x-layout title="Log in">
    <div class="mx-auto max-w-[480px]">
        <div class="rounded-[20px] bg-white p-6 shadow-[0_12px_32px_rgb(15_27_45/0.14)] sm:p-8">
            <h1 class="font-display text-4xl leading-[42px] font-bold">Welcome back</h1>
            <p class="mt-2 text-lg leading-7 text-muted">Log in to your {{ config('app.name') }} account.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-6 flex flex-col gap-5">
                @csrf

                <x-input name="email" label="Email" type="email" required autofocus autocomplete="username" />
                <x-input name="password" label="Password" type="password" required autocomplete="current-password" />

                <label class="flex min-h-11 cursor-pointer items-center gap-3">
                    <input type="checkbox" name="remember" class="size-6 shrink-0 accent-court">
                    Remember me
                </label>

                <button type="submit" class="site-btn-primary min-h-14 w-full text-lg">Log in</button>
            </form>

            @include('auth.partials.google')

            <p class="mt-6 text-center text-muted">
                Don't have an account?
                <a href="{{ route('register') }}" class="site-link">Register</a>
            </p>
        </div>
    </div>
</x-layout>
