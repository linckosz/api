<?php

namespace bundles\lincko\api\controllers\integration;

use \libs\Controller;

class ControllerIntegration extends Controller {

	protected $app = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		return true;
	}

	public function connect_post(){
		$app = $this->app;
		\libs\Watch::php(true, '$connect_post', __FILE__, __LINE__, false, false, true);
		$msg = $app->trans->getBRUT('api', 15, 15); //Hello @@user_username~~, you are signed in to your account.
		$app->render(200, array('msg' => array('msg' => $msg)));
		return true;
	}

}
