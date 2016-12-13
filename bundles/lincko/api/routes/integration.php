<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/integration', function () use ($app) {

	$app->post(
		'/connect',
		'\bundles\lincko\api\controllers\integration\ControllerIntegration:connect'.$app->lincko->method_suffix
	)
	->name('integration_connect'.$app->lincko->method_suffix);

});
