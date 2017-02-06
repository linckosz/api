<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/info', function () use ($app) {

	$app->post(
		'/beginning',
		'\bundles\lincko\api\controllers\ControllerInfo:beginning'.$app->lincko->method_suffix
	)
	->name('info_beginning'.$app->lincko->method_suffix);

	$app->post(
		'/action',
		'\bundles\lincko\api\controllers\ControllerInfo:action_post'
	)
	->name('info_action_post');

	$app->get(
		'/action/:id',
		'\bundles\lincko\api\controllers\ControllerInfo:action_get'
	)
	->conditions(array(
		'id' => '\d+',
	))
	->name('info_action_get');

});
