<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Settings;
use \bundles\lincko\api\models\libs\Updates;
use \bundles\lincko\api\models\libs\ModelLincko;
use Carbon\Carbon;
use \libs\Translation;

class Onboarding {

	protected static $settings = NULL;

	protected static $onboarding = NULL;

	protected $data = NULL;

	protected $json = array();

	public function __construct(){
		$app = ModelLincko::getApp();
		$data = json_decode($app->request->getBody());
		if(!$data && $post = (object) $app->request->post()){
			if(isset($post->data) && is_string($post->data)){
				$post->data = json_decode($post->data);
			}
			$data = $post;
		}
		$this->json = array(
			'api_key' => $data->api_key, //Software authorization key
			'public_key' => $app->lincko->security['public_key'], //User public key
			'data' => array(),
			'method' => 'POST', //Record the type of request (GET, POST, DELETE, etc.)
			'language' => $data->language, //By default use English
			'fingerprint' => $data->fingerprint, //A way to identify which browser the user is using, help to avoid cookies copy/paste fraud
			'workspace' => $data->workspace, //the url (=ID unique string) of the workspace, by default use "Shared workspace"
		);
		$this->data = $data;
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		return true;
	}

	protected function loadOnboarding(){
		//get Settings
		if(is_null(self::$settings)){
			self::$settings = Settings::getMySettings();
		}
		$settings = self::$settings;

		//get Onboarding settings
		$onboarding = self::$onboarding;
		if(!is_object($onboarding)){
			$onboarding = new \stdClass;
			if(!empty($settings->onboarding)){
				$onboarding = json_decode($settings->onboarding);
				if(empty($onboarding)){
					$onboarding = new \stdClass;
				}
			}
			self::$onboarding = $onboarding;
		}
	}

	//Settings helps to keep track of onboarding elements
	protected function setOnboarding($item, $rank){
		$this->loadOnboarding();
		$onboarding = self::$onboarding;

		$type = $item->getTable();
		$id = $item->id;
		if(!isset($onboarding->$type)){
			$onboarding->$type = new \stdClass;
		}
		$onboarding->$type->$rank = $id;
	}

	//Settings helps to keep track of onboarding elements
	protected function resetOnboarding(){
		$this->loadOnboarding();
		$settings = self::$settings;
		self::$onboarding = new \stdClass;
		$settings->onboarding = json_encode(new \stdClass, JSON_UNESCAPED_UNICODE);
		$settings->save();
	}

	//Settings helps to keep track of onboarding elements
	protected function runOnboarding($sequence, $run=true){
		$this->loadOnboarding();
		$onboarding = self::$onboarding;

		$sequence = intval($sequence);
		$run = (bool) $run;
		if(!isset($onboarding->sequence)){
			$onboarding->sequence = new \stdClass;
		}
		$onboarding->sequence->$sequence = $run;
	}

	protected function saveOnboarding(){
		$this->loadOnboarding();
		$settings = self::$settings;
		
		//save Onboarding settings
		if(is_object(self::$onboarding)){
			$settings->onboarding = json_encode(self::$onboarding, JSON_UNESCAPED_UNICODE);
			$settings->save();
		}
	}

	protected function getOnboarding($type, $rank){
		$this->loadOnboarding();
		$onboarding = self::$onboarding;
		if(isset($onboarding->$type) && isset($onboarding->$type->$rank)){
			return $onboarding->$type->$rank; //Return the ID
		}
		return false;
	}

	public function isAnswered($id){
		$is_answered = true;
		if(is_numeric($id) && $item = Comments::getModel($id)){
			if($onboarding = json_decode($item->comment)){
				if(isset($onboarding->ob)){
					foreach ($onboarding->ob as $key => $value) {
						foreach ($value as $value2) {
							$is_answered = false;
							break;
						}
						if(!$is_answered){
							break;
						}
					}
				}
			}
		}
		return $is_answered;
	}

	public function answered($id){
		if(is_numeric($id) && $id>0 && $item = Comments::getModel($id)){
			if($onboarding = json_decode($item->comment)){
				if(isset($onboarding->ob)){
					foreach ($onboarding->ob as $key => $value) {
						$onboarding->ob->$key = new \stdClass; //Clear all answer to only display the question
					}
					$item->comment = json_encode($onboarding, JSON_UNESCAPED_UNICODE);
					$item->brutSave();
					$item->touchUpdateAt();
				}
			}
		}
	}

