<?php

declare(strict_types=1);

namespace PayPlugOney\EventListener;

use OpenApi\Events\PaymentModuleOptionEvent as OpenApiPaymentModuleOptionEvent;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\Dto\OneySimulationOption;
use PayPlugOney\Service\OneyCartAmountResolver;
use PayPlugOney\Service\OneySimulationHtmlRenderer;
use PayPlugOney\Service\OneySimulationPresenter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Api\Bridge\Propel\Event\PaymentModuleOptionEvent as NativePaymentModuleOptionEvent;
use Thelia\Api\Resource\PaymentModuleOption;
use Thelia\Api\Resource\PaymentModuleOptionGroup;
use Thelia\Core\Event\ActionEvent;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Model\Cart;
use Thelia\Model\Order;

/**
 * Publishes the Oney instalment plans (3x / 4x) as payment module options.
 *
 * Two mechanisms request those options and BOTH are live:
 *
 *  - Thelia\Domain\Module\Payment\PaymentModuleService (native, feeds
 *    /api/front/payment/modules, which the Flexy theme consumes)
 *  - OpenApi\Controller\Front\PaymentController (legacy headless checkout)
 *
 * They dispatch the SAME event name ('thelia.module.payment.options') with DIFFERENT event
 * classes. Hence a single listener typed on their common parent (ActionEvent) that
 * dispatches on instanceof: a listener type-hinted on either concrete class would raise a
 * fatal TypeError when the other mechanism fires, because PHP checks the parameter type
 * before the method body runs — an internal instanceof guard would never be reached.
 */
class PaymentOptionListener implements EventSubscriberInterface
{
    public const ONEY_TYPE_OPTION_GROUP_CODE = 'pay_plug_oney_type';

    /**
     * Hardcoded rather than referencing TheliaEvents::MODULE_PAYMENT_GET_OPTIONS or
     * OpenApiEvents::MODULE_PAYMENT_GET_OPTIONS: both constants hold this exact string, and
     * this way the listener depends on neither.
     */
    private const PAYMENT_OPTIONS_EVENT = 'thelia.module.payment.options';

    public function __construct(
        private readonly OneySimulationPresenter $simulationPresenter,
        private readonly OneySimulationHtmlRenderer $htmlRenderer,
        private readonly OneyCartAmountResolver $cartAmountResolver,
        private readonly RequestStack $requestStack,
        // Only present when the OpenApi module is installed. Nullable with a default so
        // autowiring falls back instead of failing on installs without it.
        #[Autowire(service: 'open_api.model.factory')]
        private readonly ?object $openApiModelFactory = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            self::PAYMENT_OPTIONS_EVENT => ['getPaymentModuleOptions', 128],
        ];
    }

    public function getPaymentModuleOptions(ActionEvent $event): void
    {
        // instanceof against a missing class returns false without error, so both branches
        // are safe whether or not OpenApi is installed.
        if ($event instanceof NativePaymentModuleOptionEvent) {
            $this->fillNativeEvent($event);

            return;
        }

        if ($event instanceof OpenApiPaymentModuleOptionEvent) {
            $this->fillOpenApiEvent($event);
        }
    }

    private function fillNativeEvent(NativePaymentModuleOptionEvent $event): void
    {
        $options = $this->getOptionsFor($event->getModule()->getId(), $event->getCart());
        if ([] === $options) {
            return;
        }

        $optionGroup = $this->populateOptionGroup(
            new PaymentModuleOptionGroup(),
            $options,
            static fn (): PaymentModuleOption => new PaymentModuleOption()
        );

        $event->appendPaymentModuleOptionGroups($optionGroup);
    }

    private function fillOpenApiEvent(OpenApiPaymentModuleOptionEvent $event): void
    {
        $modelFactory = $this->openApiModelFactory;
        if (null === $modelFactory) {
            return;
        }

        $options = $this->getOptionsFor($event->getModule()->getId(), $event->getCart());
        if ([] === $options) {
            return;
        }

        $optionGroup = $this->populateOptionGroup(
            $modelFactory->buildModel('PaymentModuleOptionGroup'),
            $options,
            static fn (): object => $modelFactory->buildModel('PaymentModuleOption')
        );

        $event->appendPaymentModuleOptionGroups($optionGroup);
    }

    /**
     * Fills an option group, whichever API it belongs to.
     *
     * The native and OpenApi DTOs are unrelated classes but expose the same setters, so only
     * their instantiation differs — hence the factory callable. This keeps the group/option
     * shape (codes, labels, selection bounds) defined exactly once.
     *
     * @template T of object
     *
     * @param T                                 $optionGroup
     * @param array<int, OneySimulationOption>  $options
     * @param callable(): object                $createOption
     *
     * @return T
     */
    private function populateOptionGroup(object $optionGroup, array $options, callable $createOption): object
    {
        $optionGroup
            ->setCode(self::ONEY_TYPE_OPTION_GROUP_CODE)
            ->setTitle($this->simulationPresenter->getGroupTitle())
            ->setDescription($this->simulationPresenter->getLegalNotice())
            ->setMinimumSelectedOptions(1)
            ->setMaximumSelectedOptions(1);

        foreach ($options as $option) {
            // setCode()/setTitle() are non-nullable and uninitialised by default on the native
            // DTO: omitting either breaks serialisation with "must not be accessed before
            // initialization".
            $optionGroup->appendPaymentModuleOption(
                $createOption()
                    ->setCode($option->code)
                    ->setTitle($this->simulationPresenter->getOptionTitle($option))
                    ->setDescription($this->htmlRenderer->render($option))
            );
        }

        return $optionGroup;
    }

    /**
     * @return array<int, OneySimulationOption>
     */
    private function getOptionsFor(int $moduleId, ?Cart $cart): array
    {
        if ($moduleId !== PayPlugOney::getModuleId()) {
            return [];
        }

        if (null === $cart || !PayPlugOney::isPaymentEnabled()) {
            return [];
        }

        /** @var Session $session */
        $session = $this->requestStack->getSession();

        /** @var Order|null $order */
        $order = $session->getOrder();

        return $this->simulationPresenter->getOptions(
            $this->cartAmountResolver->getTaxedTotalInCents($cart, $order)
        );
    }
}
