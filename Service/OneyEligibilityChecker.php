<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

/**
 * Single source of truth for the Oney amount range.
 *
 * Static by design: this is a pure business rule with no state and no dependency, and it
 * must be callable from places that are not wired through the DI container (the module
 * class itself, which the container instantiates outside the service graph).
 *
 * All amounts are expressed in cents, like the PayPlug API. Never pass euros here.
 */
final class OneyEligibilityChecker
{
    /**
     * For now these bounds are fixed; Oney may later allow them to be configured.
     */
    public const MINIMUM_AMOUNT_IN_CENTS = 10000;

    public const MAXIMUM_AMOUNT_IN_CENTS = 300000;

    public static function isAmountEligible(int $amountInCents): bool
    {
        return $amountInCents >= self::MINIMUM_AMOUNT_IN_CENTS
            && $amountInCents <= self::MAXIMUM_AMOUNT_IN_CENTS;
    }

    /**
     * Converts an amount in euros to cents. Rounding before the cast matters: casting a
     * float such as 100.00000001 straight to int truncates to 10000 instead of 10001.
     */
    public static function toCents(float $amountInEuros): int
    {
        return (int) round($amountInEuros * 100);
    }

    /**
     * The inverse of toCents(). Exists so no caller open-codes `/ 100` — the whole point of
     * this class is that a unit conversion happens in exactly one place.
     */
    public static function toEuros(int $amountInCents): float
    {
        return $amountInCents / 100;
    }
}
