<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Horizon::night();
    }

    /**
     * Le tableau de bord Horizon expose la charge utile des jobs, donc des
     * identifiants de tenants et des contenus de messages : réservé à
     * l'équipe NONALIX, sans exception.
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', fn (?User $user) => $user?->isSuperAdmin() === true);
    }
}
