<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/project', function () use ($app) {

	$app->post(
		'/create',
		'\bundles\lincko\api\controllers\ControllerProject:create'.$app->lincko->method_suffix
	)
	->name('project_create'.$app->lincko->method_suffix);

});
