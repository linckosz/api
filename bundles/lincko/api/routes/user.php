<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/user', function () use ($app) {

	$app->post(
		'/signin',
		'\bundles\lincko\api\controllers\ControllerUser:signin'.$app->lincko->method_suffix
	)
	->name('user_signin'.$app->lincko->method_suffix);

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerUser:create'.$app->lincko->method_suffix
	)
	->name('user_create'.$app->lincko->method_suffix);

	$app->post(
		'/signout',
		'\bundles\lincko\api\controllers\ControllerUser:signout'.$app->lincko->method_suffix
	)
	->name('user_signout'.$app->lincko->method_suffix);

	$app->post(
		'/resign',
		'\bundles\lincko\api\controllers\ControllerUser:resign'.$app->lincko->method_suffix
	)
	->name('user_resign'.$app->lincko->method_suffix);

});
