<?php
namespace Iris\Provider;

use Iris\Service\UpdateService;
use Iris\Service\ParserService;
use Iris\Service\CleanerService;
use Iris\Service\TrackerService;
use Iris\Service\StatusService;
use Silex\Application;
use Silex\ServiceProviderInterface;

class IrisServiceProvider implements ServiceProviderInterface
{
      /**
     * (non-PHPdoc)
     * @see \Silex\ServiceProviderInterface::register()
     */
    public function register(Application $app){
        $app['service.update'] = $app->share(function () use ($app) {
            return new UpdateService();
        });
        $app['service.parser'] = $app->share(function () use ($app) {
            return new ParserService();
        });
        $app['service.cleaner'] = $app->share(function () use ($app) {
            return new CleanerService();
        });
        $app['service.tracker'] = $app->share(function () use ($app) {
            return new TrackerService();
        });
        $app['service.status'] = $app->share(function () use ($app) {
            return new StatusService();
        });
    }
    public function boot(Application $app)
    {
    }
}
