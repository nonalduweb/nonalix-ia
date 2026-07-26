<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Clés primaires UUID v7.
 *
 * v7 plutôt que v4 : les identifiants restent non devinables tout en étant
 * ordonnés dans le temps. Les insertions se font donc en fin d'index B-tree
 * au lieu de le fragmenter aléatoirement — sur une table `messages` qui grossit
 * en continu, la différence est mesurable.
 */
trait HasUuidPrimaryKey
{
    use HasUuids;

    public function getIncrementing(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    public function newUniqueId(): string
    {
        return (string) \Illuminate\Support\Str::uuid7();
    }
}
