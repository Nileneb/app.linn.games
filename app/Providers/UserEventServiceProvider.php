<?php

namespace App\Providers;

use App\Listeners\SetUpNewUser;
use App\Models\User;
use Illuminate\Support\ServiceProvider;

class UserEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::created(app(SetUpNewUser::class));
    }
}
