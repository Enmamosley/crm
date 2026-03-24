<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Enviar recordatorios de pago diariamente a las 9am
Schedule::command('invoices:send-reminders --days=7')->dailyAt('09:00');

// Procesar facturas recurrentes diariamente a las 6am
Schedule::command('invoices:process-recurring')->dailyAt('06:00');

// Marcar cotizaciones vencidas diariamente a las 7am
Schedule::command('quotes:expire')->dailyAt('07:00');

// Backup de base de datos diariamente a las 2am
Schedule::command('db:backup')->dailyAt('02:00');

// Procesar dunning (reintentos de cobro) diariamente a las 10am
Schedule::command('dunning:process')->dailyAt('10:00');
