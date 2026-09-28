<?php

namespace App\Providers;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
        // Enregistre le transport "brevo" pour Laravel/Symfony Mailer
        Mail::extend('brevo', function (array $config = []) {
            $apiKey = $config['api_key'] ?? env('BREVO_API_KEY');
            $dsn = Dsn::fromString('brevo+api://' . $apiKey . '@default');
            return (new BrevoTransportFactory())->create($dsn);
        });
    }
}