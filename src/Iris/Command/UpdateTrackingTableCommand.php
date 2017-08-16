<?php
namespace Iris\Command;

use Doctrine\ORM\EntityManagerInterface;
use Iris\Service\UpdateService;
use Symfony\Component\Console\Command\Command;

class UpdateTrackingTableCommand extends Command
{

    /** @var EntityManagerInterface */
    private $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('iris:update:trackingtable')
            ->setDescription('Update Tracking Table');
    }

    protected function execute()
    {
        $this->uRangUpdate();
        $this->MaterializedTableUpdate();
    }

    private function uRangUpdate()
    {
        $us = $this->app['service.update'];
        $us->addUrang($this->app['mysqli']);
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
        $now = new \DateTime();
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
