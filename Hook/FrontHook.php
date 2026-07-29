<?php

declare(strict_types=1);

namespace PayPlugOney\Hook;

use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyCartAmountResolver;
use PayPlugOney\Service\OneySimulationPresenter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Log\Tlog;

/**
 * Thelia 2 Smarty front-office rendering.
 *
 * Thelia 3 (Flexy) has no front Smarty hooks: the instalment selector comes from the
 * payment module options API (PayPlugOney\EventListener\PaymentOptionListener) and the
 * simulation pop-in from the PayPlugOney:OneySimulation LiveComponent. Every method here
 * therefore no-ops on Thelia 3.
 *
 * The tax engine is no longer injected directly — its FQCN differs between versions — and
 * the amount computation, previously duplicated across two methods here and the payment
 * option listener, now lives in OneyCartAmountResolver.
 */
class FrontHook extends BaseHook
{
    public function __construct(
        private readonly OneyCartAmountResolver $cartAmountResolver,
        private readonly OneySimulationPresenter $simulationPresenter,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function addOneyStylesheet(HookRenderEvent $event): void
    {
        if (!$this->isOneyOffered()) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook/oneyStyleSheet.html'));
    }

    public function addBaseSimulationJs(HookRenderEvent $event): void
    {
        if (!$this->isOneyOffered()) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook/baseSimulationJs.html'));
    }

    public function addProductOneySimulation(HookRenderEvent $event): void
    {
        if (!$this->isOneyOffered()) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook/productSimulation.html'));
    }

    public function addCartOneySimulation(HookRenderEvent $event): void
    {
        if (!$this->isOneyOffered()) {
            return;
        }

        try {
            // Euros, postage excluded: the template adds postage in JavaScript, using the
            // value from the Smarty {postage} loop.
            $amount = $this->cartAmountResolver->getTaxedCartAmountInCents($this->getCart()) / 100;

            $event->add($this->render('/PayPlugOney/hook/cartSimulation.html', ['amount' => $amount]));
        } catch (\Exception $exception) {
            $this->logHookFailure('cart simulation', $exception);
        }
    }

    public function addOneyPaymentExtra(HookRenderEvent $event): void
    {
        if (!$this->isThelia2()) {
            return;
        }

        if ((int) $event->getArgument('module') !== PayPlugOney::getModuleId()) {
            return;
        }

        try {
            $amount = $this->cartAmountResolver->getTaxedTotalInCents(
                $this->getCart(),
                $this->getOrder()
            );

            $event->add(
                $this->render('/PayPlugOney/hook/paymentExtra.html', [
                    'oneyModuleId' => PayPlugOney::getModuleId(),
                    'amount' => $amount,
                    'options' => $this->simulationPresenter->getOptions($amount),
                ])
            );
        } catch (\Exception $exception) {
            $this->logHookFailure('payment extra', $exception);
        }
    }

    public function addOneyTermsAndConditionOnContent(HookRenderEvent $event): void
    {
        if (!$this->isThelia2()) {
            return;
        }

        $termsAndConditionsContentId = (int) PayPlugOney::getConfigValue(
            PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID
        );

        if (0 === $termsAndConditionsContentId) {
            return;
        }

        if ((int) $event->getArgument('content') !== $termsAndConditionsContentId) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook/termsAndConditions.html'));
    }

    public function addOneyTermsAndConditionOnModuleHook(HookRenderEvent $event): void
    {
        if (!$this->isThelia2()) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook/termsAndConditions.html'));
    }

    /**
     * Every method here is Thelia 2 only: on Thelia 3 the front-office is served by the
     * payment option API and the LiveComponent instead.
     */
    private function isThelia2(): bool
    {
        return !PayPlugOney::isThelia3();
    }

    /**
     * Separate from isThelia2() on purpose: only the promotional surfaces (stylesheet,
     * simulation buttons) are hidden when Oney is disabled. The payment-extra block and the
     * terms and conditions deliberately do NOT check this — behaviour inherited from Thelia 2
     * and preserved as-is.
     */
    private function isOneyOffered(): bool
    {
        return $this->isThelia2() && PayPlugOney::isPaymentEnabled();
    }

    /**
     * The previous implementation swallowed these exceptions with a "todo add log" comment,
     * which hid both simulation failures and PayPlug API errors.
     */
    private function logHookFailure(string $context, \Exception $exception): void
    {
        Tlog::getInstance()->addError(
            \sprintf('PayPlugOney: %s hook failed: %s', $context, $exception->getMessage())
        );
    }
}
