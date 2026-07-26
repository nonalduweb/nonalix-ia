<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidPrimaryKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preuve de consentement, insert-only.
 *
 * En cas de réclamation ou de contrôle, c'est cette table qui atteste qu'un
 * contact avait accepté — ou refusé — d'être démarché. Elle ne se modifie pas.
 */
class ConsentLog extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use HasUuidPrimaryKey;

    public $timestamps = false;

    protected $fillable = [
        'contact_id', 'action', 'channel', 'source', 'raw_message',
        'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
