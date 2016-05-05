<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/comment', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerComment:create'.$app->lincko->method_suffix
	)
	->name('comment_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerComment:read'.$app->lincko->method_suffix
	)
	->name('comment_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerComment:update'.$app->lincko->method_suffix
	)
	->name('comment_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerComment:delete'.$app->lincko->method_suffix
	)
	->name('comment_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerComment:restore'.$app->lincko->method_suffix
	)
	->name('comment_restore'.$app->lincko->method_suffix);

	$app->post(
		'/recall',
		'\bundles\lincko\api\controllers\ControllerComment:recall'.$app->lincko->method_suffix
	)
	->name('comment_recall'.$app->lincko->method_suffix);

});
