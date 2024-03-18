<?php

namespace PayPlugOney\EventListener;

use PayPlugModule\Event\Notification\PaymentNotificationEvent;
use PayPlugModule\Event\Notification\RefundNotificationEvent;
use PayPlugModule\Model\OrderPayPlugDataQuery;
use PayPlugModule\Model\OrderPayPlugMultiPaymentQuery;
use PayPlugModule\PayPlugModule;
use PayPlugOney\Service\OrderStatusService;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatusQuery;

class NotificationListener implements EventSubscriberInterface
{
    /** @var OrderStatusService  */
    protected $orderStatusService;

    /** @var EventDispatcherInterface */
    protected $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher, OrderStatusService $orderStatusService)
    {
        $this->dispatcher = $dispatcher;
        $this->orderStatusService = $orderStatusService;
    }

    public function handlePaymentNotification(PaymentNotificationEvent $event)
    {
        $transactionRef = $event->getResource()->id;
        if (!$transactionRef) {
            return null;
        }

        $order = OrderQuery::create()
            ->filterByPaymentModuleId(PayPlugOney::getModuleId())
            ->filterByTransactionRef($transactionRef)
            ->findOne();

        if (null === $order) {
            return;
        }

        $orderPayPlugData = OrderPayPlugDataQuery::create()
            ->findOneById($order->getId());

        if (null === $orderPayPlugData) {
            return;
        }

        $paymentResource = $event->getResource();

        $orderStatus = $this->getOrderStatusFromPaymentResource($paymentResource);

        if (null !== $orderStatus) {
            $event = (new OrderEvent($order))
                ->setStatus($orderStatus->getId());

            $this->dispatcher->dispatch($event, TheliaEvents::ORDER_UPDATE_STATUS);
        }
    }

    /**
     * @param $paymentResource
     * @return \Thelia\Model\OrderStatus
     */
    protected function getOrderStatusFromPaymentResource($paymentResource)
    {
        if (null !== $paymentResource->failure) {
            return OrderStatusQuery::getCancelledStatus();
        }

        if (null !== $paymentResource->authorization->authorized_at) {
            return OrderStatusQuery::getPaidStatus();
        }

        if (null !== $paymentResource->payment_method['is_pending']) {
            return $this->orderStatusService->findOrCreateOneyAuthorizationPendingOrderStatus();
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [
            PaymentNotificationEvent::PAYMENT_NOTIFICATION_EVENT => ['handlePaymentNotification', 128],
            RefundNotificationEvent::REFUND_NOTIFICATION_EVENT => ['handleRefundNotification', 128]
        ];
    }
}