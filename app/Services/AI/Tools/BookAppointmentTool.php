<?php

declare(strict_types=1);

namespace App\Services\AI\Tools;

use App\Data\AI\ToolDefinition;
use App\Models\Conversation;
use App\Models\Lead;

class BookAppointmentTool extends BaseN8nTool
{
    public function name(): string
    {
        return 'book_appointment';
    }

    public function definition(): ToolDefinition
    {
        return new ToolDefinition(
            name: $this->name(),
            description: "Planifie un rendez-vous avec un conseiller. À appeler dès que le contact convient d'une date et d'une heure de rendez-vous.",
            parameters: [
                'type'       => 'object',
                'properties' => [
                    'date' => [
                        'type'        => 'string',
                        'description' => 'Date du rendez-vous sous le format YYYY-MM-DD (ex: 2026-08-25).',
                    ],
                    'time' => [
                        'type'        => 'string',
                        'description' => 'Heure du rendez-vous sous le format HH:MM (ex: 14:00).',
                    ],
                    'reason' => [
                        'type'        => 'string',
                        'description' => 'Motif court du rendez-vous.',
                    ],
                ],
                'required' => ['date', 'time', 'reason'],
            ],
        );
    }

    public function execute(array $arguments, Conversation $conversation): string
    {
        $result = $this->call($arguments, $conversation);

        // Uniquement si n8n a réellement confirmé la prise de rendez-vous :
        // le tableau de bord commercial compte ces marqueurs, et les gonfler
        // avec des échecs rendrait la métrique mensongère.
        if ($result['ok']) {
            $lead = Lead::query()
                ->where('contact_id', $conversation->contact_id)
                ->open()
                ->first();

            if ($lead) {
                $qual = $lead->qualification ?? [];
                $qual['appointment_booked'] = true;
                $qual['appointment_date'] = $arguments['date'] ?? null;
                $qual['appointment_time'] = $arguments['time'] ?? null;
                $lead->update(['qualification' => $qual]);
            }
        }

        return $result['message'];
    }
}
