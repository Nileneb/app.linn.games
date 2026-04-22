<?php

namespace App\Providers;

use App\Events\WorkspaceLowBalance;
use App\Listeners\SendSlackLowBalanceAlert;
use App\Listeners\SetUpNewUser;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class UserEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        User::created(fn (User $user) => app(SetUpNewUser::class)($user));

        Event::listen(WorkspaceLowBalance::class, SendSlackLowBalanceAlert::class);
    }
}
