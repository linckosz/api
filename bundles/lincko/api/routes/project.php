<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/project', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerProject:create'.$app->lincko->method_suffix
	)
	->name('project_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerProject:read'.$app->lincko->method_suffix
	)
	->name('project_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerProject:update'.$app->lincko->method_suffix
	)
	->name('project_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerProject:delete'.$app->lincko->method_suffix
	)
	->name('project_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerProject:restore'.$app->lincko->method_suffix
	)
	->name('project_restore'.$app->lincko->method_suffix);

});
