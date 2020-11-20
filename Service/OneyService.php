<?php

namespace PayPlugOney\Service;

use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Payplug\OneySimulation;
use PayPlugModule\Event\PayPlugPaymentEvent;
use PayPlugModule\Service\PaymentService;
use PayPlugOney\Event\OneyPaymentEvent;
use PayPlugOney\PayPlugOney;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;

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

    public function isValidOneyAddress(Address $address)
    {
        $cellphoneNumber = $address->getCellphone();
        if ($cellphoneNumber === null) {
            throw new \Exception(
                Translator::getInstance()->trans('Cellphone number is missing.', [], PayPlugOney::DOMAIN_NAME)
            );
        }

        $phoneUtil = PhoneNumberUtil::getInstance();
        $parsedNumber = $phoneUtil->parse($cellphoneNumber, $address->getCountry()->getIsoalpha2());
        $numberType = $phoneUtil->getNumberType($parsedNumber);
        if (PhoneNumberType::MOBILE !== $numberType && PhoneNumberType::FIXED_LINE_OR_MOBILE !== $numberType) {
            throw new \Exception(
                Translator::getInstance()->trans('Cellphone number is invalid.', [], PayPlugOney::DOMAIN_NAME)
            );
        }
    }
}