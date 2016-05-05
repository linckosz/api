<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/chat', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerChat:create'.$app->lincko->method_suffix
	)
	->name('chat_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerChat:read'.$app->lincko->method_suffix
	)
	->name('chat_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerChat:update'.$app->lincko->method_suffix
	)
	->name('chat_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerChat:delete'.$app->lincko->method_suffix
	)
	->name('chat_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerChat:restore'.$app->lincko->method_suffix
	)
	->name('chat_restore'.$app->lincko->method_suffix);

});
