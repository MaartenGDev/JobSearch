<?php
use App\Client;
use GuzzleHttp\Client as GuzzleClient;
use MaartenGDev\Cache;
use MaartenGDev\LocalDriver;

$dir = $_SERVER['DOCUMENT_ROOT'] . '/../';
require_once $dir . '/vendor/autoload.php';

$drive = new LocalDriver($dir . '/cache/');
$cache = new Cache($drive,30);

$guzzle = new GuzzleClient(['cookies' => true]);
$client = new Client($guzzle, $cache);
$dotenv = new Dotenv\Dotenv($dir);
$app = new Silex\Application();

$dotenv->load();

$app->get('/search/{study}/{location}', function($study, $location) use($app, $client) {
    return $client->search($study, $location);
});

$app->run();
