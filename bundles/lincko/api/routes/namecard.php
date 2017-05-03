<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/namecard', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerNamecard:create'.$app->lincko->method_suffix
	)
	->name('namecard_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerNamecard:read'.$app->lincko->method_suffix
	)
	->name('namecard_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerNamecard:update'.$app->lincko->method_suffix
	)
	->name('namecard_update'.$app->lincko->method_suffix);

	$app->post(
		'/change',
		'\bundles\lincko\api\controllers\ControllerNamecard:change'.$app->lincko->method_suffix
	)
	->name('namecard_change'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerNamecard:delete'.$app->lincko->method_suffix
	)
	->name('namecard_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerNamecard:restore'.$app->lincko->method_suffix
	)
	->name('namecard_restore'.$app->lincko->method_suffix);

});
