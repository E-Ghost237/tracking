<?php

use Illuminate\Support\Facades\Schedule;

/*
| Scheduled jobs (section 14.5). Run `php artisan schedule:work` (dev) or a cron calling
| `php artisan schedule:run` every minute (production).
*/
Schedule::command('orders:expire')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('proofs:alert-overdue')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('payments:promote-details')->everyTenMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('tracking:poll')->everyThirtyMinutes()->withoutOverlapping()->onOneServer();
Schedule::command('data:prune')->dailyAt('03:15')->onOneServer();
Schedule::command('reports:daily-summary')->dailyAt('07:00')->onOneServer();
Schedule::command('queue:prune-failed --hours=720')->daily();
