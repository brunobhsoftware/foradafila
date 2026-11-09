<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Convite por e-mail: manda um pouco da fila a cada minuto (cron do hPanel chama "php artisan schedule:run").
Artisan::command('convites:enviar', function () {
    $this->info(\App\Support\Convites::rodada().' convite(s) enviados.');
})->purpose('Envia a fila dos convites por e-mail');

\Illuminate\Support\Facades\Schedule::command('convites:enviar')->everyMinute()->withoutOverlapping(10);
