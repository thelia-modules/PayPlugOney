<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use PayPlugModule\Form\OrderActionForm;
use PayPlugModule\Form\OrderRefundForm;
use PayPlugModule\Model\OrderPayPlugData;
use PayPlugModule\Model\OrderPayPlugDataQuery;
use PayPlugModule\Model\OrderPayPlugMultiPaymentQuery;
use PayPlugOney\PayPlugOney;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\TableMap;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Model\Order;
use Thelia\Model\OrderProduct;
use Thelia\Model\OrderProductTax;
use Thelia\Model\OrderProductTaxQuery;
use Thelia\Model\OrderQuery;
use Thelia\Model\OrderStatus;

/**
 * Builds the variable set that PayPlugModule's order-edit template expects, for orders
 * paid through Oney.
 *
 * PayPlugModule renders that template itself, but its hook filters on its own module id
 * (PayPlugModule\Hook\BackHookManager::onOrderEditPaymentModuleBottom), so it never fires
 * for an Oney order. Rather than fork the template, this module reuses it and supplies the
 * variables — including the two FormViews it requires, which the previous implementation
 * omitted, breaking the block as soon as a paid Oney order was edited.
 *
 * Shared by the Thelia 2 (Smarty) and Thelia 3 (Twig) hooks so the preparation exists
 * once. It does duplicate PayPlugModule's own preparation logic; removing that would
 * require making it extractable upstream, which is out of this module's scope.
 */
final class OrderPayPlugDataPresenter
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
    ) {
    }

    /**
     * @return array<string, mixed>|null null when the order is not an Oney order, or has
     *                                   no PayPlug data attached
     */
    public function getTemplateData(mixed $orderId): ?array
    {
        $order = OrderQuery::create()
            ->filterByPaymentModuleId(PayPlugOney::getModuleId())
            ->filterById($orderId)
            ->findOne();

        if (null === $order) {
            return null;
        }

        /** @var OrderPayPlugData|null $orderPayPlugData */
        $orderPayPlugData = OrderPayPlugDataQuery::create()->findOneById($order->getId());

        if (null === $orderPayPlugData) {
            return null;
        }

        $multiPayments = OrderPayPlugMultiPaymentQuery::create()
            ->filterByOrderId($order->getId())
            ->find()
            ->toArray(null, false, TableMap::TYPE_CAMELNAME);

        $tax = 0.0;

        return array_merge(
            [
                'refund_form' => $this->formFactory->createForm(OrderRefundForm::getName())->getForm()->createView(),
                'capture_form' => $this->formFactory->createForm(OrderActionForm::getName())->getForm()->createView(),
                'isPaid' => $this->isPaid($order),
                'currency' => $order->getCurrency()->getSymbol(),
                'orderTotalAmount' => $order->getTotalAmount($tax, true),
                'orderTotalAmountWithoutShipping' => $order->getTotalAmount($tax, false),
                'orderProducts' => $this->getOrderProducts($order),
            ],
            $orderPayPlugData->toArray(TableMap::TYPE_CAMELNAME),
            [
                'multiPayments' => $multiPayments,
            ]
        );
    }

    /**
     * Unlike PayPlugModule, an Oney order awaiting Oney's authorisation is not considered
     * paid either.
     */
    private function isPaid(Order $order): bool
    {
        return !\in_array(
            $order->getOrderStatus()->getCode(),
            [
                OrderStatus::CODE_NOT_PAID,
                OrderStatus::CODE_CANCELED,
                OrderStatusService::ONEY_AUTHORIZATION_PENDING_ORDER_STATUS_CODE,
            ],
            true
        );
    }

    /**
     * @return array<int, array{ref: string|null, title: string|null, realTotalTaxedPrice: float}>
     */
    private function getOrderProducts(Order $order): array
    {
        $orderProducts = [];

        /** @var array<int, OrderProduct> $lines */
        $lines = iterator_to_array($order->getOrderProducts());

        $taxesByLine = $this->loadTaxesByOrderProduct($lines);

        foreach ($lines as $orderProduct) {
            $wasInPromo = (bool) $orderProduct->getWasInPromo();

            $unitPrice = $wasInPromo
                ? (float) $orderProduct->getPromoPrice()
                : (float) $orderProduct->getPrice();

            $unitTax = 0.0;
            foreach ($taxesByLine[$orderProduct->getId()] ?? [] as $orderProductTax) {
                $unitTax += $wasInPromo
                    ? (float) $orderProductTax->getPromoAmount()
                    : (float) $orderProductTax->getAmount();
            }

            $orderProducts[] = [
                'ref' => $orderProduct->getProductSaleElementsRef(),
                'title' => $orderProduct->getTitle(),
                'realTotalTaxedPrice' => (round($unitPrice, 2) + round($unitTax, 2)) * $orderProduct->getQuantity(),
            ];
        }

        return $orderProducts;
    }

    /**
     * Loads every line's taxes in one query, indexed by order product id.
     *
     * Calling $orderProduct->getOrderProductTaxes() inside the loop issues one query per line
     * (N+1). PayPlugModule's own hook still does that — see the class docblock; fixing it there
     * is not possible durably since the module is installed by composer into gitignored
     * vendor/, so the change would be wiped by the next composer install.
     *
     * @param array<int, OrderProduct> $lines
     *
     * @return array<int, array<int, OrderProductTax>>
     */
    private function loadTaxesByOrderProduct(array $lines): array
    {
        $lineIds = array_values(array_filter(array_map(
            static fn (OrderProduct $orderProduct): ?int => $orderProduct->getId(),
            $lines
        )));

        if ([] === $lineIds) {
            return [];
        }

        $taxesByLine = [];

        /** @var OrderProductTax $orderProductTax */
        foreach (OrderProductTaxQuery::create()->filterByOrderProductId($lineIds, Criteria::IN)->find() as $orderProductTax) {
            $taxesByLine[$orderProductTax->getOrderProductId()][] = $orderProductTax;
        }

        return $taxesByLine;
    }
}
