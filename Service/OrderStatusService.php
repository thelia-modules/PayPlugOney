<?php

namespace PayPlugOney\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

class OrderStatusService
{
    const ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE = "oney_authorization_pending";

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    public function initAllStatuses()
    {
        $this->findOrCreateOneyAuthorizationPendingOrderStatus();
    }

    /**
     * @return OrderStatus
     */
    public function findOrCreateOneyAuthorizationPendingOrderStatus()
    {
        $oneyAuthorizationPendingOrderStatus = OrderStatusQuery::create()
            ->findOneByCode($this::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE);

        if (null !== $oneyAuthorizationPendingOrderStatus) {
            return $oneyAuthorizationPendingOrderStatus;
        }

        $oneyAuthorizationPendingOrderStatusEvent = (new OrderStatusCreateEvent())
            ->setCode(self::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE)
            ->setColor("#7e9b85")
            ->setLocale('en_US')
            ->setTitle('Oney authorization pending');

        $this->dispatcher->dispatch(TheliaEvents::ORDER_STATUS_CREATE, $oneyAuthorizationPendingOrderStatusEvent);

        $updateEvent =  (new OrderStatusUpdateEvent($oneyAuthorizationPendingOrderStatusEvent->getOrderStatus()->getId()))
            ->setCode(self::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE)
            ->setColor("#7e9b85")
            ->setLocale('fr_FR')
            ->setTitle('Autorisation oney en cours');

        $this->dispatcher->dispatch(TheliaEvents::ORDER_STATUS_UPDATE, $updateEvent);

        return $oneyAuthorizationPendingOrderStatusEvent->getOrderStatus();
    }
}