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
		$partial = NULL;
		$schema = NULL;
		$forceSchema = $data->getForceSchema();
		$lastvisit = time()-1;

		if($forceSchema==2){
			$schema = 'reset';
			$partial = $data->getLatest(0); //Setting to 0 helps to reset the full local database on client side
		} else if($forceSchema==1){
			$schema = $data->getSchema();
			$partial = $data->getLatest();
		} else {
			$partial = $data->getLatest();
		}
				

		$app->render(200, array('msg' => array('msg' => $msg, 'lastvisit' => $lastvisit, 'partial' => $partial, 'schema' => $schema),));
		return true;
	}

	public function schema_post(){
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
		$partial = $data->getMissing();
		
		$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial),));
		return true;
	}

	public function history_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 11); //You got the elements including their full history.

		$data = new Data();
		$partial = $data->getHistory();

		$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial),));
		return true;
	}

	public function force_sync_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 12); //The synchronization will be done for all contacts.

		$data = new Data();
		$data->setForceSchema();

		$app->render(200, array('msg' => array('msg' => $msg,)));
		return true;
	}

	public function force_reset_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 14); //The database reset will be done for all contacts.

		$data = new Data();
		$data->setForceReset();

		$app->render(200, array('msg' => array('msg' => $msg,)));
		return true;
	}

}
