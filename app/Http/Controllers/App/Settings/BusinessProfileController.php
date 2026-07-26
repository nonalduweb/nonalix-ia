<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Models\BusinessHour;
use App\Models\BusinessProfile;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BusinessProfileController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit(Request $request): Response
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'admin']), 403);

        return Inertia::render('Settings/Business', [
            'profile' => BusinessProfile::query()->first(),
            'hours'   => BusinessHour::query()->orderBy('day_of_week')->orderBy('opens_at')->get(),
            'days'    => BusinessHour::DAYS,
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::EUROPE)
                + \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'admin']), 403);

        $validated = $request->validate([
            'legal_name'    => ['required', 'string', 'max:160'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'industry'      => ['nullable', 'string', 'max:80'],
            'website'       => ['nullable', 'url:http,https', 'max:255'],
            'email'         => ['nullable', 'email', 'max:190'],
            'phone'         => ['nullable', 'string', 'max:32'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'postal_code'   => ['nullable', 'string', 'max:20'],
            'city'          => ['nullable', 'string', 'max:120'],
            'country'       => ['nullable', 'string', 'size:2'],
            // Le fuseau conditionne le calcul des horaires d'ouverture :
            // une valeur invalide ferait répondre l'agent au mauvais moment.
            'timezone'      => ['required', 'timezone'],
            'currency'      => ['required', 'string', 'size:3'],
            'languages'     => ['nullable', 'array'],
            'languages.*'   => ['string', 'max:5'],
        ]);

        $profile = BusinessProfile::query()->firstOrNew([]);
        $profile->fill($validated)->save();

        $this->audit->logUpdate('business.profile_updated', $profile);

        return back()->with('success', 'Informations de l\'entreprise enregistrées.');
    }

    /**
     * Remplacement complet des horaires.
     *
     * On efface puis on réinsère : une plage supprimée côté client doit
     * disparaître en base, ce qu'un simple upsert ne garantirait pas.
     */
    public function updateHours(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyRole(['owner', 'admin']), 403);

        $validated = $request->validate([
            'hours'               => ['present', 'array', 'max:50'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.opens_at'    => ['nullable', 'date_format:H:i'],
            'hours.*.closes_at'   => ['nullable', 'date_format:H:i', 'after:hours.*.opens_at'],
            'hours.*.is_closed'   => ['boolean'],
        ]);

        DB::transaction(function () use ($validated) {
            BusinessHour::query()->delete();

            foreach ($validated['hours'] as $slot) {
                BusinessHour::create([
                    'day_of_week' => $slot['day_of_week'],
                    'opens_at'    => $slot['opens_at'] ?? null,
                    'closes_at'   => $slot['closes_at'] ?? null,
                    'is_closed'   => $slot['is_closed'] ?? false,
                ]);
            }
        });

        $this->audit->log('business.hours_updated', context: ['slots' => count($validated['hours'])]);

        return back()->with('success', 'Horaires enregistrés.');
    }
}
