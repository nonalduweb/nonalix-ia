<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use RuntimeException;

/**
 * Source de vérité unique du tenant courant.
 *
 * Enregistré en singleton. Alimenté par `ResolveTenant` pour les requêtes HTTP
 * et explicitement par chaque job au démarrage de son `handle()` — un worker
 * ne partage aucun état avec la requête qui a créé le job.
 *
 * Le contexte n'est JAMAIS déduit d'une donnée fournie par l'utilisateur
 * (paramètre d'URL, en-tête, corps de requête) hors du cas des webhooks, où
 * l'identifiant d'URL est confirmé par la vérification de signature.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    /**
     * Désactive temporairement le cloisonnement.
     *
     * Réservé aux commandes d'administration et aux jobs de maintenance qui
     * travaillent sur l'ensemble des tenants. Toujours accompagné d'une trace
     * d'audit côté appelant.
     */
    private bool $scopeDisabled = false;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function forget(): void
    {
        $this->tenant = null;
        $this->scopeDisabled = false;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }

    /**
     * Tenant courant ou exception.
     *
     * À utiliser partout où l'absence de tenant traduit un bug : mieux vaut
     * une erreur bruyante qu'une requête silencieusement non filtrée.
     */
    public function currentOrFail(): Tenant
    {
        return $this->tenant ?? throw new RuntimeException(
            'Aucun tenant en contexte. Utiliser TenantContext::set() ou '
            .'runWithout() si l\'accès transverse est intentionnel.'
        );
    }

    public function scopeIsDisabled(): bool
    {
        return $this->scopeDisabled;
    }

    /**
     * Exécute un traitement dans le contexte d'un tenant donné, puis restaure
     * l'état précédent — y compris en cas d'exception.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runAs(Tenant $tenant, callable $callback): mixed
    {
        $previous        = $this->tenant;
        $previousDisabled = $this->scopeDisabled;

        $this->tenant        = $tenant;
        $this->scopeDisabled = false;

        try {
            return $callback();
        } finally {
            $this->tenant        = $previous;
            $this->scopeDisabled = $previousDisabled;
        }
    }

    /**
     * Exécute un traitement sans cloisonnement de tenant.
     *
     * Danger : toute requête Eloquent exécutée dans ce bloc voit l'intégralité
     * des données de tous les clients. À n'employer que pour l'administration
     * et les traitements de maintenance, jamais dans un flux utilisateur.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runWithout(callable $callback): mixed
    {
        $previous = $this->scopeDisabled;
        $this->scopeDisabled = true;

        try {
            return $callback();
        } finally {
            $this->scopeDisabled = $previous;
        }
    }
}
