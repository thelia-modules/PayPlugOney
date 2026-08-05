<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use Thelia\Domain\Taxation\TaxEngine\TaxEngine;
use Thelia\Model\Cart;
use Thelia\Model\Order;

/**
 * Computes the cart amount Oney must simulate against.
 *
 * This is the module's only point of contact with the tax engine, so the "taxed cart total"
 * computation exists once instead of being duplicated across the front-office surfaces and
 * the payment option listener.
 *
 * Every method name states its unit explicitly: the legacy code mixed euros and cents
 * between callers, which is exactly the kind of bug this class exists to prevent.
 */
final readonly class OneyCartAmountResolver
{
    public function __construct(
        private TaxEngine $taxEngine,
    ) {
    }

    /**
     * Taxed cart amount, postage excluded.
     */
    public function getTaxedCartAmountInCents(Cart $cart): int
    {
        return OneyEligibilityChecker::toCents($this->getTaxedCartAmountInEuros($cart));
    }

    /**
     * Taxed cart amount plus postage — the amount actually charged to the customer, and
     * therefore the one Oney simulates against.
     */
    public function getTaxedTotalInCents(Cart $cart, ?Order $order = null): int
    {
        $postage = null !== $order ? (float) $order->getPostage() : 0.0;

        return OneyEligibilityChecker::toCents($this->getTaxedCartAmountInEuros($cart) + $postage);
    }

    private function getTaxedCartAmountInEuros(Cart $cart): float
    {
        // $withPostage is left at its default: postage is added by getTaxedTotalInCents()
        // above, from the order rather than from the cart.
        return $cart->getTaxedAmount(
            $this->taxEngine->getDeliveryCountry(),
            true,
            $this->taxEngine->getDeliveryState()
        );
    }
}
