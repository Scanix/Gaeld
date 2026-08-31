<?php

namespace App\Domains\Api\Exceptions;

use App\Support\Exceptions\DomainException;

class ApiIdempotencyConflictException extends DomainException {}
