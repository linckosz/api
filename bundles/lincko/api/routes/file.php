<?php

namespace bundles\lincko\api\routes;

use \libs\Json;

$app = \Slim\Slim::getInstance();


$app->group('/file', function () use ($app) {

	$app->map(
		'/',
		'\bundles\lincko\api\controllers\ControllerFile:'.$app->lincko->method_suffix
	)
	->via('GET', 'POST')
	->name('file'.$app->lincko->method_suffix);

});
