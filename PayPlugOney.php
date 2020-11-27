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

namespace PayPlugOney;

use PayPlugModule\Service\PaymentService;
use PayPlugOney\FormExtend\OrderFormListener;
use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\Service\OneyService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class PayPlugOney extends AbstractPaymentModule
{
    /** @var string */
    const DOMAIN_NAME = 'payplugoney';

    /*
     * You may now override BaseModuleInterface methods, such as:
     * install, destroy, preActivation, postActivation, preDeactivation, postDeactivation
     *
     * Have fun !
     */
    public function pay(Order $order)
    {
        try {
            /** @var OneyService $oneyService */
            $oneyService = $this->container->get('oney_service');

            $oneyType = $this->getRequest()->getSession()->get(OrderFormListener::PAY_PLUG_ONEY_TYPE_FIELD_NAME);
            $payment = $oneyService->sendOneyPayment(
                $order,
                $oneyType
            );
        } catch (\Exception $exception) {
            return RedirectResponse::create(URL::getInstance()->absoluteUrl('error'));
        }

        return new RedirectResponse($payment['url']);
    }

    public function isValidPayment()
    {
        if (!self::isPaymentEnabled()) {
            return false;
        }

        /** @var PaymentService $paymentService */
        $paymentService = $this->container->get('payplugmodule_payment_service');
        if (!$paymentService->isPayPlugAvailable()) {
            return false;
        }

        $amount = $this->getCurrentOrderTotalAmount();

        if ($amount * 100 < (new PayPlugOney)->getMinimumAmount() || $amount * 100 > (new PayPlugOney)->getMaximumAmount()) {
            return false;
        }

        return true;
    }

    public static function isPaymentEnabled()
    {
        return PayPlugOney::getConfigValue(PayPlugOneyConfigValue::PAYMENT_ENABLED, false);
    }

    /**
     * For now it's fix but later it will be configurable
     * @return int the minimum amount to pay with oney (in cents)
     */
    public function getMinimumAmount()
    {
        return 10000;
    }

    /**
     * For now it's fix but later it will be configurable
     * @return int the maximum amount to pay with oney (in cents)
     */
    public function getMaximumAmount()
    {
        return 300000;
    }

    public function getHooks()
    {
        return [
            [
                "type" => TemplateDefinition::FRONT_OFFICE,
                "code" => "pay_plug_oney.terms_and_conditions",
                "title" => [
                    "en_US" => "Oney hook to place in terms and conditions page",
                    "fr_FR" => "Hook Oney a placer sur la page des cgv",
                ],
                "block" => false,
                "active" => true,
            ]
        ];
    }
}
