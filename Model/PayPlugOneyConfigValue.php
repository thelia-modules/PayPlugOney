<?php


namespace PayPlugOney\Model;


class PayPlugOneyConfigValue
{
    const PAYMENT_ENABLED = "payment_enabled";

    const TERMS_AND_CONDITIONS_CONTENT_ID = "terms_and_conditions_content_id";

    const TERMS_AND_CONDITIONS_HOOK_ADDED = "terms_and_conditions_hook_added";

    public static function getConfigKeys()
    {
        return [
            self::PAYMENT_ENABLED,
            self::TERMS_AND_CONDITIONS_CONTENT_ID,
            self::TERMS_AND_CONDITIONS_HOOK_ADDED
        ];
    }
}
