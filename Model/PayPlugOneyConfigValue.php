<?php

declare(strict_types=1);

namespace PayPlugOney\Model;

/**
 * Configuration keys stored through BaseModule::setConfigValue(). Not a Propel model:
 * the module owns no table.
 */
final class PayPlugOneyConfigValue
{
    public const PAYMENT_ENABLED = 'payment_enabled';

    public const TERMS_AND_CONDITIONS_CONTENT_ID = 'terms_and_conditions_content_id';

    public const TERMS_AND_CONDITIONS_HOOK_ADDED = 'terms_and_conditions_hook_added';

    /**
     * @return array<int, string>
     */
    public static function getConfigKeys(): array
    {
        return [
            self::PAYMENT_ENABLED,
            self::TERMS_AND_CONDITIONS_CONTENT_ID,
            self::TERMS_AND_CONDITIONS_HOOK_ADDED,
        ];
    }
}
