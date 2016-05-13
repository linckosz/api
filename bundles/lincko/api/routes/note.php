<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/note', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerNote:create'.$app->lincko->method_suffix
	)
	->name('note_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerNote:read'.$app->lincko->method_suffix
	)
	->name('note_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerNote:update'.$app->lincko->method_suffix
	)
	->name('note_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerNote:delete'.$app->lincko->method_suffix
	)
	->name('note_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerNote:restore'.$app->lincko->method_suffix
	)
	->name('note_restore'.$app->lincko->method_suffix);

});