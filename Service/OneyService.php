<?php

namespace PayPlugOney\Service;

use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Payplug\OneySimulation;
use PayPlugModule\Event\PayPlugPaymentEvent;
use PayPlugModule\Service\PaymentService;
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

    public function sendOneyPayment($order, $oneyType, ?float $orderTotalAmount = null)
    {
        $oneyPaymentEvent = (new PayPlugPaymentEvent())
            ->buildFromOrder($order, $orderTotalAmount)
            ->setCapture(true)
            ->setAutoCapture(true)
            ->setPaymentMethod($oneyType)
        ;

        if (null == $oneyPaymentEvent->getShippingCompany()) {
            $oneyPaymentEvent->setShippingCompany($oneyPaymentEvent->getShippingFirstName(). " " .$oneyPaymentEvent->getShippingLastName());
        }

        $this->dispatcher->dispatch($oneyPaymentEvent,PayPlugPaymentEvent::ORDER_PAYMENT_EVENT);

        return [
            'id' => $oneyPaymentEvent->getPaymentId(),
            'url' => $oneyPaymentEvent->getPaymentUrl(),
        ];
    }

    public function isValidOneyAddress(Address $address)
    {
        if (!in_array($address->getCountry()->getIsoalpha2(), ['FR', 'IT'])) {
            throw new \Exception(
                Translator::getInstance()->trans('Oney is not available in %country.', ['%country' => $address->getCountry()->getTitle()], PayPlugOney::DOMAIN_NAME)
            );
        }
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