<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/user', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerUser:create'.$app->lincko->method_suffix
	)
	->name('user_create'.$app->lincko->method_suffix);

	$app->post(
		'/read',
		'\bundles\lincko\api\controllers\ControllerUser:read'.$app->lincko->method_suffix
	)
	->name('user_read'.$app->lincko->method_suffix);

	$app->post(
		'/update',
		'\bundles\lincko\api\controllers\ControllerUser:update'.$app->lincko->method_suffix
	)
	->name('user_update'.$app->lincko->method_suffix);

	$app->post(
		'/delete',
		'\bundles\lincko\api\controllers\ControllerUser:delete'.$app->lincko->method_suffix
	)
	->name('user_delete'.$app->lincko->method_suffix);

	$app->post(
		'/restore',
		'\bundles\lincko\api\controllers\ControllerUser:restore'.$app->lincko->method_suffix
	)
	->name('user_restore'.$app->lincko->method_suffix);

	$app->post(
		'/signin',
		'\bundles\lincko\api\controllers\ControllerUser:signin'.$app->lincko->method_suffix
	)
	->name('user_signin'.$app->lincko->method_suffix);

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

	$app->post(
		'/find',
		'\bundles\lincko\api\controllers\ControllerUser:find'.$app->lincko->method_suffix
	)
	->name('user_find'.$app->lincko->method_suffix);

	$app->post(
		'/invite',
		'\bundles\lincko\api\controllers\ControllerUser:invite'.$app->lincko->method_suffix
	)
	->name('user_invite'.$app->lincko->method_suffix);

	$app->post(
		'/my_user',
		'\bundles\lincko\api\controllers\ControllerUser:my_user'.$app->lincko->method_suffix
	)
	->name('user_my_user'.$app->lincko->method_suffix);

	$app->post(
		'/forgot',
		'\bundles\lincko\api\controllers\ControllerUser:forgot'.$app->lincko->method_suffix
	)
	->name('user_forgot'.$app->lincko->method_suffix);

	$app->post(
		'/reset',
		'\bundles\lincko\api\controllers\ControllerUser:reset'.$app->lincko->method_suffix
	)
	->name('user_reset'.$app->lincko->method_suffix);

	$app->post(
		'/integration',
		'\bundles\lincko\api\controllers\ControllerUser:integration'.$app->lincko->method_suffix
	)
	->name('user_integration'.$app->lincko->method_suffix);

});
