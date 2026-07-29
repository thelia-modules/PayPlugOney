<?php

declare(strict_types=1);

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
use PayPlugOney\Service\OneyEligibilityChecker;
use PayPlugOney\Service\OneyService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Log\Tlog;
use Thelia\Model\Order;
use Thelia\Module\AbstractPaymentModule;
use Thelia\Tools\URL;

class PayPlugOney extends AbstractPaymentModule
{
    public const DOMAIN_NAME = 'payplugoney';

    /**
     * Session key set by the OpenApi module when the customer picks an option in the
     * headless checkout. Duplicated here on purpose: OpenApi is an optional dependency,
     * referencing its controller class would make this module boot-dependent on it.
     *
     * @see \OpenApi\Controller\Front\CheckoutController::PAYMENT_MODULE_OPTION_CHOICES_SESSION_KEY
     */
    private const PAYMENT_MODULE_OPTION_CHOICES_SESSION_KEY = 'payment_module_option_choices';

    private const ONEY_TYPE_OPTION_GROUP = 'pay_plug_oney_type';

    public function pay(Order $order): ?Response
    {
        try {
            /** @var OneyService $oneyService */
            $oneyService = $this->container->get('oney_service');

            $payment = $oneyService->sendOneyPayment(
                $order,
                $this->resolveSelectedOneyType(),
                $this->getOrderPayTotalAmount($order)
            );
        } catch (\Exception $exception) {
            // Without this trace a failed payment is undebuggable: the customer only
            // ever sees the generic error page.
            Tlog::getInstance()->addError(
                \sprintf(
                    'PayPlugOney: payment failed for order %s: %s',
                    $order->getRef(),
                    $exception->getMessage()
                )
            );

            return new RedirectResponse(URL::getInstance()->absoluteUrl('error'));
        }

        return new RedirectResponse($payment['url']);
    }

    /**
     * The instalment plan is chosen either through the standard order form (web checkout)
     * or through the payment module options (headless checkout via OpenApi).
     */
    private function resolveSelectedOneyType(): ?string
    {
        $session = $this->getRequest()->getSession();

        $oneyType = $session->get(OrderFormListener::PAY_PLUG_ONEY_TYPE_FIELD_NAME);
        if (null !== $oneyType) {
            return $oneyType;
        }

        $paymentOptions = $session->get(self::PAYMENT_MODULE_OPTION_CHOICES_SESSION_KEY);
        if (!\is_array($paymentOptions) || [] === $paymentOptions) {
            return null;
        }

        $selectedValues = $paymentOptions[self::ONEY_TYPE_OPTION_GROUP] ?? null;

        return \is_array($selectedValues) ? array_pop($selectedValues) : null;
    }

    public function isValidPayment(): bool
    {
        if (!self::isPaymentEnabled()) {
            return false;
        }

        /** @var PaymentService $paymentService */
        $paymentService = $this->container->get('payplugmodule_payment_service');
        if (!$paymentService->isPayPlugAvailable()) {
            return false;
        }

        return OneyEligibilityChecker::isAmountEligible(
            OneyEligibilityChecker::toCents($this->getCurrentOrderTotalAmount())
        );
    }

    public static function isPaymentEnabled(): bool
    {
        return (bool) self::getConfigValue(PayPlugOneyConfigValue::PAYMENT_ENABLED);
    }

    public function getMinimumAmount(): int
    {
        return intdiv(OneyEligibilityChecker::MINIMUM_AMOUNT_IN_CENTS, 100);
    }

    public function getMaximumAmount(): int
    {
        return intdiv(OneyEligibilityChecker::MAXIMUM_AMOUNT_IN_CENTS, 100);
    }

    /**
     * @return int the minimum amount payable with Oney, in cents
     */
    public function getOneyMinimumAmount(): int
    {
        return OneyEligibilityChecker::MINIMUM_AMOUNT_IN_CENTS;
    }

    /**
     * @return int the maximum amount payable with Oney, in cents
     */
    public function getOneyMaximumAmount(): int
    {
        return OneyEligibilityChecker::MAXIMUM_AMOUNT_IN_CENTS;
    }

    public function getHooks(): array
    {
        return [
            [
                'type' => TemplateDefinition::FRONT_OFFICE,
                'code' => 'pay_plug_oney.terms_and_conditions',
                'title' => [
                    'en_US' => 'Oney hook to place in terms and conditions page',
                    'fr_FR' => 'Hook Oney a placer sur la page des cgv',
                ],
                'block' => false,
                'active' => true,
            ],
        ];
    }

    /**
     * True on Thelia 3 (twig branch), false on Thelia 2. Gates the Thelia 3-only code
     * paths (default-twig hooks, LiveComponent) so the module works on both versions.
     */
    public static function isThelia3(): bool
    {
        return class_exists(\Thelia\Core\Install\Database::class);
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $exclude = [__DIR__.'/I18n/*'];

        if (!self::isThelia3()) {
            // These depend on Thelia 3-only classes (default-twig back-office,
            // Symfony UX components) and must not be registered on Thelia 2.
            $exclude[] = __DIR__.'/Hook/Back/*';
            $exclude[] = __DIR__.'/Twig/*';
        }

        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude($exclude)
            ->autowire(true)
            ->autoconfigure(true);
    }
}
