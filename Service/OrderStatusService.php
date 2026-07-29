<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\OrderStatus\OrderStatusCreateEvent;
use Thelia\Core\Event\OrderStatus\OrderStatusUpdateEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Model\OrderStatus;
use Thelia\Model\OrderStatusQuery;

/**
 * Owns the Oney-specific order status. Oney authorises a payment before confirming it,
 * a state core Thelia has no equivalent for.
 */
final class OrderStatusService
{
    public const ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE = 'oney_authorization_pending';

    private const STATUS_COLOR = '#7e9b85';

    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public function initAllStatuses(): void
    {
        $this->findOrCreateOneyAuthorizationPendingOrderStatus();
    }

    public function findOrCreateOneyAuthorizationPendingOrderStatus(): OrderStatus
    {
        $existingStatus = OrderStatusQuery::create()
            ->findOneByCode(self::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE);

        if (null !== $existingStatus) {
            return $existingStatus;
        }

        $createEvent = (new OrderStatusCreateEvent())
            ->setCode(self::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE)
            ->setColor(self::STATUS_COLOR)
            ->setLocale('en_US')
            ->setTitle('Oney authorization pending');

        $this->dispatcher->dispatch($createEvent, TheliaEvents::ORDER_STATUS_CREATE);

        $createdStatus = $createEvent->getOrderStatus();

        // Second pass to attach the French translation: the create event only carries one
        // locale.
        $updateEvent = (new OrderStatusUpdateEvent($createdStatus->getId()))
            ->setCode(self::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE)
            ->setColor(self::STATUS_COLOR)
            ->setLocale('fr_FR')
            ->setTitle('Autorisation oney en cours');

        $this->dispatcher->dispatch($updateEvent, TheliaEvents::ORDER_STATUS_UPDATE);

        return $createdStatus;
    }
}
