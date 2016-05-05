<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/task', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerTask:create'.$app->lincko->method_suffix
	)
	->name('task_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerTask:read'.$app->lincko->method_suffix
	)
	->name('task_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerTask:update'.$app->lincko->method_suffix
	)
	->name('task_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerTask:delete'.$app->lincko->method_suffix
	)
	->name('task_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerTask:restore'.$app->lincko->method_suffix
	)
	->name('task_restore'.$app->lincko->method_suffix);

});
