<?php

namespace libs;

abstract class Controller {

	public function __call($method, $args=array()){
		$app = \Slim\Slim::getInstance();
		$msg = 'Sorry, we could not understand the request.'; //Cannot use translation because we don't know which bundle will be loaded
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
