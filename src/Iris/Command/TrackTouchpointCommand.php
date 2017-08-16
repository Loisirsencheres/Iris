<?php

namespace Iris\Command;

use Doctrine\ORM\EntityManagerInterface;
use Iris\Service\TrackerService;
use Silex\Application;
use Symfony\Component\Console\Command\Command;
use Predis;

Predis\Autoloader::register();

class TrackTouchpointCommand extends Command {

    /** @var EntityManagerInterface  */
    private $app;

    public function __construct(Application $app) {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure() {
        $this
                ->setName('iris:track:touchpoint')
                ->setDescription('Track touchpoint');
    }

    protected function execute() {
        try {
            $client = new Predis\Client(array(
                'host' => $this->app['redis.host'],
                'database' => 8,
                'password' => $this->app['redis.password']
            ));
        } catch (\Exception $e) {
            throw $e;
        }
        $allkeys = $client->keys("*");
        foreach ($allkeys AS $redisKey) {
            $touchpoint = json_decode($client->get($redisKey), true);
            $keysSort[$redisKey] = strtotime($touchpoint['created']);
        }
        if (isset($keysSort)) {
            asort($keysSort);
            $count = 0;
            foreach ($keysSort AS $key => $value) {
                $count++;
                $touchpoint = json_decode($client->get($key), true);
                $this->parseSingleTouchpoint($touchpoint);
                $client->del($key);
                if ($count == 300) {
                    break;
                }
            }
        }
        return "done";
    }

    private function parseSingleTouchpoint($touchpoint) {
        /** @var TrackerService $ts */
        $ts = $this->app['service.tracker'];
        if ($touchpoint['created'] == "0000-00-00 00:00:00" || !isset($touchpoint['created']) || $touchpoint['created'] == "") {
            $touchpoint['created'] = new \DateTime();
            $touchpoint['created'] = $touchpoint['created']->format('Y-m-d H:i:s');
        }
        if (!isset($touchpoint['agent'])) {
            $touchpoint['agent'] = "";
        }
        $touchpoint['landingPage'] = (isset($touchpoint['landingPage'])) ? $touchpoint['landingPage'] : "";
        $touchpoint['agent'] = (isset($touchpoint['agent'])) ? $touchpoint['agent'] : "";
        $touchpoint['ip'] = (isset($touchpoint['ip'])) ? $touchpoint['ip'] : "";
        if ($ts->isRobot($touchpoint['landingPage'], $touchpoint['agent'], $touchpoint['ip'])) {
            return;
        }
        if ($touchpoint["touchpoint"] == "startSession") {
            $ts->startSession($this->app['mysqli'], $touchpoint);
        } else {
            $ts->event($this->app['mysqli'], $touchpoint);
        }
    }

}
