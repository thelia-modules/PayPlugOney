<?php

declare(strict_types=1);

namespace PayPlugOney\Controller\Front;

use PayPlugOney\FormExtend\OrderFormListener;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyAddressValidator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;
use Thelia\Model\AddressQuery;
use Thelia\Model\Cart;
use Thelia\Model\CartItem;
use Thelia\Model\Customer;
use Thelia\Model\Order;

/**
 * Validates, before handing over to Oney, the constraints Oney enforces on its side and
 * would otherwise reject the payment for.
 */
class OneyController extends BaseFrontController
{
    /**
     * Oney refuses orders with more than this many items in total.
     */
    private const MAXIMUM_CART_QUANTITY = 1000;

    /**
     * The dispatcher is injected as an argument: controllers have no getDispatcher()
     * helper method.
     */
    #[Route('/oney/payment_form', name: 'payplugoney_check_payment_form')]
    public function checkPaymentFormValidity(
        // OneyAddressValidator rather than OneyService: it needs no PayPlug credentials, so an
        // unconfigured shop does not turn this endpoint into a 500.
        OneyAddressValidator $addressValidator,
        EventDispatcherInterface $dispatcher,
    ): JsonResponse {
        /** @var Session $session */
        $session = $this->getSession();

        $customer = $session->getCustomerUser();
        if (!$customer instanceof Customer) {
            return new JsonResponse(['errors' => []]);
        }

        $errors = array_merge(
            $this->validateAddresses($addressValidator, $session->getOrder(), $customer),
            $this->validateCart($session->getSessionCart($dispatcher), $customer)
        );

        return new JsonResponse(['errors' => $errors]);
    }

    /**
     * @return array<int, string>
     */
    private function validateAddresses(OneyAddressValidator $addressValidator, Order $order, Customer $customer): array
    {
        $errors = [];

        foreach ($this->getAddressesToValidate($order, $customer) as $label => $address) {
            try {
                $addressValidator->validate($address);
            } catch (\Exception $exception) {
                $errors[] = Translator::getInstance()->trans($label, [], PayPlugOney::DOMAIN_NAME)
                    .$exception->getMessage();
            }
        }

        return $errors;
    }

    /**
     * @return array<string, Address|null> translation key of the error prefix => address
     */
    private function getAddressesToValidate(Order $order, Customer $customer): array
    {
        return [
            'Invoice address invalid : ' => $this->findCustomerAddress(
                $this->getSubmittedInvoiceAddressId() ?? $order->getChoosenInvoiceAddress(),
                $customer
            ),
            'Delivery address invalid : ' => $this->findCustomerAddress(
                $order->getChoosenDeliveryAddress(),
                $customer
            ),
        ];
    }

    /**
     * The customer may have picked a different invoice address in the form that is being
     * validated, before it is persisted on the order.
     *
     * Read from the POST bag rather than through Request::get('thelia_order_payment[…]'):
     * that bracket syntax never resolved, and the third "deep" argument it relied on was
     * removed from Symfony's Request::get() — so this check silently always fell back to the
     * order's stored address. Request::get() is forbidden anyway; this route is documented as
     * POST, so the payment form arrives in the request bag.
     */
    private function getSubmittedInvoiceAddressId(): ?int
    {
        $formData = $this->getRequest()->request->all()[OrderFormListener::THELIA_CUSTOMER_ORDER_PAYMENT_FROM_NAME] ?? null;

        if (!\is_array($formData)) {
            return null;
        }

        $addressId = $formData['invoice-address'] ?? null;

        return null !== $addressId && '' !== $addressId ? (int) $addressId : null;
    }

    /**
     * SECURITY: the invoice address id can come straight from the request, so it must be
     * scoped to the logged-in customer. Without the customer filter this endpoint leaks
     * whether an arbitrary address id exists and, through the error messages, its country and
     * phone type — an IDOR that let anyone probe other customers' addresses.
     */
    private function findCustomerAddress(mixed $addressId, Customer $customer): ?Address
    {
        if (null === $addressId || '' === $addressId) {
            return null;
        }

        return AddressQuery::create()
            ->filterById((int) $addressId)
            ->filterByCustomerId($customer->getId())
            ->findOne();
    }

    /**
     * @return array<int, string>
     */
    private function validateCart(Cart $cart, Customer $customer): array
    {
        $errors = [];

        $totalQuantity = array_reduce(
            iterator_to_array($cart->getCartItems()),
            static fn (int $accumulator, CartItem $cartItem): int => $accumulator + $cartItem->getQuantity(),
            0
        );

        if ($totalQuantity > self::MAXIMUM_CART_QUANTITY) {
            $errors[] = Translator::getInstance()->trans(
                'The sum of quantities of item in your cart must be lesser than 1000.',
                [],
                PayPlugOney::DOMAIN_NAME
            );
        }

        if (str_contains((string) $customer->getEmail(), '+')) {
            $errors[] = Translator::getInstance()->trans(
                'Your email addresses cannot contain a plus sign (+).',
                [],
                PayPlugOney::DOMAIN_NAME
            );
        }

        return $errors;
    }
}
