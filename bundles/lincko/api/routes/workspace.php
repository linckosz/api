<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/workspace', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerWorkspace:create'.$app->lincko->method_suffix
	)
	->name('workspace_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerWorkspace:read'.$app->lincko->method_suffix
	)
	->name('workspace_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerWorkspace:update'.$app->lincko->method_suffix
	)
	->name('workspace_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerWorkspace:delete'.$app->lincko->method_suffix
	)
	->name('workspace_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerWorkspace:restore'.$app->lincko->method_suffix
	)
	->name('workspace_restore'.$app->lincko->method_suffix);

});
