<?php

namespace bundles\lincko\api\middlewares;

//This class help to add information manually if we need (debug purpose)
class JsonApiView extends \JsonApiView {
	public function render($status=200, $data = NULL) {
		$app = \Slim\Slim::getInstance();
		
		//Clean the output to keep only the last Json message
		ob_clean();

		//Debug message must be a Global variable
		//$app = \Slim\Slim::getInstance();
		//$app->flashNow('debug', 'A debug message');

		if(isset($app->lincko->securityFlash['public_key']) && isset($app->lincko->securityFlash['private_key'])){
			$app->flashNow('public_key', $app->lincko->securityFlash['public_key']);
			$app->flashNow('private_key', $app->lincko->securityFlash['private_key']);
		}
		parent::render($status, $data);
	}
}

class JsonApi extends \Slim\Middleware {

	protected $app = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$app->view(new JsonApiView());
		//Warning: The Middleware JsonApiMiddleware can send error message by Json to the final user (security issue). We must rewrite error handling after the load of this middleware.
		$app->add(new \JsonApiMiddleware());
		return true;
	}

	public function call() {
		$this->next->call();
	}
	
}