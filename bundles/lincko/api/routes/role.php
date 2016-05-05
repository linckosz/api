<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/role', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerRole:create'.$app->lincko->method_suffix
	)
	->name('role_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerRole:read'.$app->lincko->method_suffix
	)
	->name('role_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerRole:update'.$app->lincko->method_suffix
	)
	->name('role_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerRole:delete'.$app->lincko->method_suffix
	)
	->name('role_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerRole:restore'.$app->lincko->method_suffix
	)
	->name('role_restore'.$app->lincko->method_suffix);

});
