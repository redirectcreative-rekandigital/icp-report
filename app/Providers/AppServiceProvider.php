<?php

namespace App\Providers;

use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Settings::class);

        // Short reference code attached to logged errors and shown to users (PRD-07 §8.1).
        $this->app->singleton('error.ref', fn () => 'ERR-'.Str::upper(Str::random(5)));
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());
    }
}
