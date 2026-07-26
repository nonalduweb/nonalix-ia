<?php

declare(strict_types=1);

namespace App\Contracts\AI;

use App\Data\AI\ToolDefinition;
use App\Models\Conversation;

/**
 * Outil que l'agent IA peut invoquer.
 *
 * Un outil ne fait jamais confiance aux arguments produits par le modèle :
 * ce sont des données non validées, générées par une machine probabiliste.
 * Chaque implémentation valide ses entrées et agit toujours dans le périmètre
 * du tenant de la conversation reçue.
 */
interface AgentTool
{
    /** Identifiant stable, référencé dans `agents.enabled_tools`. */
    public function name(): string;

    /**
     * Déclaration exposée au modèle.
     *
     * La description fait partie du prompt : c'est elle qui détermine si
     * l'outil est appelé au bon moment.
     */
    public function definition(): ToolDefinition;

    /**
     * Exécute l'outil et renvoie le texte réinjecté dans la conversation.
     *
     * La valeur retournée est lue par le modèle, pas par un humain : elle doit
     * être factuelle et compacte. En cas d'erreur, renvoyer un message
     * explicatif plutôt que de lever une exception — l'agent doit pouvoir
     * s'expliquer auprès du contact.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function execute(array $arguments, Conversation $conversation): string;
}
