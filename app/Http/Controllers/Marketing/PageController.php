<?php

declare(strict_types=1);

namespace App\Http\Controllers\Marketing;

use App\Models\Plan;
use Inertia\Inertia;
use Inertia\Response;

class PageController
{
    public function home(): Response
    {
        return Inertia::render('Marketing/Home', [
            'appUrl' => 'https://'.config('nonalix.domains.app'),
        ]);
    }

    public function pricing(): Response
    {
        return Inertia::render('Marketing/Pricing', [
            // Les tarifs affichés viennent de la table `plans` : le site
            // commercial ne peut pas dériver de ce qui est réellement facturé.
            'plans' => Plan::query()->public()->get([
                'id', 'name', 'slug', 'description', 'price_cents',
                'currency', 'interval', 'quotas', 'features',
            ]),
        ]);
    }

    public function legal(): Response
    {
        return Inertia::render('Marketing/Legal');
    }

    public function privacy(): Response
    {
        return Inertia::render('Marketing/Privacy');
    }

    public function terms(): Response
    {
        return Inertia::render('Marketing/Terms');
    }
}
