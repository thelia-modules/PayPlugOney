<?php

declare(strict_types=1);

namespace PayPlugOney\Service\Dto;

/**
 * One Oney instalment plan (3x or 4x), normalised from the PayPlug simulation payload.
 *
 * Template-engine agnostic on purpose: the same object feeds the Twig LiveComponent, the
 * HTML renderer and the payment-option listeners.
 *
 * Every monetary value is in cents, as returned by the PayPlug API.
 */
final readonly class OneySimulationOption
{
    /**
     * @param array<int, int> $installmentAmountsInCents
     */
    public function __construct(
        public string $code,
        public int $installmentCount,
        public int $downPaymentAmountInCents,
        public int $totalCostInCents,
        public float $effectiveAnnualPercentageRate,
        public array $installmentAmountsInCents,
        public int $orderAmountInCents,
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
