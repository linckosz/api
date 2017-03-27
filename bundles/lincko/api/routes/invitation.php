<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/invitation', function () use ($app) {

	$app->post(
		'/email',
		'\bundles\lincko\api\controllers\ControllerInvitation:email'.$app->lincko->method_suffix
	)
	->name('invitation_email'.$app->lincko->method_suffix);

});
