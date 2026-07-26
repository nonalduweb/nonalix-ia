<?php

declare(strict_types=1);

namespace App\Http\Controllers\App;

use App\Enums\DocumentStatus;
use App\Jobs\Knowledge\IngestDocumentJob;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KnowledgeDocumentController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', Document::class), 403);

        return Inertia::render('Knowledge/Index', [
            'documents' => Document::query()
                ->with('uploader:id,name')
                ->latest()
                ->paginate(25),
            'stats' => [
                'ready'      => Document::query()->searchable()->count(),
                'processing' => Document::query()->whereIn('status', [
                    DocumentStatus::Pending->value, DocumentStatus::Extracting->value,
                    DocumentStatus::Chunking->value, DocumentStatus::Embedding->value,
                ])->count(),
                'failed'     => Document::query()->where('status', DocumentStatus::Failed->value)->count(),
                'chunks'     => DocumentChunk::query()->count(),
            ],
            'limits' => [
                'max_bytes'  => config('nonalix.knowledge.max_document_bytes'),
                'mime_types' => config('nonalix.knowledge.allowed_mime_types'),
            ],
        ]);
    }

    /** Import d'un fichier ou d'une URL. */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('create', Document::class), 403);

        $maxKilobytes = (int) (config('nonalix.knowledge.max_document_bytes') / 1024);

        $validated = $request->validate([
            'source_type' => ['required', Rule::in(['pdf', 'docx', 'txt', 'url'])],
            'title'       => ['required', 'string', 'max:250'],
            'file'        => [
                'required_unless:source_type,url', 'nullable', 'file',
                "max:{$maxKilobytes}",
                'mimes:pdf,docx,txt,md',
            ],
            'source_url'  => ['required_if:source_type,url', 'nullable', 'url:http,https', 'max:2000'],
        ]);

        $isUrl = $validated['source_type'] === 'url';

        // Empreinte du contenu : l'unicité (tenant_id, checksum) empêche
        // d'indexer deux fois le même fichier et de payer deux fois les
        // embeddings correspondants.
        $checksum = $isUrl
            ? hash('sha256', (string) $validated['source_url'])
            : hash_file('sha256', $request->file('file')->getRealPath());

        $existing = Document::query()->where('checksum', $checksum)->first();

        if ($existing !== null) {
            return back()->withErrors([
                'file' => "Ce contenu est déjà présent dans la base de connaissances « {$existing->title} ».",
            ]);
        }

        $path     = null;
        $mimeType = null;
        $size     = null;

        if (! $isUrl) {
            $file = $request->file('file');
            // Stockage privé : les documents contiennent les données métier
            // confidentielles du client et ne doivent jamais être servis
            // directement par le serveur web.
            $path     = $file->store((string) $request->user()->tenant_id, 'knowledge');
            $mimeType = $file->getClientMimeType();
            $size     = $file->getSize();
        }

        $document = Document::create([
            'title'        => $validated['title'],
            'source_type'  => $validated['source_type'],
            'source_url'   => $validated['source_url'] ?? null,
            'storage_path' => $path,
            'mime_type'    => $mimeType,
            'size_bytes'   => $size,
            'checksum'     => $checksum,
            'status'       => DocumentStatus::Pending,
            'uploaded_by'  => $request->user()->id,
        ]);

        $this->audit->log('knowledge.document_created', $document, [
            'after' => ['title' => $document->title, 'source_type' => $document->source_type],
        ]);

        IngestDocumentJob::dispatch($document->tenant_id, $document->id)->onQueue('knowledge');

        return back()->with('success', 'Document ajouté. Le traitement est en cours.');
    }

    public function destroy(Request $request, Document $document): RedirectResponse
    {
        abort_unless($request->user()->can('delete', $document), 403);

        // Le fichier physique part avec l'enregistrement : conserver un
        // document supprimé sur le disque serait une fuite de données.
        if ($document->storage_path !== null) {
            Storage::disk('knowledge')->delete($document->storage_path);
        }

        $this->audit->log('knowledge.document_deleted', $document, [
            'before' => ['title' => $document->title],
        ]);

        // Les fragments partent en cascade (FK ON DELETE CASCADE).
        $document->forceDelete();

        return back()->with('success', 'Document supprimé.');
    }

    /** Relance l'ingestion (échec précédent, ou changement de modèle d'embedding). */
    public function reprocess(Request $request, Document $document): RedirectResponse
    {
        abort_unless($request->user()->can('reprocess', $document), 403);

        if ($document->status->isProcessing()) {
            return back()->withErrors(['status' => 'Ce document est déjà en cours de traitement.']);
        }

        $document->update(['status' => DocumentStatus::Pending, 'error' => null]);

        IngestDocumentJob::dispatch($document->tenant_id, $document->id)->onQueue('knowledge');

        return back()->with('success', 'Réindexation lancée.');
    }
}
