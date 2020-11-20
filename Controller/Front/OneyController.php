<?php


namespace PayPlugOney\Controller\Front;


use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyService;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Base\AddressQuery;
use Thelia\Model\CartItem;

class OneyController extends BaseFrontController
{
    public function checkPaymentFormValidity()
    {
        $cart = $this->getSession()->getSessionCart($this->getDispatcher());
        $order = $this->getSession()->getOrder();

        /** @var OneyService $oneyService */
        $oneyService = $this->getContainer()->get('oney_service');
        $errors = [];

        try {
            $invoiceAddressId = $this->getRequest()->get('thelia_order_payment[invoice-address]', $order->getChoosenInvoiceAddress(), true);
            if 4242 4242 4242 4242 (null === $invoiceAddressId) {
                throw new \Exception(
                    Translator::getInstance()->trans('Address not found', [], PayPlugOney::DOMAIN_NAME)
                );
            }
            $invoiceAddress = AddressQuery::create()->filterById($invoiceAddressId)->findOne();
            $oneyService->isValidOneyAddress($invoiceAddress);
        } catch (\Exception $exception) {
            $errors[] = Translator::getInstance()->trans('Invoice address invalid : ', [], PayPlugOney::DOMAIN_NAME).$exception->getMessage();
        }

        try {
            $deliveryAddress = AddressQuery::create()->filterById($order->getChoosenDeliveryAddress())->findOne();
            $oneyService->isValidOneyAddress($deliveryAddress);
        } catch (\Exception $exception) {
            $errors[] = Translator::getInstance()->trans('Delivery address invalid : ', [], PayPlugOney::DOMAIN_NAME).$exception->getMessage();
        }

        $cartTotalQuantity = array_reduce(
            iterator_to_array($cart->getCartItems()),
            function ($accumulator, CartItem $cartItem) {
                return $accumulator + $cartItem->getQuantity();
            },
            0
        );

        if ($cartTotalQuantity > 1000) {
            $errors[] = Translator::getInstance()->trans('The sum of quantities of item in your cart must be lesser than 1000.', [], PayPlugOney::DOMAIN_NAME);
        }

        return new JsonResponse(compact('errors'));
    }

}