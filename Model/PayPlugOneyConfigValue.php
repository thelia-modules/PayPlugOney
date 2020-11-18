<?php


namespace PayPlugOney\Model;


class PayPlugOneyConfigValue
{
    const PAYMENT_ENABLED = "payment_enabled";

    public static function getConfigKeys()
    {
        return [
            self::PAYMENT_ENABLED
        ];
    }
}
