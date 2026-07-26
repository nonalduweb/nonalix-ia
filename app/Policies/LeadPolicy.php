<?php

declare(strict_types=1);

namespace App\Policies;

class LeadPolicy extends TenantPolicy
{
    protected function resource(): string
    {
        return 'leads';
    }
}
