<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use Payplug\OneySimulation;
use PayPlugModule\Event\PayPlugPaymentEvent;
use PayPlugModule\Service\PaymentService;
use PayPlugOney\Exception\InvalidAddressException;
use Thelia\Model\Address;
use Thelia\Model\Order;

/**
 * Oney-specific payment operations, layered on top of PayPlugModule's generic payment
 * service (whose $dispatcher property and initAuth() method are used here).
 */
class OneyService extends PaymentService
{
    /**
     * @return array<string, mixed> raw simulation payload keyed by operation code
     */
    public function getSimulation(int $amountInCents): array
    {
        $payPlug = $this->initAuth();

        return OneySimulation::getSimulations(
            [
                'amount' => $amountInCents,
                'country' => 'FR',
                'operations' => OneySimulationPresenter::getSupportedOperations(),
            ],
            $payPlug
        );
    }

    /**
     * @return array{id: mixed, url: mixed}
     */
    public function sendOneyPayment(Order $order, ?string $oneyType, ?float $orderTotalAmount = null): array
    {
        $oneyPaymentEvent = (new PayPlugPaymentEvent())
            ->buildFromOrder($order, $orderTotalAmount)
            ->setCapture(true)
            ->setAutoCapture(true)
            ->setPaymentMethod($oneyType);

        if (null === $oneyPaymentEvent->getShippingCompany()) {
            $oneyPaymentEvent->setShippingCompany(
                $oneyPaymentEvent->getShippingFirstName().' '.$oneyPaymentEvent->getShippingLastName()
            );
        }

        $this->dispatcher->dispatch($oneyPaymentEvent, PayPlugPaymentEvent::ORDER_PAYMENT_EVENT);

        return [
            'id' => $oneyPaymentEvent->getPaymentId(),
            'url' => $oneyPaymentEvent->getPaymentUrl(),
        ];
    }

    /**
     * Kept for backwards compatibility. The logic moved to OneyAddressValidator, which needs
     * no PayPlug credentials — prefer injecting that service directly.
     *
     * @throws InvalidAddressException when the address cannot be used with Oney
     */
    public function isValidOneyAddress(?Address $address): void
    {
        (new OneyAddressValidator())->validate($address);
    }
}
