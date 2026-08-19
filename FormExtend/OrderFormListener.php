<?php

declare(strict_types=1);

namespace PayPlugOney\FormExtend;

use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Event\TheliaFormEvent;
use Thelia\Core\Translation\Translator;

/**
 * Adds the Oney instalment plan choice to the standard order payment form (web checkout) and
 * remembers the customer's pick in session, where PayPlugOney::pay() reads it back.
 *
 * The headless checkout goes through the payment module options instead — see
 * PayPlugOney\EventListener\PaymentOptionListener.
 */
class OrderFormListener implements EventSubscriberInterface
{
    public const THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME = 'thelia_order_payment';

    public const PAY_PLUG_ONEY_TYPE_FIELD_NAME = 'pay_plug_oney_type';

    /**
     * The only instalment plans Oney accepts. Used both to build the choice field and to
     * whitelist what actually reaches the session.
     */
    private const SUPPORTED_ONEY_TYPES = ['oney_x3_with_fees', 'oney_x4_with_fees'];

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TheliaEvents::FORM_AFTER_BUILD.'.'.self::THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME => ['addOneyTypeField', 64],
            TheliaEvents::ORDER_SET_PAYMENT_MODULE => ['checkOneyTypeSelected', 64],
        ];
    }

    public function addOneyTypeField(TheliaFormEvent $event): void
    {
        $translator = Translator::getInstance();

        $event->getForm()->getFormBuilder()->add(
            self::PAY_PLUG_ONEY_TYPE_FIELD_NAME,
            ChoiceType::class,
            [
                'choices' => [
                    $translator->trans('Pay in 3 times', [], PayPlugOney::DOMAIN_NAME) => self::SUPPORTED_ONEY_TYPES[0],
                    $translator->trans('Pay in 4 times', [], PayPlugOney::DOMAIN_NAME) => self::SUPPORTED_ONEY_TYPES[1],
                ],
                'required' => false,
            ]
        );
    }

    public function checkOneyTypeSelected(OrderEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return;
        }

        $session = $request->getSession();

        // Always reset first: a customer who switches away from Oney must not keep a stale
        // instalment plan in session.
        $session->set(self::PAY_PLUG_ONEY_TYPE_FIELD_NAME, null);

        // The POST bag explicitly, never Request::get(): this runs on ORDER_SET_PAYMENT_MODULE,
        // dispatched while handling the submitted order payment form.
        $formData = $request->request->all()[self::THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME] ?? null;
        if (!\is_array($formData)) {
            return;
        }

        $selectedType = $formData[self::PAY_PLUG_ONEY_TYPE_FIELD_NAME] ?? null;

        // Read straight off the raw request, so whitelist it here: the ChoiceType constrains
        // the UI, not what a client can POST. Without this an arbitrary string would be stored
        // in session and forwarded to Oney as the payment method.
        if (!\in_array($selectedType, self::SUPPORTED_ONEY_TYPES, true)) {
            return;
        }

        $session->set(self::PAY_PLUG_ONEY_TYPE_FIELD_NAME, $selectedType);
    }
}
