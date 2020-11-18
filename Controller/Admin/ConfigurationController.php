<?php

namespace PayPlugOney\Controller\Admin;

use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;

class ConfigurationController extends BaseAdminController
{
    public function saveAction()
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::MODULE), 'PayPlugOney', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm('payplugoney_configuration_form');

        try {
            $data = $this->validateForm($form)->getData();

            foreach ($data as $key => $value) {
                if (in_array($key, PayPlugOneyConfigValue::getConfigKeys())) {
                    PayPlugOney::setConfigValue($key, $value);
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

        return $this->generateSuccessRedirect($form);
    }

}