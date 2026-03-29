<?php

namespace App\Support\Exceptions;

/**
 * Thrown when attempting to use a feature that is not enabled for the current plan.
 */
class FeatureDisabledException extends DomainException
{
    public function __construct(string $featureName)
    {
        parent::__construct("Feature [{$featureName}] is not enabled.");
    }
}
