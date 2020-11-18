<?php

namespace PayPlugOney\Hook;

use PayPlugOney\PayPlugOney;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

class ConfigurationHook extends BaseHook
{
    public function addOneyConfiguration(HookRenderEvent $event)
    {
        $event->add(
            $this->render('/PayPlugOney/hook_configuration.html')
        );
    }
}