<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class StatusController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/', array($this, 'status'))->bind('status');
        return $controllers;
    }

    function status(Request $request) {
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
