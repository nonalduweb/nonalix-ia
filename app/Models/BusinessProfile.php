<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Identité de l'entreprise, injectée dans le prompt de l'agent.
 */
class BusinessProfile extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    protected $fillable = [
        'legal_name', 'description', 'industry', 'website', 'email', 'phone',
        'address_line1', 'address_line2', 'postal_code', 'city', 'country',
        'timezone', 'currency', 'languages',
    ];

    protected function casts(): array
    {
        return ['languages' => 'array'];
    }

    protected $attributes = [
        'languages' => '["fr"]',
    ];

    public function formattedAddress(): ?string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            trim(($this->postal_code ?? '').' '.($this->city ?? '')),
            $this->country,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }
}
