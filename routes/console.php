<?php

use App\Services\Housekeeping;
use Illuminate\Support\Facades\Schedule;

Schedule::call(fn () => app(Housekeeping::class)->run())->hourly()->name('housekeeping');
