<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Settings changed from the admin. Each one overrides a config value at boot;
 * anything never saved here falls back to .env as usual.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /** Setting key => the config value it overrides. */
    public const CONFIG = [
        'app_name' => 'app.name',
        'app_url' => 'app.url',
        'timezone' => 'app.timezone',
        'logo' => 'app.logo',
        'google_client_id' => 'services.google.client_id',
        'google_client_secret' => 'services.google.client_secret',
        'google_redirect' => 'services.google.redirect',
    ];

    /** Stored encrypted with APP_KEY. */
    public const SECRET = ['google_client_secret'];

    /**
     * Saved settings, secrets decrypted.
     *
     * @return array<string, string|null>
     */
    public static function values(): array
    {
        return static::pluck('value', 'key')
            ->map(fn ($value, $key) => in_array($key, self::SECRET) && $value !== null
                // A secret saved under an old APP_KEY can't be read; treat it as unset.
                ? rescue(fn () => Crypt::decryptString($value), null, report: false)
                : $value)
            ->all();
    }

    /**
     * Save settings. Null is saved too: a field cleared in the admin stays
     * cleared rather than falling back to .env.
     *
     * @param  array<string, string|null>  $values
     */
    public static function put(array $values): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], [
                'value' => in_array($key, self::SECRET) && filled($value) ? Crypt::encryptString($value) : $value,
            ]);
        }
    }

    public static function applyToConfig(): void
    {
        foreach (static::values() as $key => $value) {
            if (isset(self::CONFIG[$key])) {
                config([self::CONFIG[$key] => $value]);
            }
        }

        date_default_timezone_set(config('app.timezone'));
    }
}
