<?php

namespace App\Providers;

use App\Models\Recherche\P5Treffer;
use App\Observers\P5TrefferObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        P5Treffer::observe(P5TrefferObserver::class);
    }
}
