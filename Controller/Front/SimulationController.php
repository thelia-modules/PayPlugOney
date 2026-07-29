<?php

declare(strict_types=1);

namespace PayPlugOney\Controller\Front;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyEligibilityChecker;
use PayPlugOney\Service\OneySimulationPresenter;
use PayPlugOney\Service\OneySimulationThrottle;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;
use Twig\Environment;

/**
 * Renders the Oney simulation, and answers whether a given amount is eligible.
 *
 * On Thelia 3 the simulation is rendered through the PayPlugOney:OneySimulation
 * LiveComponent. Exposing it over a route matters: a theme template must not reference a
 * module's Twig component or function directly, since that reference is resolved at compile
 * time and breaks the whole page when the module is disabled. Fetching this route keeps the
 * theme independent of the module.
 *
 * Routes are declared in Config/routing.xml only — see ConfigurationController for why.
 */
class SimulationController extends BaseFrontController
{
    /**
     * Twig front-office templates live under frontOffice/flexy/, named after the Thelia 3 base
     * theme the module targets — never after a project theme such as vallereuil-scierie, which
     * inherits from flexy. This mirrors backOffice/default-twig/ and keeps the Thelia 2 Smarty
     * templates isolated in frontOffice/default/.
     *
     * The @PayPlugOneyModule namespace points at the module's templates/ directory (TheliaKernel
     * registers it per active module), hence the full path here.
     */
    private const SIMULATION_COMPONENT_TEMPLATE = '@PayPlugOneyModule/frontOffice/flexy/PayPlugOney/simulation.html.twig';

    private const TERMS_TEMPLATE = '@PayPlugOneyModule/frontOffice/flexy/PayPlugOney/terms-and-conditions.html.twig';

    public function simulate(
        OneySimulationPresenter $simulationPresenter,
        OneySimulationThrottle $throttle,
        Environment $twig,
    ): Response {
        // This route is public and each cache miss costs a paid PayPlug call, so cap how often
        // a single client can walk the eligible amount range.
        if (!$throttle->allowsAnotherSimulation()) {
            return new Response('', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $amountInCents = $this->getRequestedAmountInCents();

        if (!PayPlugOney::isThelia3()) {
            if (!OneyEligibilityChecker::isAmountEligible($amountInCents)) {
                return $this->render('PayPlugOney/outOfBoundSimulation', [
                    'minimumAmount' => OneyEligibilityChecker::MINIMUM_AMOUNT_IN_CENTS,
                    'maximumAmount' => OneyEligibilityChecker::MAXIMUM_AMOUNT_IN_CENTS,
                ]);
            }

            return $this->render('PayPlugOney/simulation', [
                'amount' => $amountInCents,
                'options' => $simulationPresenter->getOptions($amountInCents),
            ]);
        }

        // Thelia 3: render the LiveComponent through a thin wrapper template. Going through
        // Twig directly rather than BaseFrontController::render() keeps this independent of
        // the active theme, which owns no PayPlugOney template. Twig is injected as a method
        // argument because the 'twig' service is private and cannot be fetched from the
        // container.
        return new Response(
            $twig->render(self::SIMULATION_COMPONENT_TEMPLATE, ['amountInCents' => $amountInCents])
        );
    }

    /**
     * Lets a theme check eligibility before displaying the Oney call to action, without
     * paying for a simulation call.
     */
    public function isSimulationValid(): JsonResponse
    {
        return new JsonResponse([
            'isValid' => OneyEligibilityChecker::isAmountEligible($this->getRequestedAmountInCents()),
        ]);
    }

    /**
     * Renders the Oney terms and conditions, so a theme can include them where it displays
     * its own terms. Replaces the Thelia 2 pay_plug_oney.terms_and_conditions Smarty hook,
     * which has no equivalent in Flexy.
     */
    public function termsAndConditions(Environment $twig): Response
    {
        if (!PayPlugOney::isThelia3()) {
            return $this->render('PayPlugOney/hook/termsAndConditions');
        }

        return new Response($twig->render(self::TERMS_TEMPLATE));
    }

    /**
     * The amount is attacker-controlled, so it is coerced to a bounded integer. It only ever
     * reaches the eligibility check and the PayPlug simulation call, both of which expect
     * cents as an int.
     */
    private function getRequestedAmountInCents(): int
    {
        return (int) round((float) $this->getRequest()->get('amount', 0));
    }
}
