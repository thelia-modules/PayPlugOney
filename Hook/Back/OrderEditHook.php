<?php

declare(strict_types=1);

namespace PayPlugOney\Hook\Back;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OrderPayPlugDataPresenter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Renders the PayPlug block on the order edit screen for Oney orders, in the Thelia 3
 * default-twig back-office.
 *
 * PayPlugModule owns that template but its own hook filters on its own module id, so it
 * never fires for an Oney order. Rather than forking the markup, this hook reuses
 * PayPlugModule's Twig template — module template namespaces share a flat Twig loader — and
 * supplies the variables through OrderPayPlugDataPresenter.
 *
 * This replaces the Thelia 2 asset-resolver indirection in PayPlugOney\Hook\BackHook, which
 * is broken on Thelia 3: the resolver now expects string arguments, the back-office template
 * is .html.twig rather than .html, and that template requires refund_form/capture_form
 * FormViews the old code never provided.
 */
class OrderEditHook extends BaseHook
{
    private const PAYPLUG_ORDER_TEMPLATE = 'PayPlugModule/order_pay_plug.html.twig';

    public function __construct(
        private readonly OrderPayPlugDataPresenter $dataPresenter,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        if (!PayPlugOney::isThelia3()) {
            return [];
        }

        // Method name distinct from BackHook::onOrderEditPaymentModuleBottom, so the two
        // registrations do not collide on the same module_hook row.
        return [
            'order-edit.payment-module-bottom' => [
                ['type' => 'back', 'method' => 'onOrderEditPaymentModuleBottomTwig'],
            ],
        ];
    }

    public function onOrderEditPaymentModuleBottomTwig(HookRenderEvent $event): void
    {
        $templateData = $this->dataPresenter->getTemplateData($event->getArgument('order_id'));

        if (null === $templateData) {
            return;
        }

        $event->add(
            $this->render(
                self::PAYPLUG_ORDER_TEMPLATE,
                array_merge($event->getArguments(), $templateData)
            )
        );
    }
}
