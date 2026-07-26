<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Data\AI\ChatMessage;
use App\Enums\MessageDirection;
use App\Models\Agent;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Mémoire conversationnelle : fenêtre glissante sur les derniers messages.
 *
 * Volontairement bornée. Envoyer l'intégralité d'un fil de plusieurs centaines
 * de messages coûterait cher à chaque tour, dépasserait la fenêtre de contexte
 * et dégraderait la qualité des réponses — les modèles se perdent dans les
 * historiques trop longs. Douze messages couvrent l'échange en cours, qui est
 * ce dont l'agent a réellement besoin.
 */
class ConversationMemory
{
    /**
     * Construit l'historique à envoyer au modèle, du plus ancien au plus récent.
     *
     * @return array<int, ChatMessage>
     */
    public function forConversation(Conversation $conversation, Agent $agent): array
    {
        $window = max(2, $agent->memory_window ?? (int) config('ai.agent.memory_window', 12));

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->forMemory()
            ->limit($window)
            ->get()
            ->reverse()
            ->values();

        $history = [];

        foreach ($messages as $message) {
            $content = $this->contentOf($message);

            if ($content === null) {
                continue;
            }

            $history[] = $message->direction === MessageDirection::Inbound
                ? ChatMessage::user($content)
                : ChatMessage::assistant($content);
        }

        return $history;
    }

    /**
     * Texte exploitable d'un message.
     *
     * Les médias n'ont pas de contenu textuel en Phase 1 : plutôt que de les
     * ignorer silencieusement — ce qui rendrait la conversation incohérente
     * pour le modèle — on les remplace par une description explicite.
     */
    private function contentOf(Message $message): ?string
    {
        if ($message->body !== null && trim($message->body) !== '') {
            return $message->body;
        }

        if ($message->type->isMedia()) {
            return sprintf('[Le contact a envoyé un fichier de type %s]', $message->type->value);
        }

        return null;
    }
}
