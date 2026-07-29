<?php

declare(strict_types=1);

namespace PayPlugOney\Hook;

use PayPlugModule\PayPlugModule;
use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OrderPayPlugDataPresenter;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Thelia 2 Smarty rendering of the PayPlug block on the order edit screen, for orders paid
 * through Oney.
 *
 * On Thelia 3 this is handled by PayPlugOney\Hook\Back\OrderEditHook, so this one no-ops
 * there — which also avoids the assets resolver path below, whose contract changed in
 * Thelia 3 (string arguments, and the back-office template is now .html.twig).
 */
class BackHook extends BaseHook
{
    public function __construct(
        private readonly OrderPayPlugDataPresenter $dataPresenter,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public function onOrderEditPaymentModuleBottom(HookRenderEvent $event): void
    {
        if (PayPlugOney::isThelia3()) {
            return;
        }

        $templateData = $this->dataPresenter->getTemplateData($event->getArgument('order_id'));
        if (null === $templateData) {
            return;
        }

        $event->add(
            $this->renderBasePayPlugTemplate(
                'PayPlugModule/order_pay_plug.html',
                array_merge($event->getArguments(), $templateData)
            )
        );
    }

    /**
     * Renders a template owned by PayPlugModule, resolved through its own asset directory.
     *
     * @param array<string, mixed> $parameters
     */
    protected function renderBasePayPlugTemplate(string $templateName, array $parameters): string
    {
        $templateDir = $this->assetsResolver->resolveAssetSourcePath(
            PayPlugModule::getModuleCode(),
            '',
            $templateName,
            $this->parser
        );

        if (null === $templateDir) {
            return \sprintf(
                'ERR: Unknown template %s for module %s',
                $templateName,
                PayPlugModule::getModuleCode()
            );
        }

        return $this->parser->render($templateDir.DS.$templateName, $parameters);
    }
}
