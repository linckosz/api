<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/debug', function () use ($app) {
	
	if($app->getMode()==='development'){
		$app->get('/md5', function () use($app) {
			include($app->lincko->path.'/error/md5.php');
		})
		->name('debug_md5_get');
	}

});
