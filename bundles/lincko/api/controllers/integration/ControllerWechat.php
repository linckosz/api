<?php

namespace bundles\lincko\api\controllers\integration;

use \libs\Controller;

class ControllerWechat extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function connect_post(){
		\libs\Watch::php($this->data, '$data', __FILE__, false, false, true);
	}

}
