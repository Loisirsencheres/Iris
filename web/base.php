<?php

use Igorw\Silex\ConfigServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;

require_once __DIR__ . '/../vendor/autoload.php';
$app = new Silex\Application();
$app->register(new ConfigServiceProvider(__DIR__ . "/../app/config/config.yml"));
$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../src/Iris/Ressources/view',
));
$app->register(new ServiceControllerServiceProvider());
// Profiler, needs TWIG
if ($app['env'] === 'dev') {
    if (isset($_GET['noProfiler']) && $_GET['noProfiler'] == 1) {
        $_COOKIE['noProfiler'] = 1;
    }
    if (!isset($_COOKIE['noProfiler'])) {
        $app->register(new HttpFragmentServiceProvider()); // Required with Symfony 2.8 to get the web profiler
        $app->register(new WebProfilerServiceProvider(), array(
            'profiler.cache_dir' => '/ass/cache/iris/var/cache/profiler',
            'profiler.mount_prefix' => '/_profiler', // this is the default
        ));
    }
}

$app->register(new UrlGeneratorServiceProvider());
$app->register(new \Iris\Provider\IrisServiceProvider());

$app['mysqli'] = new \mysqli($app['db.host'], $app['db.user'], $app['db.password'], $app['db.name']);
if (mysqli_connect_errno()) {
    die("Failed to connect to MySQL: " . mysqli_connect_error());
}

if (isset($app['user_rw'])) {
    $username = $app['user_rw'];
}
if (isset($app['pass_rw'])) {
    $password = $app['pass_rw'];
}
if (isset($app['host_rw'])) {
    $host = $app['host_rw'];
}

if ($app['env'] !== "dev") {
    $conn = mysqli_init();

    if (isset($app['cert']) && file_exists($app['cert'])) {
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
        $conn->ssl_set(null, null, $app['cert'], null, null);
    }

    if (!($conn->real_connect($host, $username, $password, null, '3306', null, isset($app['cert']) ? MYSQLI_CLIENT_SSL : null))) {
        throw new Exception('Could not connect to Mysql: ' . $conn->error);
    }

    if (isset($app['database'])) {
        if (!$conn->select_db($app['database'])) {
            throw new Exception('Could not select Mysql database: ' . $report->conn->error);
        }
    }
    $app['mysqliSSL'] = $conn;
}

$redisConf = [
    'host' => $app['redis.host'],
];

if ($app['redis.password'] != '') {
    $redisConf['password'] = $app['redis.password'];
}

$predisClients['session'] = [
    'parameters' => $redisConf + ['database' => 0],
    'options' => array(
        'prefix' => 'sessions:'
    )
];

$app->register(new Predis\Silex\ClientsServiceProvider(), ['predis.clients' => $predisClients]);


$app->get("/", function() use ($app) {
    return $app['twig']->render('main.twig');
})->bind('dashboard');
$app->mount('/conversion', new \Iris\Controller\ConversionController($app));
$app->mount('/update', new \Iris\Controller\UpdateController($app));
//$app->mount('/cleaner', new \Iris\Controller\CleanerController($app)); 
//$app->mount('/parser', new \Iris\Controller\ParserController($app));
$app->mount('/tracker', new \Iris\Controller\TrackerController($app));
$app->mount('/test', new \Iris\Controller\TestController($app));
return $app;
