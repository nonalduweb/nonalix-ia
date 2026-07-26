<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentStatus: string
{
    case Pending    = 'pending';
    case Extracting = 'extracting';
    case Chunking   = 'chunking';
    case Embedding  = 'embedding';
    case Ready      = 'ready';
    case Failed     = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'En attente',
            self::Extracting => 'Extraction du texte',
            self::Chunking   => 'Découpage',
            self::Embedding  => 'Vectorisation',
            self::Ready      => 'Prêt',
            self::Failed     => 'Échec',
        };
    }

    /** Le document est-il utilisable par la recherche RAG ? */
    public function isSearchable(): bool
    {
        return $this === self::Ready;
    }

    public function isProcessing(): bool
    {
        return in_array($this, [self::Pending, self::Extracting, self::Chunking, self::Embedding], true);
    }
}
