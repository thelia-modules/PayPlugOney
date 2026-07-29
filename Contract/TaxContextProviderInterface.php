<?php

declare(strict_types=1);

namespace PayPlugOney\Contract;

use Thelia\Model\Country;
use Thelia\Model\State;

/**
 * Structural contract for Thelia's tax engine.
 *
 * DELIBERATELY implemented by nobody. The concrete class is Thelia\TaxEngine\TaxEngine on
 * Thelia 2 and Thelia\Domain\Taxation\TaxEngine\TaxEngine on Thelia 3, and that class
 * implements no interface, so no native type-hint can cover both versions.
 *
 * Used purely as a PHPDoc type, so static analysis can check the calls in
 * OneyCartAmountResolver — the module's only point of contact with the tax engine — while
 * the runtime dependency stays injected by service id.
 */
interface TaxContextProviderInterface
{
    public function getDeliveryCountry(): Country;

    public function getDeliveryState(): ?State;
}
