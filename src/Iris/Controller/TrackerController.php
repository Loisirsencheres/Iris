<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;
use Predis;

Predis\Autoloader::register();

class TrackerController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->post('/web', array($this, 'webTracking'))->bind('tracking.web');
        return $controllers;
    }

    public function webTracking(\Symfony\Component\HttpFoundation\Request $request) {
        $req = ($request->request->all());
        $touchpoint = [];
        foreach ($req as $key => $param) {
            $touchpoint[$key] = urldecode($param);
        }

        try {
            $client = new Predis\Client(array(
                'host' => $this->app['redis.host'],
                'database' => 8,
                'password' => $this->app['redis.password']
            ));
        } catch (Exception $e) {
            echo $e->getMessage();
        }
        $key = uniqid("", true);
        $client->set($key, \GuzzleHttp\json_encode($touchpoint));

        return"";
    }

}
