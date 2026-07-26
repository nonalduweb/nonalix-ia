<?php

declare(strict_types=1);

namespace App\Services\Knowledge\Extractors;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class TextExtractor implements DocumentExtractor
{
    public function supports(Document $document): bool
    {
        return in_array($document->source_type, ['txt', 'manual'], true)
            || str_starts_with((string) $document->mime_type, 'text/');
    }

    public function extract(Document $document): ExtractedDocument
    {
        $path = $document->storage_path;

        if ($path === null || ! Storage::disk('knowledge')->exists($path)) {
            throw new RuntimeException('Fichier texte introuvable sur le stockage.');
        }

        $raw = Storage::disk('knowledge')->get($path);

        if ($raw === null || trim($raw) === '') {
            throw new RuntimeException('Le fichier est vide.');
        }

        // Les fichiers déposés par des clients sont fréquemment en ISO-8859-1
        // ou en Windows-1252. Sans conversion, les accents ressortent en
        // caractères de remplacement dans les réponses de l'agent.
        $encoding = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8';

        if ($encoding !== 'UTF-8') {
            $raw = mb_convert_encoding($raw, 'UTF-8', $encoding);
        }

        return new ExtractedDocument($raw, [
            'source'            => $document->title,
            'original_encoding' => $encoding,
        ]);
    }
}
