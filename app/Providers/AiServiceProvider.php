<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\AI\AgentTool;
use App\Services\AI\AiProviderManager;
use App\Services\AI\Tools\GetBusinessHoursTool;
use App\Services\AI\Tools\ListServicesTool;
use App\Services\AI\Tools\QualifyLeadTool;
use App\Services\AI\Tools\RequestHumanHandoverTool;
use App\Services\Knowledge\DocumentIngestionService;
use App\Services\Knowledge\Extractors\DocxExtractor;
use App\Services\Knowledge\Extractors\PdfExtractor;
use App\Services\Knowledge\Extractors\TextExtractor;
use App\Services\Knowledge\Extractors\UrlExtractor;
use App\Services\Knowledge\RecursiveTextChunker;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiProviderManager::class);

        $this->app->singleton(RecursiveTextChunker::class, RecursiveTextChunker::fromConfig(...));

        // Registre des outils, indexé par nom. Un agent ne reçoit que ceux
        // qu'il a explicitement activés (voir `agents.enabled_tools`).
        $this->app->singleton('nonalix.agent.tools', function ($app): array {
            $tools = [
                $app->make(RequestHumanHandoverTool::class),
                $app->make(QualifyLeadTool::class),
                $app->make(ListServicesTool::class),
                $app->make(GetBusinessHoursTool::class),
            ];

            return collect($tools)
                ->keyBy(static fn (AgentTool $tool) => $tool->name())
                ->all();
        });

        // L'ordre des extracteurs compte : le premier qui déclare gérer le
        // document l'emporte. TextExtractor est en dernier car son test
        // (`text/*`) est le plus permissif.
        $this->app->singleton(DocumentIngestionService::class, fn ($app) => new DocumentIngestionService(
            extractors: [
                $app->make(PdfExtractor::class),
                $app->make(DocxExtractor::class),
                $app->make(UrlExtractor::class),
                $app->make(TextExtractor::class),
            ],
            chunker: $app->make(RecursiveTextChunker::class),
            providers: $app->make(AiProviderManager::class),
            vectors: $app->make(\App\Services\Knowledge\VectorSearchService::class),
            quotas: $app->make(\App\Services\Billing\QuotaService::class),
        ));
    }
}
