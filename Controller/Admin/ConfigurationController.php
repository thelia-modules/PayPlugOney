<?php

namespace PayPlugOney\Controller\Admin;

use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

class ConfigurationController extends BaseAdminController
{
    public function redirectToPayPlugConfiguration()
    {
        return $this->generateRedirect(URL::getInstance()->absoluteUrl('/admin/module/PayPlugModule')."#oneyConfiguration");
    }

    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'PayPlugOney', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm('payplugoney_configuration_form');

        try {
            $data = $this->validateForm($form)->getData();

            if (
                0 === $data[PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID]
                    &&
                false === $data[PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED]
            ) {
                throw new \Exception(Translator::getInstance()->trans("You must either select a content or say you have added the hook for Oney terms and conditions"));
            }

            foreach ($data as $key => $value) {
                if (in_array($key, PayPlugOneyConfigValue::getConfigKeys())) {
                    PayPlugOney::setConfigValue($key, $value);
                    continue;
                }
            }

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans(
                    "Error",
                    [],
                    PayPlugOney::DOMAIN_NAME
                ),
                $e->getMessage(),
                $form
            );
        }

        $url = $form->getSuccessUrl();
        return $this->generateRedirect($url."#oneyConfiguration");
    }

}