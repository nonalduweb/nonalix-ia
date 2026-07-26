<?php

declare(strict_types=1);

namespace App\Services\Knowledge\Extractors;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Throwable;

class PdfExtractor implements DocumentExtractor
{
    public function supports(Document $document): bool
    {
        return $document->source_type === 'pdf'
            || $document->mime_type === 'application/pdf';
    }

    public function extract(Document $document): ExtractedDocument
    {
        $path = $document->storage_path;

        if ($path === null || ! Storage::disk('knowledge')->exists($path)) {
            throw new RuntimeException('Fichier PDF introuvable sur le stockage.');
        }

        try {
            $pdf = (new Parser)->parseFile(Storage::disk('knowledge')->path($path));
        } catch (Throwable $e) {
            throw new RuntimeException(
                'PDF illisible ou corrompu : '.$e->getMessage(), previous: $e,
            );
        }

        $pages = $pdf->getPages();
        $parts = [];

        // Le numéro de page est conservé : c'est ce qui permet à l'agent de
        // citer « page 12 du contrat » plutôt qu'un extrait sans origine.
        foreach ($pages as $index => $page) {
            $text = trim($page->getText());

            if ($text !== '') {
                $parts[] = "[page ".($index + 1)."]\n".$text;
            }
        }

        $text = implode("\n\n", $parts);

        if (trim($text) === '') {
            throw new RuntimeException(
                'Aucun texte extractible. Ce PDF est probablement un scan : '
                .'un traitement OCR serait nécessaire.'
            );
        }

        return new ExtractedDocument($text, [
            'pages'  => count($pages),
            'source' => $document->title,
        ]);
    }
}
