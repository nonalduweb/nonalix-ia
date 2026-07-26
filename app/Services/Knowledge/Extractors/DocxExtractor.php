<?php

declare(strict_types=1);

namespace App\Services\Knowledge\Extractors;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Throwable;

class DocxExtractor implements DocumentExtractor
{
    private const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function supports(Document $document): bool
    {
        return $document->source_type === 'docx' || $document->mime_type === self::MIME;
    }

    public function extract(Document $document): ExtractedDocument
    {
        $path = $document->storage_path;

        if ($path === null || ! Storage::disk('knowledge')->exists($path)) {
            throw new RuntimeException('Fichier DOCX introuvable sur le stockage.');
        }

        try {
            $word = IOFactory::load(Storage::disk('knowledge')->path($path), 'Word2007');
        } catch (Throwable $e) {
            throw new RuntimeException(
                'DOCX illisible ou corrompu : '.$e->getMessage(), previous: $e,
            );
        }

        $lines = [];

        foreach ($word->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                $text = $this->readElement($element);

                if ($text !== '') {
                    $lines[] = $text;
                }
            }
        }

        $text = implode("\n\n", $lines);

        if (trim($text) === '') {
            throw new RuntimeException('Le document ne contient aucun texte exploitable.');
        }

        return new ExtractedDocument($text, ['source' => $document->title]);
    }

    /**
     * Lit un élément PhpWord.
     *
     * Les tableaux sont aplatis en lignes séparées par des barres verticales :
     * un tarif dans un tableau doit rester lisible une fois vectorisé, sinon
     * l'information la plus utile du document devient inaccessible.
     */
    private function readElement(mixed $element): string
    {
        if ($element instanceof Text) {
            return trim((string) $element->getText());
        }

        if ($element instanceof TextRun) {
            $parts = [];

            foreach ($element->getElements() as $child) {
                $parts[] = $this->readElement($child);
            }

            return trim(implode('', $parts));
        }

        if ($element instanceof Table) {
            $rows = [];

            foreach ($element->getRows() as $row) {
                $cells = [];

                foreach ($row->getCells() as $cell) {
                    $cellParts = [];

                    foreach ($cell->getElements() as $child) {
                        $cellParts[] = $this->readElement($child);
                    }

                    $cells[] = trim(implode(' ', array_filter($cellParts)));
                }

                $rows[] = implode(' | ', $cells);
            }

            return implode("\n", array_filter($rows));
        }

        if (method_exists($element, 'getText')) {
            $text = $element->getText();

            return is_string($text) ? trim($text) : '';
        }

        return '';
    }
}
