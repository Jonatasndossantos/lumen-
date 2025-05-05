<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use OpenAI;

class OpenAIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('openai', function ($app) {
            return OpenAI::client(config('openai.api_key'), config('openai.organization'));
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
} 