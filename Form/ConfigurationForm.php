<?php

declare(strict_types=1);

namespace PayPlugOney\Form;

use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Lang;

class ConfigurationForm extends BaseForm
{
    protected function buildForm(): void
    {
        $translator = Translator::getInstance();

        $this->formBuilder
            ->add(
                PayPlugOneyConfigValue::PAYMENT_ENABLED,
                CheckboxType::class,
                [
                    'data' => (bool) PayPlugOney::getConfigValue(PayPlugOneyConfigValue::PAYMENT_ENABLED),
                    'label' => $translator->trans('Enable multi payment by Oney', [], PayPlugOney::DOMAIN_NAME),
                    'required' => false,
                ]
            )
            ->add(
                PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID,
                ChoiceType::class,
                [
                    'data' => (int) PayPlugOney::getConfigValue(PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID),
                    'label' => $translator->trans(
                        'Select your content used for display your "Terms and conditions" a new section for Oney will be added at the end of this content',
                        [],
                        PayPlugOney::DOMAIN_NAME
                    ),
                    'choices' => $this->getContentChoices(),
                    'required' => false,
                ]
            )
            ->add(
                PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED,
                CheckboxType::class,
                [
                    'data' => (bool) PayPlugOney::getConfigValue(PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED),
                    'label' => $translator->trans('I confirm that I have added the hook', [], PayPlugOney::DOMAIN_NAME),
                    'required' => false,
                ]
            );
    }

    /**
     * @return array<string, int> label => content id, as ChoiceType expects
     */
    private function getContentChoices(): array
    {
        $choices = [
            Translator::getInstance()->trans('Select a content', [], PayPlugOney::DOMAIN_NAME) => 0,
        ];

        // joinWithI18n eager-loads the titles: reading getTitle() on a plain find() issues one
        // extra query per content to hydrate the translation.
        $contents = ContentQuery::create()
            ->joinWithI18n($this->getCurrentLocale())
            ->find();

        /** @var Content $content */
        foreach ($contents as $content) {
            $choices[(string) $content->getTitle()] = $content->getId();
        }

        return $choices;
    }

    private function getCurrentLocale(): string
    {
        $request = $this->getRequest();
        $lang = $request->hasSession() ? $request->getSession()->get('thelia.current.lang') : null;

        if ($lang instanceof Lang && null !== $lang->getLocale()) {
            return $lang->getLocale();
        }

        return (string) Lang::getDefaultLanguage()->getLocale();
    }

    public static function getName(): string
    {
        return 'payplugoney_configuration_form';
    }
}
