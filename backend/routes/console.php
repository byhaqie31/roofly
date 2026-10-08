<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('analytics:prune')->daily();

// Rent invoices roll forward once a day (ADR-006): new periods inside the
// 30-day horizon are created, unpaid ones past due become overdue. Local
// midnight for landlords, not UTC.
Schedule::command('invoices:roll')->dailyAt('00:30')->timezone('Asia/Kuala_Lumpur')->withoutOverlapping();
