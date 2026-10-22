@php
    $defaultDriver = collect(['pgsql', 'mysql', 'sqlite'])->first(fn ($d) => $drivers[$d]['available']) ?? 'sqlite';
    $failing = collect($requirements)->reject(fn ($check) => $check['ok']);
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Install PickleBall</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ink-950 font-sans text-base text-ink-950 antialiased">
    <div
        x-data="installer(@js([
            'appName' => $defaults['appName'],
            'appUrl' => $defaults['appUrl'],
            'sqlitePath' => $defaults['sqlitePath'],
            'drivers' => $drivers,
            'defaultDriver' => $defaultDriver,
            'routes' => ['database' => route('install.database'), 'install' => route('install.store')],
        ]))"
        class="mx-auto max-w-2xl px-4 py-10 sm:py-16"
    >
        <header class="flex items-center justify-center gap-3">
            <x-admin.brand-mark class="size-11" />
            <span class="text-2xl font-extrabold tracking-tight text-white">Pickle<span class="text-brand-400">Ball</span></span>
        </header>

        {{-- Progress --}}
        <ol class="mt-10 flex items-center gap-2" aria-label="Installation steps">
            <template x-for="(label, index) in steps" :key="label">
                <li class="flex flex-1 items-center gap-2" :aria-current="index === step ? 'step' : null">
                    <span
                        class="grid size-9 shrink-0 place-items-center rounded-full text-sm font-semibold transition"
                        :class="index < step ? 'bg-brand-500 text-ink-950' : index === step ? 'bg-white text-ink-950' : 'bg-white/10 text-slate-400'"
                    >
                        <span x-show="index >= step" x-text="index + 1"></span>
                        <x-icon name="check" class="size-4" x-show="index < step" x-cloak stroke-width="3" />
                    </span>
                    <span class="hidden text-sm font-medium sm:block" :class="index <= step ? 'text-white' : 'text-slate-400'" x-text="label"></span>
                    <span x-show="index < steps.length - 1" class="h-px flex-1 bg-white/15"></span>
                </li>
            </template>
        </ol>

        <main class="mt-8 rounded-2xl bg-white p-6 shadow-xl sm:p-10">
            {{-- 1. Welcome --}}
            <section x-show="step === 0" data-step="0">
                <h1 class="text-[28px] leading-tight font-bold tracking-tight">Welcome</h1>
                <p class="mt-3 text-slate-600">
                    Let’s get PickleBall running. You’ll need your database details, then you’ll name the site and create the first admin account.
                    It takes about two minutes.
                </p>

                <div x-data="{ open: {{ $failing->isNotEmpty() ? 'true' : 'false' }} }" class="mt-8 rounded-xl border border-slate-200">
                    <button type="button" @click="open = ! open" :aria-expanded="open.toString()" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left">
                        @if ($meetsRequirements)
                            <span class="flex items-center gap-3 font-medium text-emerald-700">
                                <x-icon name="shield-check" /> All {{ count($requirements) }} server checks passed
                            </span>
                        @else
                            <span class="flex items-center gap-3 font-medium text-red-600">
                                <x-icon name="triangle-alert" /> {{ $failing->count() }} of {{ count($requirements) }} server checks need fixing
                            </span>
                        @endif
                        <x-icon name="chevron-down" class="size-4 text-slate-500 transition" ::class="open && 'rotate-180'" />
                    </button>

                    <ul x-show="open" x-transition.opacity x-cloak class="divide-y divide-slate-100 border-t border-slate-200">
                        @foreach ($requirements as $check)
                            <li class="flex items-center justify-between gap-4 px-5 py-3 text-sm">
                                <span class="flex items-center gap-2.5">
                                    @if ($check['ok'])
                                        <x-icon name="check" class="size-4 text-emerald-600" stroke-width="3" />
                                    @else
                                        <x-icon name="x" class="size-4 text-red-600" stroke-width="3" />
                                    @endif
                                    {{ $check['label'] }}
                                </span>
                                <span @class(['text-right', 'text-slate-500' => $check['ok'], 'font-medium text-red-600' => ! $check['ok']])>{{ $check['detail'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-8 flex justify-end">
                    @if ($meetsRequirements)
                        <button type="button" @click="next()" class="btn-gold h-12 px-6 text-base">Let’s go</button>
                    @else
                        <a href="{{ url('/install') }}" data-reload class="btn-outline h-12 px-6 text-base">Check again</a>
                    @endif
                </div>
            </section>

            {{-- 2. Site --}}
            <form x-show="step === 1" x-cloak data-step="1" @submit.prevent="next()" class="space-y-6">
                <div>
                    <h1 class="text-[28px] leading-tight font-bold tracking-tight">Your site</h1>
                    <p class="mt-2 text-slate-600">You can change these later in the .env file.</p>
                </div>

                <x-install.field name="app_name" label="Site name" required maxlength="60" />
                <x-install.field name="app_url" label="Site address (URL)" type="url" required hint="The address people type to reach the site, without a trailing slash." />

                <fieldset>
                    <legend class="text-base font-medium">Environment</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2">
                        @foreach (['production' => ['Live site', 'Errors are hidden from visitors.'], 'local' => ['Development', 'Shows detailed errors. Not for a live site.']] as $value => [$title, $description])
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-4 has-checked:border-brand-500 has-checked:bg-brand-50">
                                <input type="radio" name="app_env" value="{{ $value }}" x-model="form.app_env" class="mt-1 size-4 accent-brand-500">
                                <span>
                                    <span class="block font-medium">{{ $title }}</span>
                                    <span class="block text-sm text-slate-500">{{ $description }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="flex justify-between gap-3 pt-2">
                    <button type="button" @click="back()" class="btn-outline h-12 px-5 text-base">Back</button>
                    <button class="btn-gold h-12 px-6 text-base">Continue</button>
                </div>
            </form>

            {{-- 3. Database --}}
            <form x-show="step === 2" x-cloak data-step="2" @submit.prevent="continueFromDatabase()" class="space-y-6">
                <div>
                    <h1 class="text-[28px] leading-tight font-bold tracking-tight">Database</h1>
                    <p class="mt-2 text-slate-600">Your host gives you these details. The database must already exist; the tables are created for you.</p>
                </div>

                <fieldset>
                    <legend class="text-base font-medium">Database type</legend>
                    <div class="mt-2 grid gap-3 sm:grid-cols-3">
                        @foreach ($drivers as $value => $driver)
                            <label @class([
                                'flex items-center gap-3 rounded-xl border border-slate-200 p-4',
                                'cursor-pointer has-checked:border-brand-500 has-checked:bg-brand-50' => $driver['available'],
                                'cursor-not-allowed opacity-50' => ! $driver['available'],
                            ]) @if (! $driver['available']) title="The pdo_{{ $value }} PHP extension is not enabled" @endif>
                                <input type="radio" name="db_driver" value="{{ $value }}" x-model="form.db_driver" @disabled(! $driver['available']) class="size-4 accent-brand-500">
                                <span class="font-medium">{{ $driver['label'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <template x-if="! isSqlite">
                    <div class="space-y-6">
                        <div class="grid gap-6 sm:grid-cols-[1fr_8rem]">
                            <x-install.field name="db_host" label="Host" required hint="Usually 127.0.0.1 or localhost." />
                            <x-install.field name="db_port" label="Port" type="number" min="1" max="65535" required />
                        </div>
                        <x-install.field name="db_database" label="Database name" required />
                        <div class="grid gap-6 sm:grid-cols-2">
                            <x-install.field name="db_username" label="Username" required autocomplete="off" />
                            <x-install.field name="db_password" label="Password" type="password" autocomplete="new-password" />
                        </div>
                    </div>
                </template>

                <template x-if="isSqlite">
                    <x-install.field name="db_database" label="Database file" required hint="Created for you if it doesn’t exist. Relative paths start from the project folder." />
                </template>

                <div
                    x-show="dbStatus"
                    x-cloak
                    role="status"
                    class="flex items-start gap-3 rounded-lg border px-4 py-3"
                    :class="dbStatus?.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-red-200 bg-red-50 text-red-700'"
                >
                    <x-icon name="check" class="mt-0.5 size-5" x-show="dbStatus?.ok" stroke-width="2.5" />
                    <x-icon name="triangle-alert" class="mt-0.5 size-5" x-show="! dbStatus?.ok" />
                    <span class="break-words" x-text="dbStatus?.message"></span>
                </div>

                <div class="flex flex-wrap justify-between gap-3 pt-2">
                    <button type="button" @click="back()" class="btn-outline h-12 px-5 text-base">Back</button>
                    <div class="flex flex-wrap gap-3">
                        <button type="button" @click="$el.form.reportValidity() && checkDatabase()" :disabled="busy" class="btn-outline h-12 px-5 text-base disabled:opacity-60">
                            Test connection
                        </button>
                        <button :disabled="busy" class="btn-gold h-12 px-6 text-base disabled:opacity-60">
                            <span x-text="busy ? 'Checking…' : 'Continue'"></span>
                        </button>
                    </div>
                </div>
            </form>

            {{-- 4. Admin --}}
            <form x-show="step === 3" x-cloak data-step="3" @submit.prevent="install()" class="space-y-6">
                <div>
                    <h1 class="text-[28px] leading-tight font-bold tracking-tight">Admin account</h1>
                    <p class="mt-2 text-slate-600">This is the account you’ll log in with to manage the platform.</p>
                </div>

                <x-install.field name="admin_name" label="Your name" required autocomplete="name" />
                <x-install.field name="admin_email" label="Email" type="email" required autocomplete="email" />
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-install.field name="admin_password" label="Password" type="password" required minlength="8" autocomplete="new-password" hint="At least 8 characters." />
                    <x-install.field name="admin_password_confirmation" label="Confirm password" type="password" required minlength="8" autocomplete="new-password" />
                </div>

                <div x-show="message" x-cloak role="alert" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                    <x-icon name="triangle-alert" class="mt-0.5 size-5" />
                    <span class="break-words" x-text="message"></span>
                </div>

                <div class="flex justify-between gap-3 pt-2">
                    <button type="button" @click="back()" :disabled="busy" class="btn-outline h-12 px-5 text-base">Back</button>
                    <button :disabled="busy" class="btn-gold h-12 px-6 text-base disabled:opacity-60">
                        <svg x-show="busy" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".25" stroke-width="3" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
                        </svg>
                        <span x-text="busy ? 'Installing…' : 'Install PickleBall'"></span>
                    </button>
                </div>
            </form>

            {{-- 5. Done --}}
            <section x-show="step === 4" x-cloak data-step="4" class="text-center">
                <span class="mx-auto grid size-16 place-items-center rounded-full bg-emerald-100 text-emerald-600">
                    <x-icon name="check" class="size-8" stroke-width="2.5" />
                </span>
                <h1 class="mt-6 text-[28px] leading-tight font-bold tracking-tight">You’re all set</h1>
                <p class="mt-3 text-slate-600">
                    <span x-text="form.app_name"></span> is installed. The settings were saved to <code class="rounded bg-slate-100 px-1.5 py-0.5 text-sm">.env</code>
                    and this installer is now switched off.
                </p>

                <dl class="mx-auto mt-8 max-w-sm divide-y divide-slate-100 rounded-xl border border-slate-200 text-left">
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-slate-500">Site</dt><dd class="truncate font-medium" x-text="form.app_url"></dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-slate-500">Database</dt><dd class="font-medium" x-text="isSqlite ? 'SQLite' : form.db_database"></dd></div>
                    <div class="flex justify-between gap-4 px-5 py-3"><dt class="text-slate-500">Admin</dt><dd class="truncate font-medium" x-text="form.admin_email"></dd></div>
                </dl>

                <a :href="loginUrl" class="btn-gold mt-8 h-12 px-8 text-base">Log in</a>
            </section>
        </main>

        <p class="mt-6 text-center text-sm text-slate-400">Laravel {{ app()->version() }} · PHP {{ PHP_VERSION }}</p>
    </div>
</body>
</html>
