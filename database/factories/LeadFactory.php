<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lead> */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'contact_id'    => Contact::factory(),
            'status'        => LeadStatus::New,
            'score'         => 0,
            'qualification' => [],
            'source'        => 'whatsapp_ai',
        ];
    }

    public function qualified(int $score = 75): static
    {
        return $this->state(fn () => [
            'status'        => LeadStatus::Qualified,
            'score'         => $score,
            'qualification' => ['need' => 'Devis pour une prestation.'],
            'qualified_at'  => now(),
            'qualified_by'  => 'ai',
        ]);
    }
}
