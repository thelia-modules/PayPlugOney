<?php

namespace PayPlugOney\Hook;

use PayPlugModule\Model\OrderPayPlugData;
use PayPlugModule\Model\OrderPayPlugDataQuery;
use PayPlugModule\PayPlugModule;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OrderStatusService;
use Propel\Runtime\Map\TableMap;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;

class BackHook extends BaseHook
{
    /**
     * @param HookRenderEvent $event
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function onOrderEditPaymentModuleBottom(HookRenderEvent $event)
    {
        $order = OrderQuery::create()
            ->filterByPaymentModuleId(PayPlugOney::getModuleId())
            ->filterById($event->getArgument('order_id'))
            ->findOne();

        if (null === $order) {
            return;
        }

        /** @var OrderPayPlugData $orderPayPlugData */
        $orderPayPlugData = OrderPayPlugDataQuery::create()
            ->findOneById($order->getId());

        if (null === $orderPayPlugData) {
            return;
        }

        $isPaid = !in_array($order->getOrderStatus()->getCode(), [OrderStatus::CODE_NOT_PAID, OrderStatus::CODE_CANCELED, OrderStatusService::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE]);

        $event->add(
            $this->renderBasePayPlugTemplate(
                'PayPlugModule/order_pay_plug.html',
                array_merge(
                    $event->getArguments(),
                    [
                        'isPaid' => $isPaid,
                        'currency' => $order->getCurrency()->getSymbol(),
                        'needCapture' => 0
                    ],
                    $orderPayPlugData->toArray(TableMap::TYPE_CAMELNAME),
                    [
                        'multiPayments' => [],
                        'needCapture' => 0
                    ]
                )
            )
        );
    }

    protected function renderBasePayPlugTemplate($templateName, $parameters)
    {
        $templateDir = $this->assetsResolver->resolveAssetSourcePath(PayPlugModule::getModuleCode(), false, $templateName, $this->parser);

        if (null !== $templateDir) {
            // retrieve the template
            $content      = $this->parser->render($templateDir . DS . $templateName, $parameters);
        } else {
            $content = sprintf("ERR: Unknown template %s for module %s", $templateName, PayPlugModule::getModuleCode());
        }

        return $content;
    }
}