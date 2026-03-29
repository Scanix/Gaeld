<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

/**
 * Base class for all domain exceptions in this application.
 *
 * Extend this class to create domain-specific exceptions that can be
 * caught at a single catch point: catch (DomainException $e).
 */
class DomainException extends \DomainException {}
