<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Cache;

/**
 * Cleanup that normally runs from the scheduler; without cron it runs opportunistically
 * after a successful PIN login, at most once per hour (PRD-01 §8.2).
 */
class Housekeeping
{
    public const LOG_RETENTION_DAYS = 365;

    public function run(): void
    {
        ActivityLog::query()->where('created_at', '<', now()->subDays(self::LOG_RETENTION_DAYS))->delete();
        app(BatchPrintService::class)->purgeExpired();
    }

    public function runIfDue(): void
    {
        if (Cache::add('housekeeping.last_run', now()->getTimestamp(), 3600)) {
            $this->run();
        }
    }
}
