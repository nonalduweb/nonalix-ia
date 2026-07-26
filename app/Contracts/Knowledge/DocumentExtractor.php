<?php

declare(strict_types=1);

namespace App\Contracts\Knowledge;

use App\Data\Knowledge\ExtractedDocument;
use App\Models\Document;

/**
 * Extraction du texte d'un document source.
 *
 * Une implémentation par format. Ajouter un format (ODT, HTML, CSV…) revient
 * à écrire une classe et à l'enregistrer : le service d'ingestion ne change pas.
 */
interface DocumentExtractor
{
    /** Ce format est-il pris en charge par cet extracteur ? */
    public function supports(Document $document): bool;

    /**
     * Retourne le texte brut et ses métadonnées.
     *
     * @throws \RuntimeException  si le document est illisible ou corrompu
     */
    public function extract(Document $document): ExtractedDocument;
}
