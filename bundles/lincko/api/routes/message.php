<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/message', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerMessage:create'.$app->lincko->method_suffix
	)
	->name('message_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerMessage:read'.$app->lincko->method_suffix
	)
	->name('message_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerMessage:update'.$app->lincko->method_suffix
	)
	->name('message_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerMessage:delete'.$app->lincko->method_suffix
	)
	->name('message_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerMessage:restore'.$app->lincko->method_suffix
	)
	->name('message_restore'.$app->lincko->method_suffix);

	$app->post(
		'/recall',
		'\bundles\lincko\api\controllers\ControllerMessage:recall'.$app->lincko->method_suffix
	)
	->name('message_recall'.$app->lincko->method_suffix);

});
