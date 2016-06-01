<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/file', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerFile:create'.$app->lincko->method_suffix
	)
	->name('file_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerFile:read'.$app->lincko->method_suffix
	)
	->name('file_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerFile:update'.$app->lincko->method_suffix
	)
	->name('file_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerFile:delete'.$app->lincko->method_suffix
	)
	->name('file_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerFile:restore'.$app->lincko->method_suffix
	)
	->name('file_restore'.$app->lincko->method_suffix);

	$app->map(
		'/result',
		'\bundles\lincko\api\controllers\ControllerFile:result'
	)
	->via('GET', 'POST', 'OPTIONS', 'HEAD')
	->name('file_result');

	$app->get(
		'/:workspace/:uid/:type/:id/:name',
		'\bundles\lincko\api\controllers\ControllerFile:file_open_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'uid' => '\w+',
		'type' => 'link|thumbnail|download',
		'id' => '\d+',
		'name' => '.+\.\w+',
	))
	->name('file_open_get');

});

