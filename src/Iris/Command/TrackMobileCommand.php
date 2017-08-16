<?php

namespace Iris\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Predis;

Predis\Autoloader::register();

class TrackMobileCommand extends Command {

    /** @var EntityManagerInterface  */
    private $app;

    public function __construct(\Silex\Application $app) {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure() {
        $this
                ->setName('iris:track:mobile')
                ->setDescription('Track mobile');
    }

    protected function execute() {
        $ts = $this->app['service.tracker'];
        $lastCallBack = $ts->getLastCallBack($this->app['mysqli']);
        if($ts->getCountOnly($this->app['mysqliSSL'], $lastCallBack)>=100){
            $callBacks=$ts->getCallbacks($this->app['mysqliSSL'], $lastCallBack);
            foreach ($callBacks as $key=>$callback){
                $this->sendToRedis($callback);
                $lastCallBack=$key;
            }
            $ts->setLastCallBack($this->app['mysqli'], $lastCallBack);
        }
        else{
            return"";
        }
    }

    public function sendToRedis($touchpoint) {
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
