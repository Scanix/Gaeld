<?php

namespace App\Domains\Accounting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ArchiveAccessEvent extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        $deny = function (): void {
            throw new \LogicException('Archive access events are append-only.');
        };
        static::updating($deny);
        static::deleting($deny);
    }
}
