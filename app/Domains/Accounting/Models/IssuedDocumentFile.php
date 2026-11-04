<?php

namespace App\Domains\Accounting\Models;

use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IssuedDocumentFile extends Model
{
    use BelongsToOrganization, HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Issued document evidence is immutable.'));
        static::deleting(fn () => throw new \LogicException('Issued document evidence requires authorized retention disposal.'));
    }
}
