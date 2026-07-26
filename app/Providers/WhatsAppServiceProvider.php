<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\WhatsApp\InboundMessageHandler;
use App\Services\WhatsApp\WebhookParser;
use App\Services\WhatsApp\WebhookSignatureVerifier;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Services sans état : un singleton suffit et évite de les
        // reconstruire à chaque webhook.
        $this->app->singleton(WebhookSignatureVerifier::class);
        $this->app->singleton(WebhookParser::class);
        $this->app->singleton(InboundMessageHandler::class);
    }
}
