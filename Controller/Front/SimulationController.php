<?php

declare(strict_types=1);

namespace PayPlugOney\Controller\Front;

use PayPlugOney\Service\OneyEligibilityChecker;
use PayPlugOney\Service\OneySimulationThrottle;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Twig\Environment;

/**
 * Renders the Oney simulation, and answers whether a given amount is eligible.
 *
 * The simulation is rendered through the PayPlugOney:OneySimulation LiveComponent. Exposing
 * it over a route matters: a theme template must not reference a module's Twig component or
 * function directly, since that reference is resolved at compile time and breaks the whole
 * page when the module is disabled. Fetching this route keeps the theme independent of the
 * module.
 *
 * Routes are declared in Config/routing.xml only — see ConfigurationController for why.
 */
class SimulationController extends BaseFrontController
{
    /**
     * Front-office templates live under frontOffice/flexy/, named after the base theme the
     * module targets — never after a project theme such as vallereuil-scierie, which inherits
     * from flexy. This mirrors backOffice/default-twig/.
     *
     * The @PayPlugOneyModule namespace points at the module's templates/ directory (TheliaKernel
     * registers it per active module), hence the full path here.
     */
    private const SIMULATION_COMPONENT_TEMPLATE = '@PayPlugOneyModule/frontOffice/flexy/PayPlugOney/simulation.html.twig';

    private const TERMS_TEMPLATE = '@PayPlugOneyModule/frontOffice/flexy/PayPlugOney/terms-and-conditions.html.twig';

    #[Route('/oney/simulate', name: 'payplugoney_simulation')]
    public function simulate(
        OneySimulationThrottle $throttle,
        Environment $twig,
    ): Response {
        // This route is public and each cache miss costs a paid PayPlug call, so cap how often
        // a single client can walk the eligible amount range.
        if (!$throttle->allowsAnotherSimulation()) {
            return new Response('', Response::HTTP_TOO_MANY_REQUESTS);
        }

        // Render the LiveComponent through a thin wrapper template. Going through Twig
        // directly rather than BaseFrontController::render() keeps this independent of the
        // active theme, which owns no PayPlugOney template. Twig is injected as a method
        // argument because the 'twig' service is private and cannot be fetched from the
        // container.
        return new Response(
            $twig->render(self::SIMULATION_COMPONENT_TEMPLATE, [
                'amountInCents' => $this->getRequestedAmountInCents(),
            ])
        );
    }

    /**
     * Lets a theme check eligibility before displaying the Oney call to action, without
     * paying for a simulation call.
     */
    #[Route('/oney/check_simulation', name: 'payplugoney_check_simulation')]
    public function isSimulationValid(): JsonResponse
    {
        return new JsonResponse([
            'isValid' => OneyEligibilityChecker::isAmountEligible($this->getRequestedAmountInCents()),
        ]);
    }

    /**
     * Renders the Oney terms and conditions, so a theme can include them where it displays
     * its own terms. Served as a route rather than as a theme hook: the same endpoint then
     * serves the headless checkout, and the theme stays independent of the module.
     */
    #[Route('/oney/terms-and-conditions', name: 'payplugoney_terms_and_conditions')]
    public function termsAndConditions(Environment $twig): Response
    {
        return new Response($twig->render(self::TERMS_TEMPLATE));
    }

    /**
     * The amount is attacker-controlled, so it is coerced to a bounded integer. It only ever
     * reaches the eligibility check and the PayPlug simulation call, both of which expect
     * cents as an int.
     *
     * Read from the query bag explicitly rather than through Request::get(), which is
     * forbidden: both routes are documented as GET with ?amount=<cents>.
     */
    private function getRequestedAmountInCents(): int
    {
        return (int) round((float) $this->getRequest()->query->get('amount', 0));
    }
}
