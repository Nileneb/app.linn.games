<?php

use App\Mail\DeployNotificationMail;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('deploy:notify', function () {
    Mail::to('bene@linn.games')->send(new DeployNotificationMail(
        deployedAt: now()->format('d.m.Y H:i:s'),
        appUrl: config('app.url'),
    ));

    $this->info('Deploy-Benachrichtigung an bene@linn.games gesendet.');
})->purpose('Send a deploy notification email to verify mail delivery');
