<?php

declare(strict_types=1);

namespace App\Services\Knowledge\Extractors;

use App\Contracts\Knowledge\DocumentExtractor;
use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;
use SimpleXMLElement;
use Throwable;

class XlsxExtractor implements DocumentExtractor
{
    public function supports(Document $document): bool
    {
        return $document->source_type === 'xlsx'
            || $document->mime_type === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function extract(Document $document): ExtractedDocument
    {
        $path = Storage::disk('knowledge')->path($document->storage_path);

        if (! file_exists($path)) {
            throw new RuntimeException("Fichier Excel introuvable.");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Impossible d'ouvrir l'archive zip du fichier Excel.");
        }

        try {
            // 1. Lire les chaînes de texte partagées (Shared Strings)
            $sharedStrings = [];
            $stringsEntry = $zip->getFromName('xl/sharedStrings.xml');
            if ($stringsEntry !== false) {
                $xml = new SimpleXMLElement($stringsEntry);
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $sharedStrings[] = (string) $si->t;
                    } else {
                        $textParts = [];
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                if (isset($r->t)) {
                                    $textParts[] = (string) $r->t;
                                }
                            }
                        }
                        $sharedStrings[] = implode('', $textParts);
                    }
                }
            }

            // 2. Parcourir les feuilles XML et extraire les valeurs des cellules
            $textRows = [];
            $sheetIndex = 1;
            while (true) {
                $sheetName = "xl/worksheets/sheet{$sheetIndex}.xml";
                $sheetEntry = $zip->getFromName($sheetName);
                
                if ($sheetEntry === false) {
                    break;
                }

                $xml = new SimpleXMLElement($sheetEntry);
                if (isset($xml->sheetData->row)) {
                    foreach ($xml->sheetData->row as $row) {
                        $rowCells = [];
                        if (isset($row->c)) {
                            foreach ($row->c as $cell) {
                                $val = isset($cell->v) ? (string) $cell->v : '';
                                $type = isset($cell['t']) ? (string) $cell['t'] : '';

                                if ($type === 's' && $val !== '' && isset($sharedStrings[(int) $val])) {
                                    $val = $sharedStrings[(int) $val];
                                }
                                $rowCells[] = $val;
                            }
                        }
                        $rowCells = array_filter(array_map('trim', $rowCells));
                        if (! empty($rowCells)) {
                            $textRows[] = implode(' | ', $rowCells);
                        }
                    }
                }
                $sheetIndex++;
            }

            $text = implode("\n", $textRows);
        } catch (Throwable $e) {
            $zip->close();

            throw new RuntimeException("Erreur lors de l'extraction des données Excel : ".$e->getMessage(), 0, $e);
        }

        // Fermeture hors du try : une archive déjà fermée par le `catch`
        // recevait un second `close()`, qui émet un avertissement PHP.
        $zip->close();

        if (trim($text) === '') {
            throw new RuntimeException("Le fichier Excel ne contient aucun texte extractible.");
        }

        return new ExtractedDocument($text, [
            'source' => $document->title,
            'size'   => strlen($text),
        ]);
    }
}
