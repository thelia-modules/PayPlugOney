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
                'logo_url' => $this->resolveLogoUrl(),
            ])
        );
    }

    /**
     * A module's template assets are only copied under public/assets/ when a hook asks the
     * assets resolver for them — the same mechanism BaseHook::addCSS()/addJS() goes through.
     * Hardcoding a path such as asset('assets/modules/payplugoney/…') yields a 404, because
     * nothing ever populates it.
     *
     * The path is relative to templates/backOffice/default-twig/PayPlugOney/, mirroring how
     * Statistic asks for 'assets/css/stats.css'.
     *
     * Returns an empty string rather than throwing: a missing decorative logo must not take
     * down PayPlug's whole configuration screen, and the template skips the <img> when empty.
     */
    private function resolveLogoUrl(): string
    {
        try {
            // PayPlugOney::getModuleCode() rather than $this->module->getCode(): BaseHook
            // declares $module as nullable.
            return (string) $this->getAssetsResolver()->resolveAssetURL(
                PayPlugOney::getModuleCode(),
                'assets/logo-oney.png',
                'images',
                $this->getParser()
            );
        } catch (\Throwable) {
            return '';
        }
    }
}
