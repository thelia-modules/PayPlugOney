<?php

declare(strict_types=1);

namespace PayPlugOney\Twig\Components;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\Dto\OneySimulationOption;
use PayPlugOney\Service\OneyEligibilityChecker;
use PayPlugOney\Service\OneySimulationHtmlRenderer;
use PayPlugOney\Service\OneySimulationPresenter;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Oney instalment simulation pop-in for the Flexy front-office.
 *
 * Performance note: the PayPlug simulation endpoint is a paid third-party HTTP call, so it is
 * only invoked once the customer actually opens the pop-in, and the result is memoised for
 * the current render. Switching between the 3x and 4x tabs is handled client-side and does
 * not round-trip to the server.
 */
#[AsLiveComponent(
    name: 'PayPlugOney:OneySimulation',
    template: '@PayPlugOneyModule/components/OneySimulation.html.twig'
)]
class OneySimulation
{
    use DefaultActionTrait;

    /**
     * Amount to simulate, in cents. Scalar on purpose: LiveProps must not carry Propel
     * objects.
     */
    #[LiveProp]
    public int $amountInCents = 0;

    #[LiveProp(writable: true)]
    public bool $open = false;

    private ?bool $memoisedEligibility = null;

    public function __construct(
        private readonly OneySimulationPresenter $simulationPresenter,
        private readonly OneySimulationHtmlRenderer $htmlRenderer,
    ) {
    }

    /**
     * Instalment breakdown for one plan. Reuses the very same renderer that produces the
     * payment option descriptions, so the markup exists in a single place.
     */
    public function renderDetails(OneySimulationOption $option): string
    {
        return $this->htmlRenderer->render($option);
    }

    /**
     * Opening is a server action because the instalment plans have to be fetched. Closing is
     * NOT: the markup is already in the DOM, so it is handled client-side by the template's
     * script, saving one HTTP round trip and one server render per close.
     */
    #[LiveAction]
    public function openSimulation(): void
    {
        $this->open = true;
    }

    /**
     * Whether the button should be offered at all — mirrors the legacy JS
     * checkSimulation() call, without the extra HTTP round-trip.
     *
     * Memoised because isPaymentEnabled() hits module_config, and the template reads this
     * both directly and through getOptions().
     */
    public function isEligible(): bool
    {
        return $this->memoisedEligibility ??= PayPlugOney::isPaymentEnabled()
            && OneyEligibilityChecker::isAmountEligible($this->amountInCents);
    }

    /**
     * @return array<int, OneySimulationOption>
     */
    public function getOptions(): array
    {
        if (!$this->open || !$this->isEligible()) {
            return [];
        }

        // The presenter memoises per amount and caches across requests, so calling it here is
        // cheap even if the template reads it more than once.
        return $this->simulationPresenter->getOptions($this->amountInCents);
    }

    public function getLegalNotice(): string
    {
        return $this->simulationPresenter->getLegalNotice();
    }

    public function getOptionTitle(OneySimulationOption $option): string
    {
        return $this->simulationPresenter->getOptionTitle($option);
    }

    public function getMinimumAmountInCents(): int
    {
        return OneyEligibilityChecker::MINIMUM_AMOUNT_IN_CENTS;
    }

    public function getMaximumAmountInCents(): int
    {
        return OneyEligibilityChecker::MAXIMUM_AMOUNT_IN_CENTS;
    }
}
