<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/translation', function () use ($app) {

	$app->post(
		'/auto',
		'\bundles\lincko\api\controllers\ControllerTranslation:auto'.$app->lincko->method_suffix
	)
	->name('translation_auto'.$app->lincko->method_suffix);

});
