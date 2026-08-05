<?php

declare(strict_types=1);

namespace PayPlugOney\Hook\Back;

use PayPlugOney\Form\ConfigurationForm;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;

/**
 * Renders the Oney configuration block inside PayPlugModule's configuration screen, in the
 * default-twig back-office.
 *
 * PayPlugModule exposes the insertion point with
 * {{ safe_hook('payplugmodule.configuration.bottom') }}, outside its own <form>, so this
 * block ships its own self-contained form.
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
