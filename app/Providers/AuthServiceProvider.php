<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Agent;
use App\Models\BusinessHour;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Document;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Policies\AgentPolicy;
use App\Policies\ContactPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\LeadPolicy;
use App\Policies\MessagePolicy;
use App\Policies\ServicePolicy;
use App\Policies\UserPolicy;
use App\Policies\WhatsAppAccountPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    protected $policies = [
        Agent::class           => AgentPolicy::class,
        Contact::class         => ContactPolicy::class,
        // Les FAQ suivent exactement la même politique que les prestations :
        // elles alimentent le même prompt et engagent l'entreprise de la
        // même façon. Une policy distincte serait un doublon à maintenir.
        Faq::class             => ServicePolicy::class,
        BusinessHour::class    => ServicePolicy::class,
        Conversation::class    => ConversationPolicy::class,
        Document::class        => DocumentPolicy::class,
        Lead::class            => LeadPolicy::class,
        Message::class         => MessagePolicy::class,
        Service::class         => ServicePolicy::class,
        User::class            => UserPolicy::class,
        WhatsAppAccount::class => WhatsAppAccountPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Un super-admin NONALIX passe toutes les gates de la plateforme,
        // MAIS jamais le cloisonnement de tenant : les policies vérifient
        // l'appartenance avant même de consulter les permissions, et un
        // super-admin n'a pas de tenant. Pour voir les données d'un client,
        // il doit passer par une impersonation tracée.
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() && str_starts_with($ability, 'platform.')
                ? true
                : null;
        });

        Gate::define('viewHorizon', fn (User $user) => $user->isSuperAdmin());
        Gate::define('viewPulse', fn (User $user) => $user->isSuperAdmin());
    }
}
