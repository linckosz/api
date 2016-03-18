<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;

class ControllerTranslation extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function auto_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$msg = $app->trans->getBRUT('api', 0, 4); //No data form received.
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return true;
		}
		$form = $data->data;
		if(isset($form->text)){
			$translator = new \libs\OnlineTranslator(true);
			$msg = $translator->autoTranslate($form->text);
			$app->render(200, array('msg' => $msg,));
		} else {
			$msg = $app->trans->getBRUT('api', 2, 6); //No text found to be translated
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return true;
		}
	}

}
