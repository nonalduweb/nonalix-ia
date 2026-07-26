<?php

declare(strict_types=1);

namespace App\Jobs\Knowledge;

use App\Enums\DocumentStatus;
use App\Jobs\Concerns\RunsInTenantContext;
use App\Models\Document;
use App\Services\Knowledge\DocumentIngestionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Ingestion d'un document dans la base de connaissances.
 *
 * File `knowledge`, priorité basse et timeout long : un PDF de plusieurs
 * centaines de pages peut occuper un worker plusieurs minutes, ce qui ne doit
 * jamais retarder un message WhatsApp en attente d'envoi.
 */
class IngestDocumentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use RunsInTenantContext;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public array $backoff = [30, 120];

    public function __construct(
        public readonly string $tenantId,
        public readonly string $documentId,
    ) {}

    public function uniqueId(): string
    {
        return 'document-ingest:'.$this->documentId;
    }

    public function handle(DocumentIngestionService $ingestion): void
    {
        $this->withTenant($this->tenantId, function () use ($ingestion) {
            $document = Document::query()->find($this->documentId);

            if ($document === null || $document->status === DocumentStatus::Ready) {
                return;
            }

            $ingestion->ingest($document);
        });
    }

    public function failed(Throwable $exception): void
    {
        // Le service capture déjà ses propres erreurs ; ce chemin ne concerne
        // que les échecs d'infrastructure (timeout du worker, OOM), pour
        // lesquels le document resterait sinon bloqué en « en cours ».
        $this->withTenant($this->tenantId, function () use ($exception) {
            Document::query()->find($this->documentId)?->markFailed(
                'Traitement interrompu : '.$exception->getMessage(),
            );
        });
    }
}
