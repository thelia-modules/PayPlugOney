<?php

namespace PayPlugOney\Hook;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyService;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\AddressQuery;
use Thelia\Model\CartItem;
use Thelia\TaxEngine\TaxEngine;

class FrontHook extends BaseHook
{
    /** @var TaxEngine  */
    protected $taxEngine;

    /** @var OneyService  */
    protected $oneyService;

    public function __construct(TaxEngine $taxEngine, OneyService $oneyService)
    {
        $this->taxEngine = $taxEngine;
        $this->oneyService = $oneyService;
    }

    public function addOneyStylesheet(HookRenderEvent $event)
    {
        if (!PayPlugOney::isPaymentEnabled()) {
            return false;
        }

        $event->add(
            $this->render('/PayPlugOney/hook/oneyStyleSheet.html')
        );
    }

    public function addBaseSimulationJs(HookRenderEvent $event)
    {
        if (!PayPlugOney::isPaymentEnabled()) {
            return false;
        }

        $event->add(
            $this->render('/PayPlugOney/hook/baseSimulationJs.html')
        );
    }

    public function addProductOneySimulation(HookRenderEvent $event)
    {
        if (!PayPlugOney::isPaymentEnabled()) {
            return false;
        }

        $event->add(
            $this->render('/PayPlugOney/hook/productSimulation.html')
        );
    }

    public function addCartOneySimulation(HookRenderEvent $event)
    {
        if (!PayPlugOney::isPaymentEnabled()) {
            return false;
        }

        try {
            $taxCountry = $this->taxEngine->getDeliveryCountry();
            $taxState = $this->taxEngine->getDeliveryState();

            $amount = $this->getCart()->getTaxedAmount($taxCountry, true, $taxState);

            $event->add(
                $this->render('/PayPlugOney/hook/cartSimulation.html', compact('amount'))
            );
        } catch (\Exception $e)
        {
            // todo add log
        }
    }

    public function addOneyPaymentExtra(HookRenderEvent $event)
    {
        if ((int)$event->getArgument('module') !== PayPlugOney::getModuleId()) {
            return;
        }

        try {
            $oneyModuleId = PayPlugOney::getModuleId();

            $cart = $this->getCart();
            $order = $this->getOrder();

            $taxCountry = $this->taxEngine->getDeliveryCountry();
            $taxState = $this->taxEngine->getDeliveryState();

            $amount = 100 * ($cart->getTaxedAmount($taxCountry, true, $taxState) + $order->getPostage());
            $simulations = $this->oneyService->getSimulation($amount);

            $event->add(
                $this->render('/PayPlugOney/hook/paymentExtra.html', compact('oneyModuleId', 'amount', 'simulations'))
            );
        } catch (\Exception $e)
        {
            // todo add log
        }
    }
}