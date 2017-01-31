<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/info', function () use ($app) {

	$app->post(
		'/beginning',
		'\bundles\lincko\api\controllers\ControllerInfo:beginning'.$app->lincko->method_suffix
	)
	->name('info_beginning'.$app->lincko->method_suffix);

});
