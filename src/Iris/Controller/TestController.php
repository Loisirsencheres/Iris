<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;
use Predis;

Predis\Autoloader::register();

class TestController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/', array($this, 'test'))->bind('test');
        return $controllers;
    }

    public function test() {

        try {
            $client = new Predis\Client(array(
                'host' => $this->app['redis.host'],
                'database' => 8,
                'password' => $this->app['redis.password']
            ));
//            echo "Successfully connected to Redis<br />";
        } catch (Exception $e) {
//            echo "Couldn't connected to Redis";
            echo $e->getMessage();
        }
        $allkeys = $client->keys("*");
        foreach ($allkeys AS $redisKey){
            $value = $client->get($redisKey);
            echo $redisKey." : ".$value."</br>";
        }
        return "done";
    }

}
