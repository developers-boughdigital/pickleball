<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('localities:import {file? : CSV to load instead of database/data/localities.csv}', function (?string $file = null) {
    $count = \App\Support\Geography::importLocalities($file);
    $this->info("Loaded {$count} suburbs.");
})->purpose('Load the suburb, postcode, council and city lookup');

Artisan::command('directory:fill-locations', function () {
    $filled = \App\Support\Geography::fillDirectory();
    $this->info("Filled city and council on {$filled['venues']} venues and {$filled['clubs']} clubs.");
})->purpose('Fill blank city and council on venues and clubs from their postcode');
