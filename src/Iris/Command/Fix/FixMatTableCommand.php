<?php

namespace Iris\Command\Fix;

use Iris\Service\UpdateService;
use Silex\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class FixMatTableCommand extends Command {

    private $app;

    /** @var OutputInterface */
    private $output;

    public function __construct(Application $app) {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure() {
        $this->setName('iris:fix:mattable')
            ->setDescription('Add missing elem');
    }

    protected function execute(InputInterface $input,OutputInterface $output) {
        $this->output = $output;
        /** @var UpdateService $us */
        $us = $this->app['service.update'];
        $us->addUrang($this->app['mysqli']);

        $this->MaterializedTableUpdate();
    }

    private function MaterializedTableUpdate()
    {
        /** @var UpdateService $us */
        $us = $this->app['service.update'];
        $conv = ["NouveauxMembres", "Connexions", "wins", "Paiements", "VA", "CA", "Bids"];
        $models = [
            'linear' => "ROUND(SUM(IF(q2.total,q2.CA/(q2.nb_campaign),0)))",
            'firstclick' => "ROUND(SUM(IF(q2.total AND q2.uRang=1,q2.CA,0)))",
            'lastclick' => "ROUND(SUM(IF(q2.total AND q2.uRang=q2.nb_campaign,q2.CA,0)))",
            'ascending' => "ROUND(SUM(IF(q2.total,q2.CA*(q2.nb_campaign+1-q2.uRang)/q2.total,0)))",
            'descending' => "ROUND(SUM(IF(q2.total,q2.CA*q2.uRang/q2.total,0)))",
            'parabolic' => "
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
            ",
        ];
        $start = new \DateTime();
        $start->setDate(2016,5,1);
        $start->setTime(0,0,0);
        $end = new \DateTime();
        $end->setTime(23,59,59);
        $interval = new \DateInterval('P1D');
        $daterange = new \DatePeriod($start, $interval, $end);
        foreach ($daterange as $now) {
            foreach ($models as $key => $model) {
                foreach ($conv as $conversion) {
                    $us->createMaterializedTable($this->app['mysqli'], $conversion, $now->format('Y-m-d'), $model, $key);
                    if ($now->format('H') == 0) {
                        $now->sub(new \DateInterval('P1D'));
                        $us->createMaterializedTable($this->app['mysqli'], $conversion, $now->format('Y-m-d'), $model,
                            $key);
                    }
                }
            }
        }
    }
}
