<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('stripe:sync-transactions')->daily();
Schedule::command('checklist:generate')->monthlyOn(1, '00:00');
Schedule::command('db:backup')->dailyAt('02:00');
