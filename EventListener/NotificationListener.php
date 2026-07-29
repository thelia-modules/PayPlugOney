<?php

declare(strict_types=1);

namespace PayPlugOney\EventListener;

use PayPlugModule\Event\Notification\PaymentNotificationEvent;
use PayPlugModule\Model\OrderPayPlugDataQuery;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OrderStatusService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Reflects PayPlug payment notifications onto the Oney order status.
 *
 * Note: this listener deliberately does not subscribe to
 * RefundNotificationEvent::REFUND_NOTIFICATION_EVENT. It used to, pointing at a
 * 'handleRefundNotification' method that does not exist in this class, which made every
 * refund notification fail. Deciding which status a partial refund should produce is a
 * business rule, not a migration concern, so the broken subscription was removed rather
 * than given an invented implementation.
 */
class NotificationListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly OrderStatusService $orderStatusService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentNotificationEvent::PAYMENT_NOTIFICATION_EVENT => ['handlePaymentNotification', 128],
        ];
    }

    public function handlePaymentNotification(PaymentNotificationEvent $event): void
    {
        $paymentResource = $event->getResource();

        // The PayPlug SDK exposes API fields as dynamic properties, which static analysis
        // cannot see on Payplug\Resource\Payment.
        /** @var string|null $transactionRef */
        $transactionRef = $paymentResource->id; // @phpstan-ignore property.notFound
        if (empty($transactionRef)) {
            return;
        }

        $order = OrderQuery::create()
            ->filterByPaymentModuleId(PayPlugOney::getModuleId())
            ->filterByTransactionRef($transactionRef)
            ->findOne();

        if (null === $order) {
            return;
        }

        if (null === OrderPayPlugDataQuery::create()->findOneById($order->getId())) {
            return;
        }

        $orderStatus = $this->resolveOrderStatus($paymentResource);
        if (null === $orderStatus) {
            return;
        }

        $this->dispatcher->dispatch(
            (new OrderEvent($order))->setStatus($orderStatus->getId()),
            TheliaEvents::ORDER_UPDATE_STATUS
        );
    }

    private function resolveOrderStatus(object $paymentResource): ?OrderStatus
    {
        if (true === $paymentResource->is_paid) {
            return OrderStatusQuery::getPaidStatus();
        }

        if (null !== $paymentResource->failure) {
            return OrderStatusQuery::getCancelledStatus();
        }

        // Oney authorises before confirming: the payment method reports "pending" while
        // Oney reviews the file. Guard on the key's presence — PayPlug omits it entirely
        // for non-Oney payment methods, and reading it blindly emits a warning.
        if (!empty($paymentResource->payment_method['is_pending'])) {
            return $this->orderStatusService->findOrCreateOneyAuthorizationPendingOrderStatus();
        }

        return null;
    }
}
