<?php

namespace Iris\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;

class ParseTouchpointCommand extends Command {

    /** @var EntityManagerInterface  */
    private $app;

    public function __construct(\Silex\Application $app) {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure() {
        $this
                ->setName('iris:parse:touchpoint')
                ->setDescription('Parse touchpoint');
    }

    protected function execute() {
        $ps = $this->app['service.parser'];
        $parserTouchpoint = $ps->getParserTouchpoint($this->app['mysqli']);
        $cleanerTouchpoint = $ps->getCleanerTouchpoint($this->app['mysqli']);
        $lastTouchpoint = $ps->getLastTouchpoint($this->app['mysqli']);
        if ($cleanerTouchpoint == $parserTouchpoint && $lastTouchpoint - $cleanerTouchpoint >= 10000) {
            echo"clean";
            $newCleanerTouchpoint = $this->cleanTouchpoint($lastTouchpoint, $cleanerTouchpoint);
            $ps->setCleanerTouchpoint($this->app['mysqli'], $newCleanerTouchpoint);
        } else {
            echo"parse1";
            $this->parseTouchpoint($ps, $parserTouchpoint, $cleanerTouchpoint);
        }
    }

    private function parseTouchpoint($ps, $parserTouchpoint, $cleanerTouchpoint) {
        try {
            $clock['start'] = microtime(true);
            $newParserTouchpoint = $ps->getListTouchpoint($this->app['mysqli'], $parserTouchpoint, $cleanerTouchpoint, 200);
            $lock = $ps->getLock($this->app['mysqli']);
            if ($newParserTouchpoint != false && $lock == "true") {
                $ps->setLock($this->app['mysqli'], "false");
                $ps->purgeListTouchpoint();
                if ($ps->getExistingT_User($this->app['mysqli'])) {
                    $ps->getOldEvents($this->app['mysqli']);
                }
                $ps->parseTouchpoint();
                $ps->sessionUnification();
            $ps->deleteUsers($this->app['mysqli']);
            $ps->insertInBase($this->app['mysqli']);
            $clock['end'] = microtime(true);
            $ps->setParserTouchpoint($this->app['mysqli'], $newParserTouchpoint, round($clock['end'] - $clock['start'], 2));
                $ps->setLock($this->app['mysqli'], "true");
            }
        } catch (\Exception $ex) {
            file_put_contents("lol.txt", $ex->getMessage());
        }
    }

    private function cleanTouchpoint($lastTouchpoint, $cleanerTouchpoint) {

        $cs = $this->app['service.cleaner'];
        $newCleanerTouchpoint = $cs->getNewCleanerTouchpoint($this->app['mysqli'], $cleanerTouchpoint, 10000);
        $touchpointId = (($lastTouchpoint - $cleanerTouchpoint) >= 10000) ? ($newCleanerTouchpoint) : $lastTouchpoint;

        $ipList = $cs->getIp($this->app['mysqli'], 300000, 70, $touchpointId);
        if (isset($this->app['mysqli'], $ipList)) {
            $ipListNot = $cs->getIpNot($this->app['mysqli'], $ipList);
            if (isset($ipListNot)) {
                $ipList = $cs->purge($ipList, $ipListNot);
                if (isset($ipList)) {
                    $cs->delete($this->app['mysqli'], $ipList);
                }
            }
        }
        $cs->checkIfExist($this->app['mysqli'], $touchpointId);
        return $touchpointId;
    }

}
