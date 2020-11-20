<?php

namespace PayPlugOney\Controller\Front;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyService;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\JsonResponse;

class SimulationController extends BaseFrontController
{
    public function simulate()
    {
        $amount = (int)round($this->getRequest()->get('amount'));
        $minimumAmount = (new PayPlugOney)->getMinimumAmount();
        $maximumAmount = (new PayPlugOney)->getMaximumAmount();

        if ($amount < $minimumAmount || $amount > $maximumAmount) {
            return $this->render('PayPlugOney/outOfBoundSimulation', compact('minimumAmount', 'maximumAmount'));
        }

        /** @var OneyService $oneyService */
        $oneyService = $this->container->get('oney_service');
        $simulations = $oneyService->getSimulation($amount);

        return $this->render('PayPlugOney/simulation', compact('amount', 'simulations'));
    }

    public function isSimulationValid()
    {
        $amount = (int)round($this->getRequest()->get('amount'));
        $minimumAmount = (new PayPlugOney)->getMinimumAmount();
        $maximumAmount = (new PayPlugOney)->getMaximumAmount();

        return new JsonResponse(
            [
                'isValid' => ($amount >= $minimumAmount && $amount <= $maximumAmount)
            ]
        );
    }

}