<?php

namespace libs;

abstract class Controller {

	public function __call($method, $args=array()){
		$app = \Slim\Slim::getInstance();
		$msg = $app->trans->getBRUT('default', 1, 4); //Sorry, we could not understand the request.
		if($app->lincko->jsonException){
			$app->render(404, array(
				'error' => true,
				'msg' => $msg,
			));
		} else {
			echo $msg;
		}
		$app->stop();
		return false;
	}
}
