<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/data', function () use ($app) {

	$app->post(
		'/latest',
		'\bundles\lincko\api\controllers\ControllerData:latest'.$app->lincko->method_suffix
	)
	->name('data_latest'.$app->lincko->method_suffix);

});
