<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/wechat', function () use ($app) {

	$app->get(
		'/connect',
		'\bundles\lincko\api\controllers\integration\ControllerWechat:connect'.$app->lincko->method_suffix
	)
	->name('wechat_connect'.$app->lincko->method_suffix);

});
