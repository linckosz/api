<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/data', function () use ($app) {

	$app->post(
		'/latest',
		'\bundles\lincko\api\controllers\ControllerData:latest'.$app->lincko->method_suffix
	)
	->name('data_latest'.$app->lincko->method_suffix);

	$app->post(
		'/schema',
		'\bundles\lincko\api\controllers\ControllerData:schema'.$app->lincko->method_suffix
	)
	->name('data_schema'.$app->lincko->method_suffix);

	$app->post(
		'/missing',
		'\bundles\lincko\api\controllers\ControllerData:missing'.$app->lincko->method_suffix
	)
	->name('data_missing'.$app->lincko->method_suffix);

	$app->post(
		'/history',
		'\bundles\lincko\api\controllers\ControllerData:history'.$app->lincko->method_suffix
	)
	->name('data_history'.$app->lincko->method_suffix);

	$app->post(
		'/force_sync',
		'\bundles\lincko\api\controllers\ControllerData:force_sync'.$app->lincko->method_suffix
	)
	->name('data_force_sync'.$app->lincko->method_suffix);

	$app->post(
		'/force_reset',
		'\bundles\lincko\api\controllers\ControllerData:force_reset'.$app->lincko->method_suffix
	)
	->name('data_force_reset'.$app->lincko->method_suffix);

	$app->post(
		'/reset_init',
		'\bundles\lincko\api\controllers\ControllerData:reset_init'.$app->lincko->method_suffix
	)
	->name('data_reset_init'.$app->lincko->method_suffix);

});
