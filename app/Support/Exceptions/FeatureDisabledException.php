<?php

namespace App\Support\Exceptions;

class FeatureDisabledException extends DomainException
{
    public function __construct(string $featureName)
    {
        parent::__construct("Feature [{$featureName}] is not enabled.");
    }
}
