<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Settings;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use Carbon\Carbon;
use \libs\Translation;

class Onboarding {

	protected static $app = NULL;

	protected static $settings = NULL;

	protected static $onboarding = NULL;

	public function __construct(){
		return true;
	}

	public static function getApp(){
		if(is_null(self::$app)){
			self::$app = \Slim\Slim::getInstance();
		}
		return self::$app;
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
		$settings->onboarding = json_encode(new \stdClass);
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
			$settings->onboarding = json_encode(self::$onboarding);
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
					$item->comment = json_encode($onboarding);
					$item->brutSave();
					$item->touchUpdateAt();
				}
			}
		}
	}

	//Launch the next onboarding
	public function next($next, $answer=false, $temp_id=''){
		$app = self::getApp();

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
				$clone_id = 1589;
				if($default_lang == 'zh-chs' || $default_lang == 'zh-chs'){
					$clone_id = 1605;
				}
			} else if($app->lincko->domain=='lincko.co'){
				$clone_id = 86;
			} else if($app->lincko->domain=='lincko.cafe'){
				$clone_id = 1973;
			} else {
				//Stop the sequence
				$this->runOnboarding(1, false);
			}

			$project_id = $this->getOnboarding('projects', 1);

			//Reset onboarding
			$this->resetOnboarding();

			//initialze project pivot
			$project_pivot = new \stdClass;
			$project_pivot->{'users>access'} = new \stdClass;
			$project_pivot->{'users>access'}->{'1'} = true; //Attach the Monkey King

			//Create a project
			if(!$this->getOnboarding('projects', 1)){
				$item = Projects::find(1973)->clone();
				$item->pivots_format($project_pivot, false);
				$item->save();
				$this->setOnboarding($item, 1);
				unset($item);
			}

			//Insure the sequence is running
			$this->runOnboarding(1, true);
		}

		else if($next==10102){
			//Stop the sequence
			$this->runOnboarding(1, false);
		}

		//It save something only if there is a change
		$this->saveOnboarding(); //Save if we have any new item to keep track

	}

	

}
