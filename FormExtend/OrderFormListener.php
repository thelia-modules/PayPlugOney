<?php

namespace PayPlugOney\FormExtend;

use PayPlugModule\Model\PayPlugConfigValue;
use PayPlugModule\PayPlugModule;
use PayPlugModule\Service\PaymentService;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\Translation\Translator;

class OrderFormListener implements EventSubscriberInterface
{
    const THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME = 'thelia_order_payment';

    const PAY_PLUG_ONEY_TYPE_FIELD_NAME = 'pay_plug_oney_type';

    /** @var Request  */
    protected $request;

    public function __construct(RequestStack $requestStack)
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    public function addOneyTypeField(TheliaFormEvent $event)
    {
        $event->getForm()->getFormBuilder()
            ->add(
                self::PAY_PLUG_ONEY_TYPE_FIELD_NAME,
                ChoiceType::class,
                [
                    'choices' => [
                        'oney_x3_with_fees' => Translator::getInstance()->trans('Pay in 3 times', [], PayPlugOney::DOMAIN_NAME),
                        'oney_x4_with_fees' => Translator::getInstance()->trans('Pay in 4 times', [], PayPlugOney::DOMAIN_NAME)
                    ]
                ]
            );

    }

    public function checkOneyTypeSelected(OrderEvent $event)
    {
        $this->request->getSession()->set(self::PAY_PLUG_ONEY_TYPE_FIELD_NAME, null);
        $formData = $this->request->get(self::THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME);

        if (!isset($formData[self::PAY_PLUG_ONEY_TYPE_FIELD_NAME]) || null == $formData[self::PAY_PLUG_ONEY_TYPE_FIELD_NAME]) {
            return;
        }

        $this->request->getSession()->set(self::PAY_PLUG_ONEY_TYPE_FIELD_NAME, $formData[self::PAY_PLUG_ONEY_TYPE_FIELD_NAME]);
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::FORM_AFTER_BUILD.'.'.self::THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME => array('addOneyTypeField', 64),
            TheliaEvents::ORDER_SET_PAYMENT_MODULE => array('checkOneyTypeSelected', 64)
        );
    }
}