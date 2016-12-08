<?php
// Category 13

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

	public function connect_get($var=false){
		ob_clean();
		header("Content-type: text/html; charset=UTF-8");
		http_response_code(200);
		echo 'Wechat connection';
		echo '<br />'.$var;
		return exit(0);
	}

}
