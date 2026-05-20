<?php

namespace App\Support\Contracts;

/**
 * Contract for subscription objects injected by EE or other billing plugins.
 *
 * Core code must never reference EE types directly. This interface lives in
 * core; the EE Subscription model implements it. Call sites in core should
 * type-hint against this contract rather than accessing raw ->status strings
 * or relation properties.
 */
interface SubscriptionContract
{
    public function isActive(): bool;

    public function isTrialing(): bool;

    /** Returns true when the trial period has expired but the status is still 'trialing'. */
    public function isTrialExpired(): bool;

    public function isPastDue(): bool;

    public function isPaused(): bool;

    public function getStatus(): string;

    public function getTrialEndsAt(): ?\DateTimeInterface;

    public function getEndsAt(): ?\DateTimeInterface;

    /**
     * Return the associated plan object.
     *
     * Typed as mixed to avoid core → EE coupling; the concrete type is
     * an EE Plan model at runtime.
     */
    public function getPlan(): mixed;
}
