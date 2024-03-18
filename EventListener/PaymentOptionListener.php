<?php

namespace PayPlugOney\EventListener;

use OpenApi\Events\OpenApiEvents;
use OpenApi\Events\PaymentModuleOptionEvent;
use OpenApi\Model\Api\ModelFactory;
use OpenApi\Model\Api\PaymentModuleOption;
use OpenApi\Model\Api\PaymentModuleOptionGroup;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Template\TemplateHelperInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Order;
use Thelia\TaxEngine\TaxEngine;

class PaymentOptionListener implements EventSubscriberInterface
{
    public function __construct(
        private ModelFactory $modelFactory,
        private OneyService $oneyService,
        private TaxEngine $taxEngine,
        private RequestStack $requestStack,
        private ParserInterface $parser,
        private TemplateHelperInterface $templateHelper
    )
    {
    }

    public static function getSubscribedEvents()
    {
        $listenedEvents = [];

        if (class_exists(PaymentModuleOptionEvent::class)) {
            $listenedEvents[OpenApiEvents::MODULE_PAYMENT_GET_OPTIONS] = array("getPaymentModuleOptions", 128);
        }

        return $listenedEvents;
    }

    public function getPaymentModuleOptions(PaymentModuleOptionEvent $event)
    {
        if ($event->getModule()->getId() !== PayPlugOney::getModuleId()) {
            return ;
        }

        $this->addPaymentCountOptionGroup($event);
    }

    private function addPaymentCountOptionGroup(PaymentModuleOptionEvent $event)
    {
        /** @var PaymentModuleOptionGroup $paymentModuleOptionGroup */
        $paymentModuleOptionGroup = $this->modelFactory->buildModel('PaymentModuleOptionGroup');
        $paymentModuleOptionGroup
            ->setCode('pay_plug_oney_type')
            ->setTitle(Translator::getInstance()->trans('Choose the number of payments'))
            ->setDescription(Translator::getInstance()->trans('Financing offer with compulsory contribution, reserved for private individuals and valid for any purchase from € 100.00 to € 3,000.00. Subject to acceptance by Oney Bank. You have 14 days to cancel your credit. Oney Bank - SA au capital de 51 286 585€ - 34 Avenue de Flandre 59170 Croix - 546 380 197 RCS Lille Métropole - n° Orias 07 023 261 www.orias.fr Correspondence: CS 60 006 - 59895 Lille Cedex - www.oney.fr'))
            ->setMinimumSelectedOptions(1)
            ->setMaximumSelectedOptions(1);

        $taxCountry = $this->taxEngine->getDeliveryCountry();
        $taxState = $this->taxEngine->getDeliveryState();

        $order = $this->requestStack->getSession()->getOrder() ?? new Order();

        $amount = 100 * ($event->getCart()->getTaxedAmount($taxCountry, true, $taxState) + $order->getPostage());
        $simulations = $this->oneyService->getSimulation($amount);

        $this->parser->setTemplateDefinition(
            $this->templateHelper->getActiveFrontTemplate()
        );

        foreach ($simulations as $type => $simulation) {
            $count = match ($type) {
                'x3_with_fees' => 3,
                'x4_with_fees' => 4,
                default => 1
            };

            /** @var PaymentModuleOption $option */
            $option = $this->modelFactory->buildModel('PaymentModuleOption');
            $option->setTitle(Translator::getInstance()->trans('Pay in %count times', ['%count' => $count]));
            $option->setDescription(
                $this->parser->render(
                    'PayPlugOney/simulationBlock.html',
                    [
                        'type' => $type,
                        'simulation' => $simulation,
                        'amount' => $amount
                    ]
                ));

            $paymentModuleOptionGroup->appendPaymentModuleOption($option);
        }

        $event->appendPaymentModuleOptionGroups($paymentModuleOptionGroup);
    }
}