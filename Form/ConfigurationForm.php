<?php

namespace PayPlugOney\Form;

use PayPlugModule\Model\PayPlugConfigValue;
use PayPlugModule\PayPlugModule;
use PayPlugModule\Service\OrderStatusService;
use PayPlugOney\Model\OneyModuleDeliveryType;
use PayPlugOney\Model\OneyModuleDeliveryTypeQuery;
use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\Base\ModuleQuery;
use Thelia\Model\Content;
use Thelia\Model\ContentQuery;
use Thelia\Model\Module;
use Thelia\Model\OrderStatusQuery;
use Thelia\Module\BaseModule;

class ConfigurationForm extends BaseForm
{
    const DELIVERY_MODULE_TYPE_KEY_PREFIX = "module_delivery_type";

    protected function buildForm()
    {
        $contentChoices = [0 => Translator::getInstance()->trans("Select a content", [], PayPlugOney::DOMAIN_NAME)];

        /** @var Content $content */
        foreach (ContentQuery::create()->find() as $content) {
            $contentChoices[$content->getId()] = $content->getTitle();
        }

        $this->formBuilder
            ->add(
                PayPlugOneyConfigValue::PAYMENT_ENABLED,
                CheckboxType::class,
                [
                    "data" => !!PayPlugOney::getConfigValue(PayPlugOneyConfigValue::PAYMENT_ENABLED, false),
                    "label"=> Translator::getInstance()->trans("Enable multi payment by Oney", [], PayPlugOney::DOMAIN_NAME),
                    "required" => false
                ]
            )
            ->add(
                PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID,
                ChoiceType::class,
                [
                    "data" => PayPlugOney::getConfigValue(PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID, 0),
                    "label"=> Translator::getInstance()->trans("Select your content used for display your \"Terms and conditions\" a new section for Oney will be added at the end of this content", [], PayPlugOney::DOMAIN_NAME),
                    "choices" => $contentChoices,
                    "required" => false
                ]
            )
            ->add(
                PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED,
                CheckboxType::class,
                [
                    "data" => !!PayPlugOney::getConfigValue(PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED, false),
                    "label"=> Translator::getInstance()->trans("I confirm that I have added the hook", [], PayPlugOney::DOMAIN_NAME),
                    "required" => false
                ]
            )
        ;
    }

    public function getName()
    {
        return "payplugoney_configuration_form";
    }
}
