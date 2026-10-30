<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.settings', [
            'settings' => $this->current(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'googleCallback' => route('auth.google.callback'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'app_name' => ['required', 'string', 'max:60'],
            'app_url' => ['required', 'url:http,https', 'max:255'],
            'timezone' => ['required', 'timezone:all'],
            'google_client_id' => ['nullable', 'string', 'max:255'],
            'google_client_secret' => ['nullable', 'string', 'max:255'],
            // Google sends people back here, so it has to reach our callback route.
            'google_redirect' => ['nullable', 'url:http,https', 'max:255', 'ends_with:/auth/google/callback'],
            // No SVG: it can carry scripts and is served from our own domain.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
        ]);

        $before = $this->current();
        $values = collect($data)->except('logo', 'remove_logo')->all();
        $values['app_url'] = rtrim($values['app_url'], '/');
        $values['google_redirect'] ??= route('auth.google.callback');

        // The secret is never sent back to the browser, so blank means "keep it".
        // Clearing the client ID turns Google sign-in off, secret included.
        if (blank($values['google_client_id'])) {
            $values['google_client_secret'] = null;
        } elseif (blank($values['google_client_secret'])) {
            unset($values['google_client_secret']);
        }

        if ($request->hasFile('logo')) {
            $values['logo'] = $this->storeLogo($request->file('logo'), $before['logo']);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo($before['logo']);
            $values['logo'] = null;
        }

        Setting::put($values);
        Setting::applyToConfig();

        $changes = collect($values)
            ->map(fn ($value, $key) => ['from' => $before[$key] ?? null, 'to' => $value])
            ->reject(fn ($change) => $change['from'] === $change['to'])
            ->map(fn ($change, $key) => in_array($key, Setting::SECRET)
                ? ['from' => $change['from'] ? 'hidden' : null, 'to' => $change['to'] ? 'hidden (changed)' : null]
                : $change);

        if ($changes->isNotEmpty()) {
            ActivityLog::record('settings.updated', 'Changed system settings', changes: $changes->all());
        }

        return redirect()->route('admin.settings')->with('status', $changes->isEmpty() ? 'Nothing to change.' : 'Settings saved.');
    }

    /**
     * What's in effect now: saved settings, or .env where nothing is saved.
     *
     * @return array<string, string|null>
     */
    private function current(): array
    {
        // An empty .env entry comes through as '', the form sends null; both mean "not set".
        $current = collect(Setting::CONFIG)->map(fn (string $config) => filled(config($config)) ? config($config) : null)->all();

        // config/services.php defaults to a relative path; show the full URL Google needs.
        $current['google_redirect'] = url($current['google_redirect'] ?? route('auth.google.callback', absolute: false));

        return $current;
    }

    private function storeLogo(UploadedFile $file, ?string $old): string
    {
        $name = 'logo-'.Str::random(10).'.'.$file->extension();
        $file->move(public_path('uploads'), $name);
        $this->deleteLogo($old);

        return "uploads/{$name}";
    }

    private function deleteLogo(?string $path): void
    {
        // Only ever delete our own uploads.
        if ($path && Str::startsWith($path, 'uploads/logo-')) {
            File::delete(public_path($path));
        }
    }
}
