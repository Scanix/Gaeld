<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about:gaeld', function () {
    $this->comment('Gäld console routes loaded.');
})->purpose('Confirm Gäld console routes are registered.');