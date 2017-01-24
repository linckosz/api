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
		'/refresh_history',
		'\bundles\lincko\api\controllers\ControllerData:refresh_history'.$app->lincko->method_suffix
	)
	->name('data_refresh_history'.$app->lincko->method_suffix);

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
		'/force_perm',
		'\bundles\lincko\api\controllers\ControllerData:force_perm'.$app->lincko->method_suffix
	)
	->name('data_force_perm'.$app->lincko->method_suffix);

	$app->post(
		'/viewed',
		'\bundles\lincko\api\controllers\ControllerData:viewed'.$app->lincko->method_suffix
	)
	->name('data_viewed'.$app->lincko->method_suffix);

	$app->post(
		'/settings',
		'\bundles\lincko\api\controllers\ControllerData:settings'.$app->lincko->method_suffix
	)
	->name('data_settings'.$app->lincko->method_suffix);

	$app->get(
		'/resume/hourly',
		'\bundles\lincko\api\controllers\ControllerData:resume_hourly_get'
	)
	->name('data_resume_hourly_get');

	$app->get(
		'/unlock',
		'\bundles\lincko\api\controllers\ControllerData:unlock_get'
	)
	->name('data_unlock_get');

	$app->post(
		'/error',
		'\bundles\lincko\api\controllers\ControllerData:error_post'
	)
	->name('data_error_post');

});
