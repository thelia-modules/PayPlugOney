<?php

namespace PayPlugOney\Controller\Front;

use PayPlugOney\PayPlugOney;
use PayPlugOney\Service\OneyService;
use Thelia\Controller\Front\BaseFrontController;

class SimulationController extends BaseFrontController
{
    public function simulate()
    {
        $amount = (int)$this->getRequest()->get('amount');
        $minimumAmount = (new \PayPlugOney\PayPlugOney)->getMinimumAmount();
        $maximumAmount = (new \PayPlugOney\PayPlugOney)->getMaximumAmount();

        if ($amount < (new \PayPlugOney\PayPlugOney)->getMinimumAmount() || $amount > (new \PayPlugOney\PayPlugOney)->getMaximumAmount()) {
            return $this->render('PayPlugOney/outOfBoundSimulation', compact('minimumAmount', 'maximumAmount'));
        }

        /** @var OneyService $oneyService */
        $oneyService = $this->container->get('oney_service');
        $simulations = $oneyService->getSimulation($amount);

        return $this->render('PayPlugOney/simulation', compact('amount', 'simulations'));
    }

}