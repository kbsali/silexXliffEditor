<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__.'/../vendor/silex.phar';
require_once __DIR__.'/helpers.php';

$app = new Silex\Application();

$app['autoloader']->registerNamespaces(array(
    'Symfony' => __DIR__.'/../vendor',
));
$app['autoloader']->register();

$app->register(new Silex\Extension\TwigExtension(), array(
    'twig.path'       => __DIR__.'/../views',
    'twig.class_path' => __DIR__.'/../vendor/twig/lib',
));

$app->register(new Silex\Extension\SessionExtension());

return $app;