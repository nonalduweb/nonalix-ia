<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Models\AccessRequest;
use App\Models\Plan;
use App\Notifications\AccessRequestReceived;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Demande d'accès depuis le site commercial.
 *
 * L'inscription exige un code d'accès. Sans ce formulaire, un prospect
 * arrivait sur une page réclamant un code qu'il n'avait aucun moyen
 * d'obtenir : le parcours s'arrêtait là.
 */
class AccessRequestController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Marketing/AccessRequest', [
            'plans' => Plan::query()->public()->get(['id', 'name', 'slug', 'price_cents', 'currency']),
            // Pré-sélection depuis un lien « Choisir » de la page Tarifs.
            'preselectedPlan' => $request->query('plan'),
            'submitted' => $request->session()->get('access_request_submitted', false),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company'      => ['required', 'string', 'min:2', 'max:160'],
            'contact_name' => ['required', 'string', 'min:2', 'max:120'],
            'email'        => ['required', 'email:rfc,dns', 'max:190'],
            'phone'        => ['nullable', 'string', 'max:32'],
            'plan_id'      => ['nullable', 'uuid', 'exists:plans,id'],
            'message'      => ['nullable', 'string', 'max:2000'],
            // Leurre : champ invisible pour un humain, rempli par les robots
            // qui remplissent tout. Moins intrusif qu'un captcha, qui ferait
            // fuir des prospects légitimes sur une connexion lente.
            'website'      => ['prohibited'],
        ], [
            'website.prohibited' => 'Envoi refusé.',
        ]);

        // Une demande déjà en attente pour la même adresse n'en crée pas une
        // seconde : un prospect qui clique deux fois ne doit pas apparaître
        // en double dans la file de traitement.
        $existing = AccessRequest::query()
            ->pending()
            ->where('email', $validated['email'])
            ->first();

        if ($existing !== null) {
            return back()->with('success', 'Votre demande est déjà enregistrée. Nous revenons vers vous très vite.');
        }

        $accessRequest = AccessRequest::create([
            ...$validated,
            'status'     => AccessRequest::PENDING,
            'ip_address' => $request->ip(),
        ]);

        $this->audit->log('platform.access_requested', $accessRequest, [
            'after' => ['company' => $accessRequest->company, 'email' => $accessRequest->email],
        ]);

        // Envoi après enregistrement : un relais SMTP indisponible ne doit pas
        // faire perdre la demande. Elle reste visible dans l'administration.
        Notification::route('mail', config('nonalix.support_email'))
            ->notify(new AccessRequestReceived($accessRequest->load('plan')));

        return back()->with('access_request_submitted', true);
    }
}
