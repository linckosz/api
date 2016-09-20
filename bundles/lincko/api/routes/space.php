<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/space', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerSpace:create'.$app->lincko->method_suffix
	)
	->name('space_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerSpace:read'.$app->lincko->method_suffix
	)
	->name('space_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerSpace:update'.$app->lincko->method_suffix
	)
	->name('space_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerSpace:delete'.$app->lincko->method_suffix
	)
	->name('space_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerSpace:restore'.$app->lincko->method_suffix
	)
	->name('space_restore'.$app->lincko->method_suffix);

});
