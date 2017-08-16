<?php

namespace Iris\Controller;

use Silex\ControllerProviderInterface;

class UpdateController implements ControllerProviderInterface {

    private $app;

    public function connect(\Silex\Application $app) {
        $this->app = $app;
        $controllers = $app["controllers_factory"];
        $controllers->get('/', array($this, 'materializeTable'))->bind('materialize.table');
        return $controllers;
    }

    public function materializeTable(\Symfony\Component\HttpFoundation\Request $request) {
        $mysqli = $this->connexion();
        $updateService = $this->app['service.update'];

        $clock['init'] = microtime(true);
        $params = $conv = $times = $models = [];
        if ($request->get('members')) {
            array_push($conv, "NouveauxMembres");
            $params['members'] = 1;
        }
        if ($request->get('bids')) {
            array_push($conv, "Bids");
            $params['bids'] = 1;
        }
        if ($request->get('connexions')) {
            array_push($conv, "Connexions");
            $params['connexions'] = 1;
        }
        if ($request->get('wins')) {
            array_push($conv, "Wins");
            $params['wins'] = 1;
        }
        if ($request->get('payments')) {
            array_push($conv, "Paiements");
            $params['payments'] = 1;
        }
        if ($request->get('va')) {
            array_push($conv, "VA");
            $params['va'] = 1;
        }
        if ($request->get('ca')) {
            array_push($conv, "CA");
            $params['ca'] = 1;
        }
        if ($request->get('linear')) {
            $models['linear'] = "ROUND(SUM(IF(q2.total,q2.CA/(q2.nb_campaign),0)))";
            $params['model']['linear'] = 1;
        }
        if ($request->get('firstclick')) {
            $models['firstclick'] = "ROUND(SUM(IF(q2.total AND q2.uRang=1,q2.CA,0)))";
            $params['model']['firstclick'] = 1;
        }
        if ($request->get('lastclick')) {
            $models['lastclick'] = "ROUND(SUM(IF(q2.total AND q2.uRang=q2.nb_campaign,q2.CA,0)))";
            $params['model']['lastclick'] = 1;
        }
        if ($request->get('ascending')) {
            $models['ascending'] = "ROUND(SUM(IF(q2.total,q2.CA*(q2.nb_campaign+1-q2.uRang)/q2.total,0)))";
            $params['model']['ascending'] = 1;
        }
        if ($request->get('descending')) {
            $models['descending'] = "ROUND(SUM(IF(q2.total,q2.CA*q2.uRang/q2.total,0)))";
            $params['model']['descending'] = 1;
        }
        if ($request->get('parabolic')) {
            $models['parabolic'] = "
                ROUND(
                    SUM(
                        IF(
                            nb_campaign>1,
                            IF(
                                MOD(nb_campaign,2),
                                CA*(ABS(uRang-(total/nb_campaign))/(((nb_campaign/2)-1)*nb_campaign/2)),
                                CA*(ABS(uRang-(total/nb_campaign))/((nb_campaign/2)*(nb_campaign/2)))
                            ),
                            1
                        )
                    )
                )
            ";
            $params['model']['parabolic'] = 1;
        }
        /*
         * Si nb_campaign >1 ALORS (Si nb_campaign PAIR alors (ABS(uRang-(total/nb_campaign))/((nb_campaign/2)-1)*nb_campaign/2) SINON (ABS(uRang-(total/nb_campaign))/((nb_campaign/2)*(nb_campaign/2))) SINON CA
         * 
         * 
         */
        if ($request->get('dateRange')) {
            $firstDate = \DateTime::createFromFormat("Y-m-d H:i:s", "2016-06-01 00:00:00");
            $now=new \DateTime();
            $explodeDate = explode(' - ', $request->get('dateRange'));
            $params['date']['start'] = $explodeDate[0];
            $params['date']['end'] = $explodeDate[1];
            $begin = new \DateTime($explodeDate[0]);
            if($begin);
            $end = new \DateTime($explodeDate[1]);
            $end = $end->modify( '+1 day' ); 
            $interval = new \DateInterval('P1D');
            $daterange = new \DatePeriod($begin, $interval, $end);
            foreach ($models as $key => $model) {
                $clock["model.start"] = microtime(true);
                foreach ($conv as $conversion) {
                    $clock["Total.start"] = microtime(true);
                    foreach ($daterange as $date) {
                        $clock["conv.start"] = microtime(true);
                        $updateService->createMaterializedTable($mysqli, $conversion, $date->format('Y-m-d'), $model, $key);
                        $clock["conv.finish"] = microtime(true);
                        $times["conv$conversion" . $date->format('Y-m-d') . $key] = array(
                            'Model' => $key,
                            'Name' => $conversion,
                            'Date' => $date->format('Y-m-d'),
                            'Temps en secondes' => round($clock["conv.finish"] - $clock["conv.start"], 2),
                        );
                    }
                    $clock["Total.end"] = microtime(true);
                    $times["Total.$conversion.$key"] = array(
                        'Model' => $key,
                        'Name' => "Total$conversion",
                        'Date' => "All$conversion",
                        'Temps en secondes' => round($clock["Total.end"] - $clock["Total.start"], 2),
                    );
                }
                $clock["model.end"] = microtime(true);
                $times["Total.$key"] = array(
                    'Model' => $key,
                    'Name' => "TotalModel",
                    'Date' => "All",
                    'Temps en secondes' => round($clock["model.end"] - $clock["model.start"], 2),
                );
            }
            $clock['Total'] = microtime(true);
            $times['Total'] = array(
                'Model' => 'All',
                'Name' => 'Total',
                'Date' => 'All',
                'Temps en secondes' => round($clock['Total'] - $clock['init'], 2),
            );
        }

        return $this->generateTable($times, $params);
    }

    function generateTable($times, $filter) {
        if (count($times) != 0) {
            reset($times);
            $first_key = key($times);
            foreach ($times[$first_key] as $key => $value) {
                $column[$key] = $key;
            }
            return $this->app['twig']->render('update.twig', ['times' => $times, 'columns' => $column, 'filters' => $filter]);
        } else {
            return $this->app['twig']->render('update.twig', ['filters' => $filter]);
        }
    }

    private function connexion() {
        //$mysqli=new mysqli("localhost","tracking","phobictracking","tracking");
        $mysqli = new \mysqli($this->app['db.host'], $this->app['db.user'], $this->app['db.password'], $this->app['db.name']);
        if (mysqli_connect_errno()) {
            die("Failed to connect to MySQL: " . mysqli_connect_error());
        }
        return $mysqli;
    }

}
