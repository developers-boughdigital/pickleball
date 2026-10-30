@php
    $canEdit = auth()->user()->can('settings.update');
    $logoUrl = $settings['logo'] ? asset($settings['logo']) : null;
    $googleOn = filled($settings['google_client_id']) && filled($settings['google_client_secret']);
@endphp

<x-admin.layout title="System Settings" :breadcrumb="['System', 'Settings']">
    {{-- data-reload: a full page load so the new name and logo show in the sidebar too. --}}
    <form
        method="POST"
        action="{{ route('admin.settings.update') }}"
        enctype="multipart/form-data"
        data-reload
        x-data="settingsForm(@js(['name' => old('app_name', $settings['app_name']), 'logo' => $logoUrl]))"
        @input="dirty = true"
        @change="dirty = true"
        class="space-y-3"
    >
        @csrf
        @method('PUT')
        <input type="hidden" name="remove_logo" :value="removeLogo ? 1 : 0" value="0">

        <x-admin.form-heading icon="sliders">
            <h1 class="truncate text-xl font-bold tracking-tight">System Settings</h1>
            <p class="truncate text-sm text-slate-500">
                @if ($canEdit)
                    Changes apply as soon as you save.
                @else
                    You can view these settings, but not change them.
                @endif
            </p>
        </x-admin.form-heading>

        <fieldset @disabled(! $canEdit) class="space-y-3">
            {{-- General --}}
            <section class="card p-4 sm:p-5">
                <h2 class="mb-3 text-sm font-semibold">General</h2>

                <div class="mb-4 flex flex-wrap items-center gap-4">
                    <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-xl bg-ink-950 p-2">
                        <img x-show="logo" :src="logo" alt="Logo" class="max-h-full max-w-full object-contain" @if (! $logoUrl) x-cloak @endif src="{{ $logoUrl }}">
                        <x-admin.brand-mark x-show="! logo" class="size-11" :custom="false" />
                    </div>

                    <div class="min-w-0">
                        <p class="text-sm font-medium">Logo</p>
                        <p class="text-xs text-slate-500">PNG, JPG or WebP, up to 1 MB. Square works best.</p>

                        <div class="mt-2 flex items-center gap-2">
                            <label class="btn-outline h-7 cursor-pointer px-2.5 text-xs has-disabled:cursor-not-allowed has-disabled:opacity-50">
                                <x-icon name="upload" class="size-3.5" />
                                Upload
                                <input type="file" name="logo" x-ref="logo" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="pickLogo">
                            </label>
                            <button type="button" x-show="logo" @click="clearLogo" class="h-7 px-2 text-xs font-medium text-red-600 hover:text-red-800 disabled:hidden" @if (! $logoUrl) x-cloak @endif>
                                Use default
                            </button>
                        </div>
                        @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <x-admin.input name="app_name" label="App name" icon="tag" :value="old('app_name', $settings['app_name'])" x-model="name" required maxlength="60" />
                    <x-admin.input name="app_url" label="App URL" type="url" icon="globe" :value="old('app_url', $settings['app_url'])" required maxlength="255" placeholder="https://example.com" />

                    <div>
                        <label for="timezone" class="mb-1 block text-sm font-medium">Timezone</label>
                        <div class="relative">
                            <x-icon name="clock" class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-slate-400" />
                            <select id="timezone" name="timezone" class="field w-full cursor-pointer appearance-none pr-8 pl-8">
                                @foreach ($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected(old('timezone', $settings['timezone']) === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                                @endforeach
                            </select>
                            <x-icon name="chevron-down" class="pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2 text-slate-500" />
                        </div>
                        @error('timezone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            {{-- Google --}}
            <section class="card p-4 sm:p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="text-sm font-semibold">Google sign-in</h2>
                    <span class="{{ $googleOn ? 'badge-success' : 'badge-muted' }}">{{ $googleOn ? 'On' : 'Off' }}</span>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-admin.input name="google_client_id" label="Client ID" icon="key" :value="old('google_client_id', $settings['google_client_id'])" maxlength="255" autocomplete="off" placeholder="1234-abc.apps.googleusercontent.com" hint="Clear it to turn Google sign-in off." />

                    <x-admin.input
                        name="google_client_secret"
                        label="Client secret"
                        type="password"
                        icon="lock"
                        x-bind:type="showSecret ? 'text' : 'password'"
                        maxlength="255"
                        autocomplete="new-password"
                        :placeholder="filled($settings['google_client_secret']) ? 'Saved. Leave blank to keep it' : 'GOCSPX-…'"
                    >
                        <button
                            type="button"
                            @click="showSecret = ! showSecret"
                            class="absolute top-1/2 right-1.5 grid size-6 -translate-y-1/2 place-items-center rounded text-slate-400 hover:text-ink-950"
                            :aria-label="showSecret ? 'Hide secret' : 'Show secret'"
                        >
                            <x-icon name="eye" class="size-4" x-show="! showSecret" />
                            <x-icon name="eye-off" class="size-4" x-show="showSecret" x-cloak />
                        </button>
                    </x-admin.input>

                    <div class="flex items-start gap-2 sm:col-span-2">
                        <div class="min-w-0 flex-1">
                            <x-admin.input
                                name="google_redirect"
                                label="Authorised redirect URI"
                                type="url"
                                icon="globe"
                                :value="old('google_redirect', $settings['google_redirect'])"
                                x-ref="redirect"
                                maxlength="255"
                                :placeholder="$googleCallback"
                                class="font-mono text-xs"
                                hint="Must match the URI in your OAuth client in the Google Cloud console. Leave blank for the default."
                            />
                        </div>
                        <button type="button" @click="copy($refs.redirect.value || @js($googleCallback))" class="btn-outline mt-6">
                            <x-icon name="copy" class="size-4" />
                            <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
                        </button>
                    </div>
                </div>
            </section>
        </fieldset>

        @if ($canEdit)
            <x-admin.form-actions>
                <a href="{{ route('admin.settings') }}" class="btn-outline">Discard</a>
                <button class="btn-gold">
                    <x-icon name="check" class="size-4" />
                    Save settings
                </button>
            </x-admin.form-actions>
        @endif
    </form>
</x-admin.layout>
