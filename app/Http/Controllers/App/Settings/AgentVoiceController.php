<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Exceptions\VoiceProviderException;
use App\Models\Agent;
use App\Services\Voice\ElevenLabsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Ce que l'écran de l'agent a besoin de savoir sur la voix.
 *
 * Trois choses seulement : l'état de la connexion, la liste des voix, et un
 * échantillon à écouter. La clé ne sort jamais d'ici — le navigateur ne reçoit
 * que des noms de voix et des octets audio.
 */
class AgentVoiceController
{
    public function __construct(
        private readonly ElevenLabsService $voice,
    ) {}

    /** État de la connexion et voix disponibles. */
    public function index(Request $request, Agent $agent): JsonResponse
    {
        abort_unless($request->user()->can('view', $agent), 403);

        $health = $this->voice->healthCheck($agent);

        return response()->json([
            'configured' => $this->voice->isConfigured($agent),
            'health'     => $health,
            // Inutile d'interroger le fournisseur si la connexion est rompue :
            // la liste serait vide et l'attente inutile.
            'voices'     => $health['ok'] ? $this->voice->getVoices($agent) : [],
            'usage'      => $health['ok'] ? $this->voice->getUsage($agent) : null,
        ]);
    }

    /**
     * Échantillon d'une voix.
     *
     * Déclenché par un clic, jamais automatiquement : chaque écoute consomme
     * le quota du compte.
     */
    public function preview(Request $request, Agent $agent): Response|JsonResponse
    {
        abort_unless($request->user()->can('update', $agent), 403);

        $validated = $request->validate([
            'voice_id' => ['required', 'string', 'max:64'],
            'language' => ['nullable', 'string', 'in:fr,en'],
        ]);

        try {
            $speech = $this->voice->testVoice(
                voiceId: $validated['voice_id'],
                agent: $agent,
                language: $validated['language'] ?? 'fr',
            );
        } catch (VoiceProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response($speech->audio, 200, [
            'Content-Type'  => $speech->mimeType,
            'Cache-Control' => 'no-store',
        ]);
    }
}
