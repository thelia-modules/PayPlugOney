<?php

declare(strict_types=1);

namespace PayPlugOney\Hook\Back;

use PayPlugOney\Form\ConfigurationForm;
use PayPlugOney\PayPlugOney;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Renders the Oney configuration block inside PayPlugModule's configuration screen, in the
 * Thelia 3 default-twig back-office.
 *
 * PayPlugModule exposes the insertion point with
 * {{ safe_hook('payplugmodule.configuration.bottom') }}, outside its own <form>, so this
 * block ships its own self-contained form.
 *
 * The Thelia 2 rendering stays in PayPlugOney\Hook\ConfigurationHook (Smarty).
 */
class OneyConfigurationHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        if (!PayPlugOney::isThelia3()) {
            // Thelia 2 goes through PayPlugOney\Hook\ConfigurationHook, declared in
            // config.xml.
            return [];
        }

        // Method name deliberately different from ConfigurationHook::addOneyConfiguration:
        // module_hook rows are keyed by (module, hook, method), so a shared name would make
        // one registration overwrite the other.
        return [
            'payplugmodule.configuration.bottom' => [
                ['type' => 'back', 'method' => 'addOneyConfigurationTwig'],
            ],
        ];
    }

    public function addOneyConfigurationTwig(HookRenderEvent $event): void
    {
        $event->add(
            $this->render('PayPlugOney/oney-configuration.html.twig', [
                'form' => $this->formFactory
                    ->createForm(ConfigurationForm::getName(), FormType::class)
                    ->createView()
                    ->getView(),
            ])
        );
    }
}
