<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/integration', function () use ($app) {

	$app->post(
		'/connect',
		'\bundles\lincko\api\controllers\integration\ControllerIntegration:connect'.$app->lincko->method_suffix
	)
	->name('integration_connect'.$app->lincko->method_suffix);

	$app->post(
		'/code',
		'\bundles\lincko\api\controllers\integration\ControllerIntegration:code'.$app->lincko->method_suffix
	)
	->name('integration_code'.$app->lincko->method_suffix);

//We use get here because we do a direct connection to display the QR code
	$app->get(
		'/qrcode(/:mini)',
		'\bundles\lincko\api\controllers\integration\ControllerIntegration:qrcode'.$app->lincko->method_suffix
	)
	->conditions(array(
		'mini' => '[\w\d]+',
	))
	->name('integration_qrcode'.$app->lincko->method_suffix);

});
