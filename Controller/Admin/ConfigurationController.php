<?php

declare(strict_types=1);

namespace PayPlugOney\Controller\Admin;

use PayPlugOney\Model\PayPlugOneyConfigValue;
use PayPlugOney\PayPlugOney;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Translation\Translator;
use Thelia\Tools\URL;

/**
 * Routes are declared in Config/routing.xml only — never with #[Route] attributes: Thelia 3
 * loads both mechanisms, so the routes would be registered twice.
 */
class ConfigurationController extends BaseAdminController
{
    private const PAYPLUG_CONFIGURATION_PATH = '/admin/module/PayPlugModule';

    private const ONEY_CONFIGURATION_ANCHOR = '#oneyConfiguration';

    /**
     * Oney has no configuration screen of its own: it lives inside PayPlugModule's.
     *
     * No checkAuth here on purpose: this only redirects to an admin page, which enforces its
     * own authorisation, and the /admin firewall already requires authentication.
     */
    public function redirectToPayPlugConfiguration(): Response
    {
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl(self::PAYPLUG_CONFIGURATION_PATH).self::ONEY_CONFIGURATION_ANCHOR
        );
    }

    public function saveAction(): Response
    {
        if (null !== $response = $this->checkAuth([AdminResources::MODULE], 'PayPlugOney', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm('payplugoney_configuration_form');

        try {
            $data = $this->validateForm($form)->getData();

            $this->assertTermsAndConditionsAreDisplayed($data);

            foreach ($data as $key => $value) {
                if (\in_array($key, PayPlugOneyConfigValue::getConfigKeys(), true)) {
                    PayPlugOney::setConfigValue($key, $value);
                }
            }
        } catch (\Exception $exception) {
            return $this->handleSaveError($exception, $form);
        }

        return $this->generateRedirect($this->getSafeRedirectUrl($form->getSuccessUrl()));
    }

    /**
     * Confines a form-supplied redirect target to this host.
     *
     * success_url and error_url are submitted as hidden fields, and
     * URL::absoluteUrl() returns any value starting with "http" verbatim
     * (Thelia\Tools\URL::absoluteUrl(), "Already absolute?" branch), so
     * BaseForm::getSuccessUrl() cannot be trusted on its own. CSRF validation already
     * makes this hard to exploit against a third party — this is defence in depth, and
     * the project review rules require the host check regardless.
     */
    private function getSafeRedirectUrl(string $url): string
    {
        $fallback = URL::getInstance()->absoluteUrl(self::PAYPLUG_CONFIGURATION_PATH);

        // Internal path: safe, but reject "//evil.com" which browsers treat as protocol-relative.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return $url.self::ONEY_CONFIGURATION_ANCHOR;
        }

        $currentHost = $this->getRequest()->getSchemeAndHttpHost();

        if (str_starts_with($url, $currentHost.'/') || $url === $currentHost) {
            return $url.self::ONEY_CONFIGURATION_ANCHOR;
        }

        return $fallback.self::ONEY_CONFIGURATION_ANCHOR;
    }

    /**
     * Oney legally requires its terms to be reachable, through a content page or through the
     * theme.
     *
     * @param array<string, mixed> $data
     *
     * @throws \Exception when neither option is configured
     */
    private function assertTermsAndConditionsAreDisplayed(array $data): void
    {
        $hasContent = 0 !== (int) ($data[PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_CONTENT_ID] ?? 0);
        $hookConfirmed = (bool) ($data[PayPlugOneyConfigValue::TERMS_AND_CONDITIONS_HOOK_ADDED] ?? false);

        if ($hasContent || $hookConfirmed) {
            return;
        }

        throw new \Exception(
            Translator::getInstance()->trans(
                'You must either select a content or say you have added the hook for Oney terms and conditions',
                [],
                PayPlugOney::DOMAIN_NAME
            )
        );
    }

    /**
     * The error has to travel through the flash bag: the configuration screen is re-rendered
     * after a redirect, which loses the ParserContext the form error mechanism relies on.
     */
    private function handleSaveError(\Exception $exception, mixed $form): Response
    {
        $this->setupFormErrorContext(
            Translator::getInstance()->trans('Error', [], PayPlugOney::DOMAIN_NAME),
            $exception->getMessage(),
            $form
        );

        $session = $this->getRequest()->getSession();

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('danger', $exception->getMessage());
        }

        return new RedirectResponse(
            URL::getInstance()->absoluteUrl(self::PAYPLUG_CONFIGURATION_PATH).self::ONEY_CONFIGURATION_ANCHOR
        );
    }
}
