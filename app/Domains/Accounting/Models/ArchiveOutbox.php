<?php

namespace App\Domains\Accounting\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ArchiveOutbox extends Model
{
    use HasUuids;

    protected $table = 'archive_outbox';

    protected $guarded = [];

    protected $attributes = ['attempts' => 0];

    protected function casts(): array
    {
        return ['available_at' => 'datetime', 'dispatched_at' => 'datetime', 'completed_at' => 'datetime', 'attempts' => 'integer'];
    }
}
