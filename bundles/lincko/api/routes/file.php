<?php

namespace bundles\lincko\api\routes;

use \libs\Json;

$app = \Slim\Slim::getInstance();


$app->group('/file', function () use ($app) {

	$app->map(
		'/',
		'\bundles\lincko\api\controllers\ControllerFile:'.$app->lincko->method_suffix
	)
	->via('GET', 'POST', 'OPTIONS')
	->name('file'.$app->lincko->method_suffix);

	$app->map(
		'/result',
		'\bundles\lincko\api\controllers\ControllerFile:result'
	)
	->via('GET', 'POST', 'OPTIONS', 'HEAD')
	->name('file_result');

});

