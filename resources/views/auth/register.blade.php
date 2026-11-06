<x-layout title="Register">
    <div class="mx-auto max-w-[480px]">
        <div class="rounded-[20px] bg-white p-6 shadow-[0_12px_32px_rgb(15_27_45/0.14)] sm:p-8">
            <h1 class="font-display text-4xl leading-[42px] font-bold">Create your account</h1>
            <p class="mt-2 text-lg leading-7 text-muted">Join {{ config('app.name') }} in a few seconds.</p>

            <form method="POST" action="{{ route('register') }}" class="mt-6 flex flex-col gap-5">
                @csrf

                <x-input name="name" label="Name" required autofocus autocomplete="name" />
                <x-input name="email" label="Email" type="email" required autocomplete="username" />
                <x-input name="password" label="Password" type="password" required autocomplete="new-password" />
                <x-input name="password_confirmation" label="Confirm password" type="password" required autocomplete="new-password" />

                <button type="submit" class="site-btn-primary min-h-14 w-full text-lg">Register</button>
            </form>

            @include('auth.partials.google')

            <p class="mt-6 text-center text-muted">
                Already have an account?
                <a href="{{ route('login') }}" class="site-link">Log in</a>
            </p>
        </div>
    </div>
</x-layout>
