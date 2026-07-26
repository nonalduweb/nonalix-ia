<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Document> */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        return [
            'title'        => $this->faker->sentence(3),
            'source_type'  => 'txt',
            'storage_path' => 'tests/'.Str::random(12).'.txt',
            'mime_type'    => 'text/plain',
            'size_bytes'   => 2048,
            'checksum'     => hash('sha256', Str::random(32)),
            'status'       => DocumentStatus::Pending,
            'chunks_count' => 0,
            'tokens_count' => 0,
        ];
    }

    public function ready(int $chunks = 3): static
    {
        return $this->state(fn () => [
            'status'             => DocumentStatus::Ready,
            'chunks_count'       => $chunks,
            'tokens_count'       => $chunks * 200,
            'embedding_provider' => config('ai.embeddings.provider'),
            'embedding_model'    => config('ai.embeddings.model'),
            'processed_at'       => now(),
        ]);
    }

    public function failed(string $reason = 'Fichier illisible.'): static
    {
        return $this->state(fn () => [
            'status' => DocumentStatus::Failed,
            'error'  => $reason,
        ]);
    }
}
