<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace PayPlugOney\EventListener;

use PayPlugModule\PayPlugModule;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;

class ConfirmationEmailListener implements EventSubscriberInterface
{
    /**
     * @var MailerFactory
     */
    protected $mailer;

    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;

    public function __construct(MailerFactory $mailer, EventDispatcherInterface $eventDispatcher)
    {
        $this->mailer = $mailer;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function updateOrderStatus(OrderEvent $event)
    {
        $payPlug = new PayPlugOney();

        if ($event->getOrder()->isPaid() && $payPlug->isPaymentModuleFor($event->getOrder())) {
            $this->eventDispatcher->dispatch(TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL, $event);
            $this->eventDispatcher->dispatch(TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL, $event);
        }
    }


    public function cancelOrderConfirmationEmail(OrderEvent $event)
    {
        $payPlug = new PayPlugOney();

        if ($payPlug->isPaymentModuleFor($event->getOrder()) && !$event->getOrder()->isPaid()) {
            $event->stopPropagation();
        }
    }


    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateOrderStatus", 128),
            TheliaEvents::ORDER_SEND_NOTIFICATION_EMAIL => array("cancelOrderConfirmationEmail", 150),
            TheliaEvents::ORDER_SEND_CONFIRMATION_EMAIL => array("cancelOrderConfirmationEmail", 150)
        );
    }
}
