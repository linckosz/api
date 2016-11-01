<?php

namespace bundles\lincko\api\controllers;

use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Settings;

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
		$user = Users::getUser();
		$partial = NULL;
		$schema = NULL;
		$info = NULL;
		$data_lastvisit = $data->getTimestamp();
		$lastvisit = time();

		if($user->getForceSchema() > $data_lastvisit){
			$info = 'reset';
			$partial = $data->getLatest(0); //Setting to 0 helps to reset the full local database on client side
		} else if($user->getCheckSchema() > $data_lastvisit){
			$schema = $data->getSchema();
			$partial = $data->getLatest();
		} else {
			$partial = $data->getLatest();
		}
		//If last visit is <0, it's considered as a 'reset' request
		if($data->getTimestamp()<=0){
			$info = 'reset';
		}	

		$app->render(200, array('msg' => array('msg' => $msg, 'lastvisit' => $lastvisit, 'partial' => $partial, 'schema' => $schema, 'info' => $info),));
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
		$info = 'missing';
		
		$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => $info),));
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

		Users::getUser()->setForceSchema();

		$app->render(200, array('msg' => array('msg' => $msg,)));
		return true;
	}

	public function force_reset_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 14); //The database reset will be done for all contacts.

		Users::getUser()->setForceReset();

		$app->render(200, array('msg' => array('msg' => $msg,)));
		return true;
	}

	public function noticed_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 16); //Notifications updated.

		if(isset($this->data->data)){
			$list = array();
			foreach ($this->data->data as $string => $timestamp) {
				if(preg_match("/^([a-z_]+)_(\d+)$/ui", $string, $matches)){
					$type = $matches[1];
					$id = $matches[2];
					$list[$type][$id] = $timestamp;
				}
			}
			$force_partial = History::historyNoticed($list);
			$data = new Data();
			$partial = $data->getMissing($force_partial);
			$info = 'noticed';
			$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => $info),));
		} else {
			$app->render(200, array('msg' => array('msg' => $msg,)));
		}
		
		return true;
	}

	public function viewed_post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 17); //Elements viewed.

		if(isset($this->data->data)){
			$force_partial = false;
			$uid = $app->lincko->data['uid'];
			foreach ($this->data->data as $string => $value) {
				if(preg_match("/^([a-z_]+)_(\d+)$/ui", $string, $matches)){
					$type = $matches[1];
					$id = $matches[2];
					$class = Users::getClass($type);
					if($class){
						if($model = $class::find($id)){
							if($model->viewed()){
								if(!$force_partial){ $force_partial = new \stdClass; }
								if(!isset($force_partial->$uid)){ $force_partial->$uid = new \stdClass; }
								if(!isset($force_partial->$uid->$type)){ $force_partial->$uid->$type = new \stdClass; }
								if(!isset($force_partial->$uid->$type->$id)){ $force_partial->$uid->$type->$id = new \stdClass; }
							}
						}
					}
				}
			}
			$data = new Data();
			$partial = $data->getMissing($force_partial);
			$info = 'viewed';
			$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => $info),));
		} else {
			$app->render(200, array('msg' => array('msg' => $msg,)));
		}

		return true;
	}

	public function settings_post(){
		$app = $this->app;
		$data = $this->data;
		$lastvisit = time();
		if(isset($data->data) && isset($data->data->settings)){
			$settings = Settings::getMySettings();
			$settings->setup = $data->data->settings;
			$dirty = $settings->getDirty();
			if(count($dirty)>0){
				if($settings->save()){
					$msg = array('msg' => 'Settings recorded');
					$data = new Data();
					$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);
					return true;
				}
			}
		}
		$app->render(200, array('show' => false, 'msg' => 'Settings not recorded', 'error' => true));
		return true;
	}

	public function resume_hourly_get(){
		$data = Data::getResume();
		//print_r($data);
		echo "\n<br />\n<br />\nResume<br />\n..........................................................\n\n";
		exit(0);
		return true;
	}

}
