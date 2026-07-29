<?php

declare(strict_types=1);

namespace PayPlugOney\Service\Dto;

/**
 * One Oney instalment plan (3x or 4x), normalised from the PayPlug simulation payload.
 *
 * Template-engine agnostic on purpose: the same object feeds the Smarty templates on
 * Thelia 2, the Twig LiveComponent on Thelia 3, and both payment-option listeners.
 *
 * Every monetary value is in cents, as returned by the PayPlug API.
 *
 * `final class` with promoted readonly properties rather than `final readonly class`:
 * the latter requires PHP 8.2, and this module must still load on Thelia 2 installs
 * running PHP 8.1.
 */
final class OneySimulationOption
{
    /**
     * @param array<int, int> $installmentAmountsInCents
     */
    public function __construct(
        public readonly string $code,
        public readonly int $installmentCount,
        public readonly int $downPaymentAmountInCents,
        public readonly int $totalCostInCents,
        public readonly float $effectiveAnnualPercentageRate,
        public readonly array $installmentAmountsInCents,
        public readonly int $orderAmountInCents,
    ) {
    }

    /**
     * What the customer ends up paying: order amount plus financing cost.
     */
    public function getTotalAmountInCents(): int
    {
        return $this->orderAmountInCents + $this->totalCostInCents;
    }
}
