<?php

declare(strict_types=1);

namespace PayPlugOney\Service;

use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use PayPlugOney\Exception\InvalidAddressException;
use PayPlugOney\PayPlugOney;
use Thelia\Core\Translation\Translator;
use Thelia\Model\Address;

/**
 * Validates an address against Oney's own requirements, which Oney would otherwise reject the
 * payment for.
 *
 * Deliberately separate from OneyService: this is pure validation with no dependency on the
 * PayPlug API or the event dispatcher. Keeping it inside OneyService — which extends
 * PayPlugModule's PaymentService, whose constructor throws when no API key is configured —
 * meant a shop without PayPlug credentials could not even validate an address, turning the
 * checkout validation endpoint into a 500.
 */
final class OneyAddressValidator
{
    /**
     * Oney is only distributed in these countries.
     */
    private const SUPPORTED_COUNTRY_CODES = ['FR', 'IT'];

    /**
     * @throws InvalidAddressException when the address cannot be used with Oney
     */
    public function validate(?Address $address): void
    {
        if (null === $address) {
            throw new InvalidAddressException($this->trans('Address not found'));
        }

        $countryCode = $address->getCountry()->getIsoalpha2();

        if (!\in_array($countryCode, self::SUPPORTED_COUNTRY_CODES, true)) {
            throw new InvalidAddressException(
                Translator::getInstance()->trans(
                    'Oney is not available in %country.',
                    ['%country' => $address->getCountry()->getTitle()],
                    PayPlugOney::DOMAIN_NAME
                )
            );
        }

        $cellphoneNumber = $address->getCellphone();
        if (null === $cellphoneNumber || '' === trim($cellphoneNumber)) {
            throw new InvalidAddressException($this->trans('Cellphone number is missing.'));
        }

        $this->assertMobileNumber($cellphoneNumber, (string) $countryCode);
    }

    /**
     * Oney sends its authorisation codes by SMS, so a landline is not acceptable.
     *
     * @throws InvalidAddressException
     */
    private function assertMobileNumber(string $cellphoneNumber, string $countryCode): void
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $parsedNumber = $phoneUtil->parse($cellphoneNumber, $countryCode);
        } catch (\Exception) {
            // A malformed number makes libphonenumber throw; surface it as an address error
            // rather than a 500.
            throw new InvalidAddressException($this->trans('Cellphone number is invalid.'));
        }

        $numberType = $phoneUtil->getNumberType($parsedNumber);

        if (PhoneNumberType::MOBILE !== $numberType && PhoneNumberType::FIXED_LINE_OR_MOBILE !== $numberType) {
            throw new InvalidAddressException($this->trans('Cellphone number is invalid.'));
        }
    }

    private function trans(string $message): string
    {
        return Translator::getInstance()->trans($message, [], PayPlugOney::DOMAIN_NAME);
    }
}
