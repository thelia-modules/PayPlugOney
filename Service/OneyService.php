<?php

namespace PayPlugOney\Service;

use Payplug\OneySimulation;
use PayPlugModule\Event\PayPlugPaymentEvent;
use PayPlugModule\Service\PaymentService;
use PayPlugOney\Event\OneyPaymentEvent;

class OneyService extends PaymentService
{
    public function getSimulation($amount)
    {
        $payPlug = $this->initAuth();
        return OneySimulation::getSimulations(
            [
                'amount' => (int)$amount,
                'country' => 'FR',
                'operations' => [
                    'x3_with_fees',
                    'x4_with_fees'
                ]
            ],
            $payPlug
        );
    }

    public function sendOneyPayment($order, $oneyType)
    {
        $oneyPaymentEvent = (new PayPlugPaymentEvent())
            ->buildFromOrder($order)
            ->setCapture(true)
            ->setAutoCapture(true)
            ->setPaymentMethod($oneyType);

        if (null == $oneyPaymentEvent->getShippingCompany()) {
            $oneyPaymentEvent->setShippingCompany($oneyPaymentEvent->getShippingFirstName(). " " .$oneyPaymentEvent->getShippingLastName());
        }

        $this->dispatcher->dispatch(PayPlugPaymentEvent::ORDER_PAYMENT_EVENT, $oneyPaymentEvent);

        return [
            'id' => $oneyPaymentEvent->getPaymentId(),
            'url' => $oneyPaymentEvent->getPaymentUrl(),
        ];
    }
}