	public function asyncMonkeyKing($project_new, $links){
		$app = ModelLincko::getApp();
		if(isset($project_new->id) && isset($this->json['data'])){
			$this->json['data']['pid'] = $project_new->id;
			$this->json['public_key'] = Authorization::getPublicKey($this->data->fingerprint);
			$users_tables = array();
			$users_tables[$app->lincko->data['uid']] = array();
			foreach ($links as $table => $list) {
				$users_tables[$app->lincko->data['uid']][$table] = true;
				if(!isset($this->json['data']['links'])){
					$this->json['data']['links'] = array();
				}
				$this->json['data']['links'][$table] = array();
				foreach ($list as $item) {
					$id = $item->id;
					$this->json['data']['links'][$table][$id] = $id;
					if(isset($item->created_by)){
						$item->created_by = 1; //Monkey King
					}
					if(isset($item->updated_by)){
						$item->updated_by = 1; //Monkey King
					}
					if(isset($item->deleted_by)){
						$item->deleted_by = 1; //Monkey King
					}
					if(isset($item->approved_by)){
						$item->approved_by = 1; //Monkey King
					}
					$item->saveHistory(false);
					$item->brutSave();
				}
			}
			Updates::informUsers($users_tables);

			//Assign tasks to the user
			$task_pivot = new \stdClass;
			$task_pivot->{'users>in_charge'} = new \stdClass;
			$task_pivot->{'users>in_charge'}->{$app->lincko->data['uid']} = true;
			$task_pivot->{'users>approver'} = new \stdClass;
			$task_pivot->{'users>approver'}->{$app->lincko->data['uid']} = true;

			//Projects::saveSkipper(true);

			$users_tables = array();
			$users_tables[$app->lincko->data['uid']] = array();
			foreach ($links as $table => $list) {
				if($table=='tasks'){
					$users_tables[$app->lincko->data['uid']][$table] = true;
					foreach ($list as $item) {
						//Assign tasks
						$item->forceGiveAccess(true);
						$item->pivots_format($task_pivot, false);
						$item->saveHistory(false);
						$item->save();
					}
				}
			}
			Projects::saveSkipper(false);
			$project_new->setPerm();
			$users_tables = $project_new->touchUpdateAt($users_tables, false, true);
			Updates::informUsers($users_tables);

			/*
			//For some users, the setPerm was empty
			$url = $app->environment['slim.url_scheme'].'://'.$app->request->headers->Host.'/onboarding/monkeyking';
			$data = json_encode($this->json);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
			curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 1); //Cannot use MS, it will crash the request
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
			curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json; charset=UTF-8',
					'Content-Length: ' . mb_strlen($data),
				)
			);

			$verbose_show = false;
			if($verbose_show){
				$verbose = fopen('php://temp', 'w+');
				curl_setopt($ch, CURLOPT_VERBOSE, true);
				curl_setopt($ch, CURLOPT_STDERR, $verbose);
				curl_setopt($ch, CURLOPT_TIMEOUT, 60); //Increase the time to make sure we can display the result
			}

			$result = curl_exec($ch);

			if($verbose_show){
				\libs\Watch::php(json_decode($data), $url, __FILE__, __LINE__, false, false, true);
				\libs\Watch::php(curl_getinfo($ch), '$ch', __FILE__, __LINE__, false, false, true);
				rewind($verbose);
				\libs\Watch::php(stream_get_contents($verbose), '$verbose', __FILE__, __LINE__, false, false, true);
				fclose($verbose);
				\libs\Watch::php($result, '$result', __FILE__, __LINE__, false, false, true);
			}

			@curl_close($ch);
			*/
		}
		return true;
	}

