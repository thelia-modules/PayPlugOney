<?php

namespace PayPlugOney\Form;

use PayPlugModule\Model\PayPlugConfigValue;
use PayPlugModule\PayPlugModule;
use PayPlugModule\Service\OrderStatusService;
use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Thelia\Core\Translation\Translator;
use Thelia\Form\BaseForm;
use Thelia\Model\OrderStatusQuery;

class ConfigurationForm extends BaseForm
{
    protected function buildForm()
    {
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
        ;
    }

    public function getName()
    {
        return "payplugoney_configuration_form";
    }
}
