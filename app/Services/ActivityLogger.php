<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\PinSession;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    public function __construct(private Request $request) {}

    /**
     * @param  string  $category  one of ActivityLog::CATEGORIES keys
     * @param  array<string, mixed>  $meta  never put PINs or recovery codes here
     */
    public function log(string $action, string $category, string $description, ?Model $subject = null, array $meta = []): ActivityLog
    {
        return ActivityLog::query()->create([
            'action' => $action,
            'category' => $category,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'meta' => $meta ?: null,
            'ip' => $this->request->ip(),
            'device' => self::device($this->request->userAgent()),
            'session_short' => PinSession::tag($this->request),
            'created_at' => now(),
        ]);
    }

    /** Short, human summary of a user agent, e.g. "Chrome • Windows". */
    public static function device(?string $ua): ?string
    {
        if (! $ua) {
            return null;
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Browser lain',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'CrOS') => 'ChromeOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'OS lain',
        };

        return "{$browser} • {$os}";
    }
}
