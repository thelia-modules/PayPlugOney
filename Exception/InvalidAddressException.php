<?php

declare(strict_types=1);

namespace PayPlugOney\Exception;

/**
 * Raised when a billing or delivery address does not satisfy Oney's requirements
 * (unsupported country, missing or non-mobile phone number).
 *
 * Extends \Exception so existing catch-all callers keep working unchanged.
 */
class InvalidAddressException extends \Exception
{
}
