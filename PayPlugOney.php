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

use OpenApi\Controller\Front\CheckoutController;
use PayPlugModule\Service\PaymentService;
use PayPlugOney\FormExtend\OrderFormListener;
use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\Service\OneyService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
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

            if (null === $oneyType) {
                $paymentOptions = $this->getRequest()->getSession()->get(CheckoutController::PAYMENT_MODULE_OPTION_CHOICES_SESSION_KEY);
                if (!empty($paymentOptions)) {
                    foreach ($paymentOptions as $group => $values) {
                        if ($group !== 'pay_plug_oney_type') {
                            continue;
                        }

                        $oneyType = array_pop($values);
                    }
                }
            }
            $payment = $oneyService->sendOneyPayment(
                $order,
                $oneyType
            );
        } catch (\Exception $exception) {
            return  new RedirectResponse(URL::getInstance()->absoluteUrl('error'));
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

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
