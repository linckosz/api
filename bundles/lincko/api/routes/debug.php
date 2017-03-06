<?php

namespace bundles\lincko\api\routes;

$app = \Slim\Slim::getInstance();

$app->group('/debug', function () use ($app) {
	
	if($app->getMode()==='development'){

		$app->get('/', function () use($app) {
			$data = NULL; //Just in order to avoid a bug if we call it in debug.php
			include($app->lincko->path.'/error/debug.php');
		})
		->name('debug_get');
		
		$app->get('/md5', function () use($app) {
			include($app->lincko->path.'/error/md5.php');
		})
		->name('debug_md5_get');
	}

});
