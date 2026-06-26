<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Password-reset emails link to the SPA reset page, not a backend route.
        ResetPassword::createUrlUsing(fn ($notifiable, string $token) => sprintf(
            '%s/#/reset-password?token=%s&email=%s',
            config('app.frontend_url'),
            $token,
            urlencode($notifiable->getEmailForPasswordReset()),
        ));
    }
}
