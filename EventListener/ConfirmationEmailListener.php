<?php

declare(strict_types=1);

namespace PayPlugOney\EventListener;

use PayPlugModule\Model\PayPlugConfigValue;
use PayPlugModule\PayPlugModule;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

/**
 * Defers the order confirmation email until the Oney payment is actually settled, when
 * PayPlugModule is configured to do so.
 *
 * Oney authorises before confirming, so the default "email on order placed" behaviour would
 * tell the customer their order is confirmed while Oney may still decline it.
 */
class ConfirmationEmailListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS => ['updateStatus', 128],
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => ['sendConfirmationEmail', 129],
        ];
    }

    /**
     * Stops the default confirmation email for an unpaid Oney order.
     */
    public function sendConfirmationEmail(OrderEvent $event): void
    {
        if (!self::shouldWaitForPayment()) {
            return;
        }

        $order = $event->getOrder();

        if (!$order->isPaid() && $order->getPaymentModuleId() === PayPlugOney::getModuleId()) {
            $event->stopPropagation();
        }
    }

    /**
     * Sends the confirmation email once the Oney order becomes paid.
     */
    public function updateStatus(OrderEvent $event, string $eventName, EventDispatcherInterface $dispatcher): void
    {
        $order = $event->getOrder();

        if (!$order->isPaid() || $order->getPaymentModuleId() !== PayPlugOney::getModuleId()) {
            return;
        }

        if (self::shouldWaitForPayment()) {
            $dispatcher->dispatch($event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);
        }

        Tlog::getInstance()->debug('Confirmation email sent to customer '.$order->getCustomer()->getEmail());
    }

    private static function shouldWaitForPayment(): bool
    {
        return (bool) PayPlugModule::getConfigValue(PayPlugConfigValue::SEND_CONFIRMATION_MESSAGE_ONLY_IF_PAID);
    }
}
