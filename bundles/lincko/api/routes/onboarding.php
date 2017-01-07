<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/onboarding', function () use ($app) {

	$app->post(
		'/next',
		'\bundles\lincko\api\controllers\ControllerOnboarding:next'.$app->lincko->method_suffix
	)
	->name('onboarding_next'.$app->lincko->method_suffix);

	$app->post(
		'/monkeyking',
		'\bundles\lincko\api\controllers\ControllerOnboarding:monkeyking'.$app->lincko->method_suffix
	)
	->name('onboarding_monkeyking'.$app->lincko->method_suffix);

});
