<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reenvío automático de comprobantes electrónicos pendientes/con error a SUNAT.
// Requiere tener el scheduler activo: * * * * * php artisan schedule:run
Schedule::command('facturacion:reintentar')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

// Resumen diario de boletas (RC): cada día a la 01:00 procesa las del día anterior.
Schedule::command('facturacion:resumen-boletas')
    ->dailyAt('01:00')
    ->withoutOverlapping();

// Los resúmenes de boletas (RC) y las bajas de facturas (RA) se procesan de
// forma asíncrona: SUNAT devuelve un ticket que hay que volver a consultar
// hasta obtener el CDR. Sin esto las boletas se quedan PENDIENTE para siempre
// y las bajas en "Enviado" sin confirmar.
Schedule::command('facturacion:consultar-tickets')
    ->everyThirtyMinutes()
    ->withoutOverlapping()
    ->runInBackground();
