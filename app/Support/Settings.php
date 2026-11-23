<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Key/value application settings (table `settings`), cached as one array.
 */
class Settings
{
    private const CACHE_KEY = 'settings.all';

    /** Keys that must never be sent to the browser. */
    public const SECRET_KEYS = ['pin_hash', 'recovery_code_hash'];

    public const DEFAULTS = [
        'school_name' => 'SD Muhammadiyah Wonorejo',
        'school_address' => '',
        'program_name' => 'International Class Program',
        'partner_name' => '',
        'city' => 'Wonorejo',
        'band_comments' => [],
        'principal_name' => '',
        'principal_id_label' => 'NBM',
        'principal_id_number' => '',
        'school_logo_path' => null,
        'second_logo_path' => null,
        'partner_logo_path' => null,
        'session_idle_minutes' => 60,
        'show_documents_menu' => true,
        'active_term_id' => null,
        'pin_hash' => null,
        'recovery_code_hash' => null,
        'pin_changed_at' => null,
        'setup_completed_at' => null,
    ];

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (array_key_exists($key, $all)) {
            return $all[$key];
        }

        return $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('settings')) {
                return [];
            }

            return Setting::query()->pluck('value', 'key')
                ->map(fn ($v) => $v === null ? null : json_decode($v, true))
                ->all();
        });

        return $this->loaded = array_merge(self::DEFAULTS, $stored);
    }

    /** @param array<string, mixed> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(
                ['key' => $key],
                ['value' => $value === null ? null : json_encode($value)],
            );
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::query()->whereKey($key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function isSetupComplete(): bool
    {
        return ! empty($this->get('pin_hash'));
    }

    public function idleMinutes(): int
    {
        return (int) $this->get('session_idle_minutes', 60);
    }
}
