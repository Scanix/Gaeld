<?php

namespace App\Domains\Api\Support;

use App\Domains\Api\Models\ApiIdempotencyKey;

final readonly class ApiIdempotencyReservation
{
    public function __construct(
        public ApiIdempotencyKey $record,
        public bool $replay = false,
    ) {}
}
