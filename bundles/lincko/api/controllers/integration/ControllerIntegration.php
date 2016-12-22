<?php

namespace bundles\lincko\api\controllers\integration;

use \libs\Controller;
use \libs\Json;

class ControllerIntegration extends Controller {

	protected $app = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		return true;
	}

	public function connect_post(){
		$app = $this->app;
		$json = new Json('Third party connection succeed!', false, 200, false, false, array(), false);
		$json->render(200);
		return true;
	}

}
