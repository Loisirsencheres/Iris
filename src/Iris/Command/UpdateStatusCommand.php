<?php

namespace Iris\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;

class UpdateStatusCommand extends Command {

    /** @var EntityManagerInterface  */
    private $app;

    public function __construct(\Silex\Application $app) {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure() {
        $this
                ->setName('iris:update:status')
                ->setDescription('Update status');
    }

    protected function execute() {
        $this->lastUpdate();
    }

    private function lastUpdate() {
        $tables = [
            't_Sessions'
            , 't_Registers'
            , 't_Logins'
            , 't_Bids'
            , 't_Wins'
            , 't_Payments'];
        foreach ($tables as $table) {
            $query = "
                UPDATE toolBox t
                SET value=(
                    SELECT MAX(created)
                    FROM $table
                    )
                WHERE
                    t._key='last_$table'";
            if (!$this->app['mysqli']->query($query)) {
                printf("Message d'erreur : %s\n", $this->app['mysqli']->error);
            }
        }
    }

}
