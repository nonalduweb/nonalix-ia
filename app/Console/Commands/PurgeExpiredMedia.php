<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Purge des médias WhatsApp expirés.
 *
 * Le fichier disparaît, le message reste : l'historique de la conversation
 * conserve la trace qu'un document a été échangé, sans en garder le contenu
 * au-delà de la durée de rétention annoncée au client.
 */
class PurgeExpiredMedia extends Command
{
    protected $signature = 'nonalix:purge-media {--days=}';

    protected $description = 'Supprime les médias WhatsApp au-delà de la rétention configurée.';

    public function handle(): int
    {
        $days   = (int) ($this->option('days') ?? config('nonalix.retention.media_days', 90));
        $cutoff = now()->subDays($days);
        $purged = 0;

        Message::withoutTenantScope()
            ->whereNotNull('media')
            ->where('created_at', '<', $cutoff)
            ->chunkById(500, function ($messages) use (&$purged) {
                foreach ($messages as $message) {
                    $path = $message->media['storage_path'] ?? null;

                    if (is_string($path) && Storage::disk('media')->exists($path)) {
                        Storage::disk('media')->delete($path);
                    }

                    // On conserve les métadonnées (type, nom de fichier) et on
                    // marque explicitement la purge, pour que l'interface
                    // puisse expliquer pourquoi le fichier n'est plus là.
                    $media = $message->media;
                    unset($media['storage_path']);
                    $media['purged_at'] = now()->toIso8601String();

                    $message->forceFill(['media' => $media])->saveQuietly();

                    $purged++;
                }
            });

        $this->info("{$purged} média(s) purgé(s) (rétention : {$days} jours).");

        return self::SUCCESS;
    }
}
