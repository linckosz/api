<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/test', function () use ($app) {

	$app->post(
		'/',
		'\bundles\lincko\api\controllers\ControllerTest:'.$app->lincko->method_suffix
	)
	->name('test'.$app->lincko->method_suffix);

	$app->post(
		'/email',
		'\bundles\lincko\api\controllers\ControllerTest:email'.$app->lincko->method_suffix
	)
	->name('test_email'.$app->lincko->method_suffix);

	$app->post(
		'/user',
		'\bundles\lincko\api\controllers\ControllerTest:user'.$app->lincko->method_suffix
	)
	->name('test_user'.$app->lincko->method_suffix);

});
