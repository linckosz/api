<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/file', function () use ($app) {

	$app->map(
		'/create',
		'\bundles\lincko\api\controllers\ControllerFile:create'.$app->lincko->method_suffix
	)
	->via('POST', 'OPTIONS')
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
	->name('file_result'.$app->lincko->method_suffix);

	$app->get(
		'/:workspace/:uid/:type/:id/:name',
		'\bundles\lincko\api\controllers\ControllerFile:open_get'
	)
	->conditions(array(
		'workspace' => '\d+',
		'uid' => '\d+',
		'type' => 'link|thumbnail|download',
		'id' => '\d+',
		'name' => '.+',
	))
	->name('file_open_get');

	$app->post(
		'/progress/:id',
		'\bundles\lincko\api\controllers\ControllerFile:progress_post'
	)
	->conditions(array(
		'id' => '\d+',
	))
	->name('file_progress_post');

	$app->map(
		'/toto',
		function(){
			$app = \Slim\Slim::getInstance();
			$data = json_decode($app->request->getBody());
			$post = $app->request->post();
			\libs\Watch::php($data, '$data', __FILE__, false, false, true);
			\libs\Watch::php($post, '$post', __FILE__, false, false, true);
		}
	)
	->via('POST', 'OPTIONS')
	->name('file_toto'.$app->lincko->method_suffix);

});

