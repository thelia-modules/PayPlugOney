<?php

declare(strict_types=1);

namespace PayPlugOney\Hook;

use PayPlugOney\PayPlugOney;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Thelia 2 Smarty rendering of the Oney configuration block, inserted into PayPlugModule's
 * configuration screen.
 *
 * On Thelia 3 the same block is rendered by PayPlugOney\Hook\Back\OneyConfigurationHook
 * against the default-twig back-office, so this one no-ops there.
 */
class ConfigurationHook extends BaseHook
{
    public function addOneyConfiguration(HookRenderEvent $event): void
    {
        if (PayPlugOney::isThelia3()) {
            return;
        }

        $event->add($this->render('/PayPlugOney/hook_configuration.html'));
    }
}
