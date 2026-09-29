<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nobingo:health {--json}', function () {
    $this->call('bingo:health', [
        '--json' => $this->option('json'),
    ]);
})->purpose('Perform comprehensive system health checks across all subsystems');
