<?php
ini_set('display_errors','stderr');
//Bootstrap our Silex application
$app = require __DIR__ . '/web/base.php';
$app->boot();

//Include the namespaces of the components we plan to use
use Symfony\Component\Console\Application;

$cli = new Application('Iris', '0.1');

//searchbar
$cli->add(new \Iris\Command\ParseTouchpointCommand($app));
$cli->add(new \Iris\Command\UpdateTrackingTableCommand($app));
$cli->add(new \Iris\Command\TrackTouchpointCommand($app));
$cli->add(new \Iris\Command\UpdateStatusCommand($app));
$cli->add(new \Iris\Command\TrackMobileCommand($app));
$cli->add(new \Iris\Command\Fix\FixMatTableCommand($app));
$cli->run();