	//This code is run after display to insure the user will go inside the account before everything is done
	public function changeMonkeyKing(){
		$app = ModelLincko::getApp();
		if(isset($this->data->data->pid) && isset($this->data->data->links) && $project_new = Projects::withTrashed()->find($this->data->data->pid)){
			$links = $this->data->data->links;

			//Assign tasks to the user
			$task_pivot = new \stdClass;
			$task_pivot->{'users>in_charge'} = new \stdClass;
			$task_pivot->{'users>in_charge'}->{$app->lincko->data['uid']} = true;
			$task_pivot->{'users>approver'} = new \stdClass;
			$task_pivot->{'users>approver'}->{$app->lincko->data['uid']} = true;

			Projects::saveSkipper(true);

			$users_tables = array();
			$users_tables[$app->lincko->data['uid']] = array();
			foreach ($links as $table => $list) {
				if($table=='tasks'){
					$users_tables[$app->lincko->data['uid']][$table] = true;
					foreach ($list as $id) {
						if($class = Projects::getClass($table)){
							if($item = $class::withTrashed()->find($id)){
								//Assign tasks
								$item->pivots_format($task_pivot, false);
								$item->saveHistory(false);
								$item->save();
							}
						}
					}
				}
			}
			Projects::saveSkipper(false);
			$users_tables = $project_new->touchUpdateAt($users_tables, false, true);
			Updates::informUsers($users_tables);
			echo 'ok';
			return exit(0);
		} else {
			echo 'error';
			return exit(0);
		}
	}

	//Launch the next onboarding
	public function next($next, $answer=false, $temp_id=''){
		$app = ModelLincko::getApp();

		//the user answered the question
		if($answer){
			$item = new Comments();
			$item->temp_id = $temp_id;
			$item->comment = $answer;
			$item->parent_type = 'projects';
			if($item->parent_id = $this->getOnboarding('projects', 1)){
				$item->save();
			}
			unset($item);
		}

		if(!is_numeric($next)){
			return false;
		}

		$next = ''.$next; //Convert it to string for object key

		//This is the entry where to start onboarding system (Initialiaze the first onboarding)
		if($next==10101){

			$translation = new Translation;
			$translation->getList('default');
			$default_lang = $translation->getDefaultLanguage();

			$clone_id = -1;
			if($app->lincko->domain=='lincko.com'){
				$clone_id = 1589; //English
				if($default_lang == 'zh-chs' || $default_lang == 'zh-cht'){
					$clone_id = 1605; //Chinese
				} else if($default_lang == 'fr'){
					$clone_id = 1844; //French
				} else if($default_lang == 'ko'){
					$clone_id = 1823; //Korean
				}
			} else if($app->lincko->domain=='lincko.co'){
				$clone_id = 980;
			} else if($app->lincko->domain=='lincko.cafe'){
				$clone_id = 1973;
			} else {
				//Stop the sequence
				$this->runOnboarding(1, false);
			}

			//Reset onboarding
			$this->resetOnboarding();

			//Create a project
			/*
				toto => 
				The idea is to immediatly prepare an onboarding project (field "onboard" at 1 if available),
				Then, once the user click on start, we manually:
					- Add a pivot for the user
					- Add string in each _perm with default value (don't run setPerm)
					- Add string in extra for _perm (don't null extra)
			*/
			if(!$this->getOnboarding('projects', 1)){
				if($project_ori = Projects::find($clone_id)){

					//initialize project pivot
					$pivot = new \stdClass;
					$pivot->{'users>access'} = new \stdClass;
					$pivot->{'users>access'}->{'1'} = true; //Attach the Monkey King
					$pivot->{'users>access'}->{$app->lincko->data['uid']} = true; //Make sure the user itself is attached

					Projects::saveSkipper(true);
					$links = array();
					$project_new = $project_ori->clone(false, array(), $links); //7s
					Projects::saveSkipper(false);
					$project_new->title = $project_ori->title;
					$project_new->pivots_format($pivot, false);
					$project_new->saveHistory(false);
					$project_new->save(); //1s
					$this->setOnboarding($project_new, 1);

					//Send the request in another thread
					$this->asyncMonkeyKing($project_new, $links); //8s

					//Insure the sequence is running
					$this->runOnboarding(1, true);
				}
			}
			
		}

		else if($next==10102){
			//Stop the sequence
			$this->runOnboarding(1, false);
		}

		//It save something only if there is a change
		$this->saveOnboarding(); //Save if we have any new item to keep track

	}

}
