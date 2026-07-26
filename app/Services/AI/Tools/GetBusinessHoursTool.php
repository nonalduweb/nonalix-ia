<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Contracts\AI\AgentTool;
use App\Data\AI\ToolDefinition;
use App\Models\BusinessHour;
use App\Models\BusinessProfile;
use App\Models\Conversation;
use Carbon\CarbonImmutable;

/**
 * Horaires d'ouverture et disponibilité à l'instant présent.
 *
 * Les modèles n'ont aucune notion de l'heure courante : sans cet outil, un
 * agent répond « nous sommes ouverts » à partir du tableau des horaires, sans
 * savoir qu'il est 3 h du matin.
 */
class GetBusinessHoursTool implements AgentTool
{
    public function name(): string
    {
        return 'get_business_hours';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Donne les horaires d'ouverture et indique si l'entreprise est "
                ."ouverte en ce moment. À utiliser pour toute question sur les "
                ."horaires, la disponibilité ou le moment d'un rappel.",
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        $hours = BusinessHour::query()->orderBy('day_of_week')->orderBy('opens_at')->get();

        if ($hours->isEmpty()) {
            return "Aucun horaire n'est enregistré. Ne fais aucune supposition : "
                ."propose de transférer à un conseiller.";
        }

        $timezone = BusinessProfile::query()->value('timezone') ?? 'Europe/Paris';
        $now      = CarbonImmutable::now($timezone);
        $isOpen   = BusinessHour::isOpenAt($hours, $now);

        $lines = [];

        foreach ($hours->groupBy('day_of_week') as $day => $slots) {
            $ranges = $slots
                ->filter(fn ($s) => ! $s->is_closed && $s->opens_at && $s->closes_at)
                ->map(fn ($s) => substr((string) $s->opens_at, 0, 5).'–'.substr((string) $s->closes_at, 0, 5))
                ->implode(', ');

            $lines[] = '- '.(BusinessHour::DAYS[$day] ?? '').' : '.($ranges !== '' ? $ranges : 'fermé');
        }

        return sprintf(
            "Horaires :\n%s\n\nNous sommes actuellement %s. Heure locale : %s.",
            implode("\n", $lines),
            $isOpen ? 'OUVERTS' : 'FERMÉS',
            $now->format('l d/m/Y H:i'),
        );
    }
}
