<?php
$app = require_once __DIR__ . '/base.php';

if (!isset($_SERVER['PHP_AUTH_USER']) || !isset($_SERVER['PHP_AUTH_PW']) || ($_SERVER['PHP_AUTH_USER'] !== $app['user'] ) || ($_SERVER['PHP_AUTH_PW'] !== $app['password'])) {
    header('WWW-Authenticate: Basic realm="Authentifiez vous"');
    header('HTTP/1.0 401 Unauthorized');
    echo '<html><head>';
    echo '<title>Nope</title></head><body>';
    echo '<h1>Nope</h1>';
    echo '</body></html>';
    exit;
}

$app->run();
