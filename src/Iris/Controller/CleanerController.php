<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class CleanerController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/', array($this, 'cleaner'))->bind('cleaner');
        return $controllers;
    }

    function cleaner(Request $request) {
        $cleanerService = $this->app['service.cleaner'];
        $filter = $times = [];
        if ($request->get('range') && $request->get('minimum')){
            $minimum = ($request->get('minimum')>=100)?$request->get('minimum'):100;
            $explode = explode(";", $request->get('range'));
            $nbTouchpoint = ($explode[1]>=300000&&$explode[1]<=1000000)?$explode[1]:300000;

            $ipList = $cleanerService->getIp($this->app['mysqli'], $nbTouchpoint, $minimum);
            if (isset($this->app['mysqli'], $ipList)) {
                $ipListNot = $cleanerService->getIpNot($this->app['mysqli'], $ipList);
                if (isset($ipListNot)) {
                    $ipList = $cleanerService->purge($ipList, $ipListNot);
                    if (isset($ipList)) {
                        $cleanerService->delete($this->app['mysqli'], $ipList);
                    }
                }
            }
        }
        return $this->generateTable($times, $filter);
    }

    private function generateTable($times, $filter) {
        if (count($times) != 0) {
            reset($times);
            $first_key = key($times);
            foreach ($times[$first_key] as $key => $value) {
                $column[$key] = $key;
            }
            return $this->app['twig']->render('cleaner.twig', ['times' => $times, 'columns' => $column, 'filters' => $filter]);
        } else {
            return $this->app['twig']->render('cleaner.twig', ['filters' => $filter]);
        }
    }
}
