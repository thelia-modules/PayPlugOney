<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use PayPlugOney\Contract\TaxContextProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Thelia\Model\Cart;
use Thelia\Model\Order;

/**
 * Computes the cart amount Oney must simulate against.
 *
 * This is the module's only point of contact with the tax engine, for two reasons:
 *
 *  - DRY: the same "taxed cart total" computation used to be duplicated in FrontHook
 *    (twice) and in the payment option listener.
 *  - Bi-compatibility: the tax engine's FQCN moved between versions
 *    (Thelia\TaxEngine\TaxEngine on Thelia 2, Thelia\Domain\Taxation\TaxEngine\TaxEngine
 *    on Thelia 3), so it cannot be injected by type. The service id 'thelia.taxEngine' is
 *    a stable public alias in both versions, hence the injection by id below.
 *
 * Every method name states its unit explicitly: the legacy code mixed euros and cents
 * between callers, which is exactly the kind of bug this class exists to prevent.
 */
final class OneyCartAmountResolver
{
    /**
     * @param TaxContextProviderInterface $taxEngine structural type only — see the interface
     */
    public function __construct(
        #[Autowire(service: 'thelia.taxEngine')]
        private readonly object $taxEngine,
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
        // Three arguments only: Thelia 3 added a fourth ($withPostage) that does not
        // exist on Thelia 2. Postage is added by the caller above instead.
        return $cart->getTaxedAmount(
            $this->taxEngine->getDeliveryCountry(),
            true,
            $this->taxEngine->getDeliveryState()
        );
    }
}
