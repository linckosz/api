<?php

$path = dirname(__FILE__).'/..';

require_once $path.'/vendor/autoload.php';

$app = new \Slim\Slim(array(
	'cookies.encrypt' => true, //Must use $app->getCookie('foo', false);
	'cookies.secret_key' => 'au6G7dbSh87Ws',
	'cookies.lifetime' => '365 days',
	'cookies.secure' => true,
	'cookies.path' => '/',
	'cookies.httponly' => true,
	'templates.path' => '..',
	'debug' => false,
));

function my_autoload($pClassName){
	$app = \Slim\Slim::getInstance();
	$pClassName = str_replace('\\', '/', $pClassName);
	if(file_exists($app->lincko->path.'/'.$pClassName.'.php')){
		include_once($app->lincko->path.'/'.$pClassName.'.php');
	}
}

spl_autoload_register('my_autoload');

require_once $path.'/config/language.php';
require_once $path.'/param/parameters.php';

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
	$app->config(array(
		'log.enable' => true,
	));
	ini_set('display_errors', '0');
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
	$app->config(array(
		'log.enable' => false,
	));
	ini_set('display_errors', '0');
	ini_set('opcache.enable', '0');
	//Force to delay (microseconds) to simulate network slow speed
	usleep(500000); //500ms
});

require_once $path.'/config/autoload.php' ;
require_once $path.'/error/errorPHP.php';
require_once $path.'/config/eloquent.php';
require_once $path.'/config/session.php';

$app->get('/:catchall', function() use ($app) {
	$app->render(404, array(
		'error' => true,
		'msg' => 'Sorry, we could not understand the request.', //Cannot use translation because we don't know which bundle will be loaded
	));
})->conditions(array('catchall' => '.*'))
->name('catchall');

$app->run();
