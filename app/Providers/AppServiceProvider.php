<?php

namespace App\Providers;

use App\Channels\Telegram\TelegramClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TelegramClient::class, fn () => new TelegramClient(
            (string) config('services.telegram.token'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
