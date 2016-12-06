<?php
// Category 13

namespace bundles\lincko\api\controllers;

use \libs\Controller;

class ControllerWechat extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function connect_get(){
		echo 'ok';
	}

}
