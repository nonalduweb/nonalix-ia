<?php

declare(strict_types=1);

return [

    /*
    |---------------------------------------------------------------------------
    | Clé de la plateforme
    |---------------------------------------------------------------------------
    | Utilisée quand l'entreprise n'apporte pas la sienne. Elle reste
    | exclusivement côté serveur : aucune route, aucune vue, aucun événement
    | diffusé ne doit jamais la faire sortir.
    */
    'api_key' => env('ELEVENLABS_API_KEY'),

    'base_url' => env('ELEVENLABS_BASE_URL', 'https://api.elevenlabs.io/v1'),

    /*
    |---------------------------------------------------------------------------
    | Modèles
    |---------------------------------------------------------------------------
    | Configurables, jamais figés dans le code : le catalogue d'ElevenLabs
    | évolue, et le choix qualité/latence dépend du contexte.
    |
    | `multilingual_v2` couvre 29 langues avec la meilleure restitution ; c'est
    | le bon choix pour une note vocale, où une seconde de plus ne se voit pas.
    | `flash_v2_5` privilégie la latence : c'est celui d'une conversation, où
    | l'attente s'entend.
    */
    'models' => [
        'stt'          => env('ELEVENLABS_STT_MODEL', 'scribe_v1'),
        'tts_quality'  => env('ELEVENLABS_TTS_MODEL', 'eleven_multilingual_v2'),
        'tts_realtime' => env('ELEVENLABS_TTS_REALTIME_MODEL', 'eleven_flash_v2_5'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Format de sortie
    |---------------------------------------------------------------------------
    | WhatsApp n'accepte en note vocale QUE de l'OGG encodé en Opus. Un MP3
    | passerait, mais s'afficherait comme une pièce jointe et non comme un
    | message vocal — ce qui n'est pas la même chose pour celui qui le reçoit.
    |
    | Le navigateur, lui, lit le MP3 sans difficulté.
    */
    'formats' => [
        // `opus_48000_64` et non `ogg_*` : ElevenLabs ne connaît pas ce dernier.
        // Les formats `opus_*` renvoient de l'Opus encapsulé en Ogg, ce que
        // WhatsApp attend précisément pour une note vocale.
        'whatsapp' => 'opus_48000_64',
        'web'      => 'mp3_44100_128',
    ],

    /*
    |---------------------------------------------------------------------------
    | Garde-fous
    |---------------------------------------------------------------------------
    | Un fichier audio arrive d'un tiers : sa taille et son type sont des
    | affirmations, pas des faits. On les vérifie avant de dépenser un appel.
    */
    'limits' => [
        'max_audio_bytes'   => (int) env('ELEVENLABS_MAX_AUDIO_BYTES', 20 * 1024 * 1024),
        'max_tts_chars'     => (int) env('ELEVENLABS_MAX_TTS_CHARS', 2500),
        'timeout_seconds'   => (int) env('ELEVENLABS_TIMEOUT', 60),
        'allowed_mime'      => [
            'audio/ogg', 'audio/opus', 'audio/mpeg', 'audio/mp3', 'audio/mp4',
            'audio/aac', 'audio/wav', 'audio/x-wav', 'audio/webm', 'audio/amr',
            'video/mp4', 'application/octet-stream',
        ],
    ],

    /*
    |---------------------------------------------------------------------------
    | Coût
    |---------------------------------------------------------------------------
    | En micro-centimes d'euro, jamais en flottant. À zéro par défaut : un coût
    | inventé serait pire qu'un coût absent. À renseigner d'après la grille
    | réellement contractée.
    */
    'cost' => [
        'tts_micros_per_char'      => (int) env('ELEVENLABS_COST_TTS_PER_CHAR', 0),
        'stt_micros_per_second'    => (int) env('ELEVENLABS_COST_STT_PER_SECOND', 0),
    ],

    // Voix de repli si l'agent n'en a pas choisi. Vide = première voix du compte.
    'default_voice_id' => env('ELEVENLABS_DEFAULT_VOICE'),

    'demo_text' => [
        'fr' => 'Bonjour, je suis votre assistant Nonalix IA. Comment puis-je vous aider aujourd\'hui ?',
        'en' => 'Hello, I am your Nonalix AI assistant. How may I help you today?',
    ],

];
