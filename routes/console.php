<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes / Scheduler
|--------------------------------------------------------------------------
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Monthly leave accrual (VL/SL 1.25 days/month) on the 1st at 00:05.
Schedule::command('leave:accrue')
    ->monthlyOn(1, '00:05')
    ->withoutOverlapping();
