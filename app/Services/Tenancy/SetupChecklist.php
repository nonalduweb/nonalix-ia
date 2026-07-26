<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Agent;
use App\Models\BusinessHour;
use App\Models\BusinessProfile;
use App\Models\Faq;
use App\Models\Service;
use App\Models\WhatsAppAccount;

/**
 * État d'avancement de la configuration d'une entreprise.
 *
 * Les écrans de réglages sont six onglets d'apparence équivalente, alors que
 * trois d'entre eux conditionnent réellement le fonctionnement de l'agent.
 * Sans ce repère, un client peut croire son installation terminée et ne
 * comprendre qu'au premier message reçu qu'il manquait l'essentiel.
 */
class SetupChecklist
{
    /**
     * @return array<string, array{done: bool, required: bool, hint: string|null}>
     */
    public function forCurrentTenant(): array
    {
        $profile = BusinessProfile::query()->first();

        // Un compte neuf a ses sept jours fermés : l'agent annoncerait une
        // fermeture permanente. C'est un manque, pas un réglage par défaut.
        $hasOpenDay = BusinessHour::query()->where('is_closed', false)->exists();

        $profileDone = $profile !== null
            && filled($profile->legal_name)
            && mb_strlen((string) $profile->description) >= 40
            && $hasOpenDay;

        $agent = Agent::query()->where('is_active', true)->first();

        $whatsapp = WhatsAppAccount::query()->first();

        return [
            'business' => [
                'done'     => $profileDone,
                'required' => true,
                'hint'     => $profileDone ? null : $this->businessHint($profile, $hasOpenDay),
            ],
            'agent' => [
                'done'     => $agent !== null,
                'required' => true,
                'hint'     => $agent !== null ? null : 'Aucun agent actif : personne ne répond aux messages.',
            ],
            'whatsapp' => [
                'done'     => $whatsapp?->status->canSend() ?? false,
                'required' => true,
                'hint'     => ($whatsapp?->status->canSend() ?? false)
                    ? null
                    : 'Numéro non connecté : aucun message ne peut être reçu ni envoyé.',
            ],
            // Facultatifs, mais ce sont eux qui rendent les réponses utiles
            // plutôt que génériques.
            'services' => [
                'done'     => Service::query()->exists(),
                'required' => false,
                'hint'     => null,
            ],
            'faqs' => [
                'done'     => Faq::query()->exists(),
                'required' => false,
                'hint'     => null,
            ],
        ];
    }

    private function businessHint(?BusinessProfile $profile, bool $hasOpenDay): string
    {
        return match (true) {
            $profile === null || blank($profile->legal_name) => 'Raison sociale manquante.',
            mb_strlen((string) $profile->description) < 40   => 'Décrivez votre activité en quelques phrases.',
            ! $hasOpenDay                                     => 'Aucun jour ouvert : l\'agent vous annoncera fermé en permanence.',
            default                                           => 'Configuration incomplète.',
        };
    }
}
