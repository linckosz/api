<?php

$path = dirname(__FILE__).'/..';

require_once $path.'/vendor/autoload.php';

$app = new \Slim\Slim();

require_once $path.'/config/global.php';
require_once $path.'/config/language.php';
require_once $path.'/param/common.php';
require_once $path.'/param/unique/parameters.php';

// Only invoked if mode is "production"
$app->configureMode('production', function () use ($app) {
	$app->config(array(
		'log.enable' => true,
	));
	ini_set('display_errors', '0');
	$app->lincko->data['need_invitation'] = false;
	$app->lincko->data['allow_create_user'] = true;
});

// Only invoked if mode is "development"
$app->configureMode('development', function () use ($app) {
	$app->config(array(
		'log.enable' => false,
	));
	ini_set('display_errors', '0');
	ini_set('opcache.enable', '0');
	$app->lincko->data['need_invitation'] = false;
	$app->lincko->data['allow_create_user'] = true;
	$app->lincko->showError = true; //Force to see Error message
	//Force to delay (microseconds) to simulate network slow speed
	//usleep(500000); //500ms
});

require_once $path.'/config/autoload.php';
require_once $path.'/error/errorPHP.php';
require_once $path.'/config/eloquent.php';
require_once $path.'/config/session.php';

$app->map('/:catchall', function() use ($app) {
	$app->render(404, array(
		'error' => true,
		'msg' => $app->trans->getBRUT('default', 1, 4), //Sorry, we could not understand the request.
	));
})->conditions(array('catchall' => '.*'))
->name('catchall')
->via('GET', 'POST', 'PUT', 'DELETE');

$app->run();
//Checking $app (print_r) after run can make php crashed out of memory because it contains files data
