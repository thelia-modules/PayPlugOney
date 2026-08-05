<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\Dto\OneySimulationOption;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;

/**
 * Turns the raw PayPlug/Oney simulation payload into normalised, template-agnostic
 * options.
 *
 * This is the single place where Oney's business rules live (which operations to
 * simulate, how an operation code maps to a number of instalments, how an option is
 * labelled). Both payment-option listeners — the legacy OpenApi one and the native one —
 * and the front LiveComponent consume this service, so the logic exists exactly once.
 */
final class OneySimulationPresenter
{
    /**
     * Operation codes requested from the Oney simulation endpoint, mapped to the total
     * number of payments the customer makes (down payment included).
     *
     * x3_with_fees returns 2 instalments plus the down payment, hence 3.
     */
    private const OPERATION_INSTALMENT_COUNTS = [
        'x3_with_fees' => 3,
        'x4_with_fees' => 4,
    ];

    /**
     * How long a simulation is reused across requests. Oney's schedule for a given amount is
     * a fixed scale with no customer scoring, so it is stable over minutes; kept short so a
     * change of scale propagates quickly.
     */
    private const CACHE_TTL_IN_SECONDS = 300;

    private const CACHE_KEY_PREFIX = 'payplugoney.simulation.';

    /**
     * Per-request memoisation. The shared cache below already absorbs most calls, but this
     * also covers the case where the cache pool is a no-op adapter.
     *
     * @var array<int, array<int, OneySimulationOption>>
     */
    private array $memoisedOptions = [];

    /**
     * OneyService is injected lazily on purpose: PayPlugModule's PaymentService constructor
     * calls initAuth(), which throws when no PayPlug API key is configured. Instantiating it
     * eagerly would make every consumer of this presenter — including the payment option
     * listener — fail at container build time, taking the whole checkout down on an install
     * where PayPlug is not configured yet.
     */
    public function __construct(
        #[Autowire(service: 'oney_service', lazy: true)]
        private readonly OneyService $oneyService,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public static function getSupportedOperations(): array
    {
        return array_keys(self::OPERATION_INSTALMENT_COUNTS);
    }

    /**
     * @return array<int, OneySimulationOption> empty when the amount is out of Oney's
     *                                          range or the simulation call fails
     */
    public function getOptions(int $amountInCents): array
    {
        if (!OneyEligibilityChecker::isAmountEligible($amountInCents)) {
            return [];
        }

        return $this->memoisedOptions[$amountInCents] ??= $this->loadOptions($amountInCents);
    }

    /**
     * Fetches a simulation, going through the shared cache.
     *
     * Caching matters here: getSimulation() is a synchronous HTTP call to a paid third-party
     * endpoint, and it sits on the checkout's critical path. TheliaEvents::MODULE_PAYMENT_GET_OPTIONS
     * fires on every build of the payment method list — every reload of the payment step, every
     * address change, every coupon — so without a cache each of those costs one PayPlug round trip.
     *
     * @return array<int, OneySimulationOption>
     */
    private function loadOptions(int $amountInCents): array
    {
        try {
            // An exception thrown inside the callback propagates and the item is NOT stored,
            // which is what we want: a transient PayPlug outage must not be cached for the
            // whole TTL and deprive every visitor of Oney.
            return $this->cache->get(
                self::CACHE_KEY_PREFIX.$amountInCents,
                function (ItemInterface $item) use ($amountInCents): array {
                    $item->expiresAfter(self::CACHE_TTL_IN_SECONDS);

                    return $this->buildOptions(
                        $this->oneyService->getSimulation($amountInCents),
                        $amountInCents
                    );
                }
            );
        } catch (\Throwable $exception) {
            // A missing API key or an unreachable PayPlug endpoint must not break the page
            // this runs on: the payment option listener feeds the whole payment method list,
            // so throwing here would cost the customer every payment method, not just Oney.
            Tlog::getInstance()->addError(
                \sprintf('PayPlugOney: Oney simulation unavailable: %s', $exception->getMessage())
            );

            return [];
        }
    }

    /**
     * @param array<string, mixed> $simulations
     *
     * @return array<int, OneySimulationOption>
     */
    private function buildOptions(array $simulations, int $amountInCents): array
    {
        $options = [];

        foreach ($simulations as $operation => $simulation) {
            $option = $this->buildOption((string) $operation, $simulation, $amountInCents);
            if (null !== $option) {
                $options[] = $option;
            }
        }

        return $options;
    }

    /**
     * Label of the group the customer picks an instalment plan from.
     */
    public function getGroupTitle(): string
    {
        return Translator::getInstance()->trans(
            'Choose the number of payments',
            [],
            PayPlugOney::DOMAIN_NAME
        );
    }

    /**
     * Human-readable label for an option, e.g. "Pay in 3 times".
     */
    public function getOptionTitle(OneySimulationOption $option): string
    {
        return Translator::getInstance()->trans(
            'Pay in %count times',
            ['%count' => $option->installmentCount],
            PayPlugOney::DOMAIN_NAME
        );
    }

    /**
     * The mandatory legal notice Oney requires next to any instalment offer.
     */
    public function getLegalNotice(): string
    {
        return Translator::getInstance()->trans(
            'Financing offer with compulsory contribution, reserved for private individuals and valid for any purchase from € 100.00 to € 3,000.00. Subject to acceptance by Oney Bank. You have 14 days to cancel your credit. Oney Bank - SA au capital de 51 286 585€ - 34 Avenue de Flandre 59170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07 023 261 www.orias.fr Correspondence: CS 60 006 - 59895 Lille Cedex - www.oney.fr',
            [],
            PayPlugOney::DOMAIN_NAME
        );
    }

    /**
     * @param mixed $simulation raw payload for one operation, as returned by the API
     */
    private function buildOption(string $operation, mixed $simulation, int $amountInCents): ?OneySimulationOption
    {
        $instalmentCount = self::OPERATION_INSTALMENT_COUNTS[$operation] ?? null;
        if (null === $instalmentCount || !\is_array($simulation)) {
            // Unknown operation, or a payload shape the API changed under us: skip it
            // rather than render a broken plan.
            return null;
        }

        $instalments = [];
        foreach ($simulation['installments'] ?? [] as $instalment) {
            if (isset($instalment['amount'])) {
                $instalments[] = (int) $instalment['amount'];
            }
        }

        return new OneySimulationOption(
            code: $operation,
            installmentCount: $instalmentCount,
            downPaymentAmountInCents: (int) ($simulation['down_payment_amount'] ?? 0),
            totalCostInCents: (int) ($simulation['total_cost'] ?? 0),
            effectiveAnnualPercentageRate: (float) ($simulation['effective_annual_percentage_rate'] ?? 0),
            installmentAmountsInCents: $instalments,
            orderAmountInCents: $amountInCents,
        );
    }
}
