<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('audit:ensure-partitions')
    ->dailyAt('00:15')
    ->withoutOverlapping();

Schedule::command('documents:expire')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('collaboration:send-reminders')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('collaboration:send-daily-digest')
    ->dailyAt('07:30')
    ->withoutOverlapping();
