<?php

namespace bundles\lincko\api\controllers;

use \bundles\lincko\api\models\libs\Data;

use \libs\Controller;

class ControllerData extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function latest_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 9); //You got the latest updates.

		$data = new Data();
		$storage = $data->getLatest();
		$lastvisit = time()-1;

		$app->render(200, array('msg' => array('msg' => $msg, 'lastvisit' => $lastvisit, 'storage' => $storage),));
		return true;
	}

	public function schema_get(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 10); //You got the latest schema.

		$data = new Data();
		$schema = $data->getSchema();

		$app->render(200, array('msg' => array('msg' => $msg, 'schema' => $schema),));
		return true;
	}

	public function missing_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 11); //You got the missing elements.

		$data = new Data();
		$missing = $data->getMissing();

		$app->render(200, array('msg' => array('msg' => $msg, 'missing' => $missing),));
		return true;
	}

}
