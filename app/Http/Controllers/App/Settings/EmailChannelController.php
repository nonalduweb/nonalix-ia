<?php

declare(strict_types=1);

namespace App\Http\Controllers\App\Settings;

use App\Mail\ForwardProbeMail;
use App\Models\BusinessProfile;
use App\Services\Audit\AuditLogger;
use App\Services\Email\MailProviderDetector;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Raccordement du canal e-mail d'une entreprise.
 *
 * On ne peut pas recevoir le courrier adressé à un domaine qu'on ne contrôle
 * pas : le client doit poser une redirection chez SON fournisseur, et cette
 * étape-là ne s'automatise pas. Tout le reste, si — l'adresse de destination
 * est frappée d'avance, le fournisseur est reconnu d'après les enregistrements
 * MX du domaine pour afficher son chemin de clics à lui, et la mise en service
 * se constate d'elle-même à l'arrivée du message sonde.
 */
class EmailChannelController
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly MailProviderDetector $detector,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(Request $request): Response
    {
        abort_unless($request->user()->can('settings.view'), 403);

        $tenant  = $this->context->currentOrFail();
        $profile = BusinessProfile::query()->first();
        $address = $profile?->email;

        return Inertia::render('Settings/EmailChannel', [
            // Frappée à l'inscription ; les entreprises antérieures à la
            // migration 000030 en reçoivent une ici, à la première visite.
            'inboundAddress'  => $tenant->inboundEmailAddress() ?? $this->mintAddress($tenant),
            'businessEmail'   => $address,
            'verifiedAt'      => $tenant->email_forward_verified_at?->toIso8601String(),
            'probeSentAt'     => $tenant->email_probe_sent_at?->toIso8601String(),
            'provider'        => $address ? $this->detector->detect($address) : null,
        ]);
    }

    /** Envoie le message sonde à l'adresse déclarée par l'entreprise. */
    public function probe(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('settings.update'), 403);

        $tenant  = $this->context->currentOrFail();
        $profile = BusinessProfile::query()->first();

        if ($profile?->email === null) {
            return back()->withErrors([
                'email' => 'Renseignez d\'abord l\'adresse e-mail de votre entreprise dans Configuration › Entreprise.',
            ]);
        }

        $code = Str::upper(Str::random(10));

        $tenant->forceFill([
            'email_probe_code'          => $code,
            'email_probe_sent_at'       => now(),
            // Une nouvelle sonde remet le canal en attente : si la redirection
            // a été retirée, l'indicateur ne doit pas rester au vert.
            'email_forward_verified_at' => null,
        ])->save();

        try {
            Mail::to($profile->email)->send(new ForwardProbeMail($code, $tenant->name));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'email' => 'Le message de vérification n\'a pas pu être envoyé. Réessayez dans un instant.',
            ]);
        }

        $this->audit->log('tenant.email_probe_sent', $tenant, [
            'after' => ['email' => $profile->email],
        ]);

        return back()->with(
            'success',
            'Message de vérification envoyé à '.$profile->email.'. Il apparaîtra comme vérifié dès qu\'il nous sera revenu.',
        );
    }

    private function mintAddress($tenant): string
    {
        $tenant->ensureInboundEmailToken();

        return $tenant->inboundEmailAddress();
    }
}
