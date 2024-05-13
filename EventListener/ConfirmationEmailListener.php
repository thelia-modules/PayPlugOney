<?php

namespace PayPlugOney\EventListener;

use PayPlugModule\PayPlugModule;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;

class ConfirmationEmailListener implements EventSubscriberInterface
{
    /**
     * @param OrderEvent $event
     *
     * @throws \Exception if the message cannot be loaded.
     */
    public function sendConfirmationEmail(OrderEvent $event)
    {
        if (PayPlugModule::getConfigValue('send_confirmation_message_only_if_paid')) {
            // We send the order confirmation email only if the order is paid
            $order = $event->getOrder();

            if (!$order->isPaid() && $order->getPaymentModuleId() === PayPlugOney::getModuleId()) {
                $event->stopPropagation();
            }
        }
    }

    /*
     * Check if we are the order payment module, and if order new status is paid, send a confirmation email to the customer.
     *
     * @param OrderEvent $event
     * @param $eventName
     * @param EventDispatcherInterface $dispatcher
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function updateStatus(OrderEvent $event, $eventName, EventDispatcherInterface $dispatcher)
    {
        $order = $event->getOrder();

        if ($order->isPaid() && $order->getPaymentModuleId() === PayPlugOney::getModuleId()) {
            // Send confirmation email if required.
            if (PayPlugModule::getConfigValue('send_confirmation_message_only_if_paid')) {
                $dispatcher->dispatch($event, TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL);
            }

            Tlog::getInstance()->debug("Confirmation email sent to customer " . $order->getCustomer()->getEmail());
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            TheliaEvents::ORDER_UPDATE_STATUS           => ['updateStatus', 128],
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => ['sendConfirmationEmail', 129]
        ];
    }
}
