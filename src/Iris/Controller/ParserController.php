<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;

class ParserController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/urang', array($this, 'addUniqueRang'))->bind('parser.urang');
        $controllers->get('/touchpoint', array($this, 'parseTouchpoint'))->bind('parser.touchpoint');
        return $controllers;
    }

    public function addUniqueRang(\Symfony\Component\HttpFoundation\Request $request) {
        if ($request->get('submit')) {
            $ps = $this->app['service.parser'];
            $ps->addUrang($this->app['mysqli']);
        }
        return $this->app['twig']->render('parser/uRang.twig');
    }

    public function parseTouchpoint(\Symfony\Component\HttpFoundation\Request $request) {

        if ($request->get('submit')) {
            $ps = $this->app['service.parser'];
            $nb = ($request->get('nbTouchpoint')) ? $request->get('nbTouchpoint') : 2000;
            $step = ($request->get('step')) ? $request->get('step') : 500;
            for ($i = 0; $i < $nb; $i+=$step) {
                $parserTouchpoint = $ps->getParserTouchpoint($this->app['mysqli']);
                $cleanerTouchpoint = $ps->getCleanerTouchpoint($this->app['mysqli']);
                $clock['start'] = microtime(true);
                $newParserTouchpoint = $ps->getListTouchpoint($this->app['mysqli'], $parserTouchpoint, $cleanerTouchpoint, $step);
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
            }
        }
        return $this->app['twig']->render('parser/touchpoint.twig');
    }

}
