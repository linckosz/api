<?php

namespace bundles\lincko\api\models;

use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Settings;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use \bundles\lincko\api\models\libs\Updates;
use Carbon\Carbon;
use \libs\Translation;

class Onboarding {

	protected static $app = NULL;

	protected static $settings = NULL;

	protected static $onboarding = NULL;

	protected static $monkey_king = array();

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

	//This code is run after display to insure the user will go inside the account before everything is done
	public static function hookAddMonkeyKing(){
		$app = self::getApp();

		if(!isset(self::$monkey_king['project_new']) || !isset(self::$monkey_king['links'])){
			return false;
		}

		$project_new = self::$monkey_king['project_new'];
		$links = self::$monkey_king['links'];

		//initialize project pivot
		$all_pivot = new \stdClass;
		$all_pivot->{'users>access'} = new \stdClass;
		$all_pivot->{'users>access'}->{'1'} = true; //Attach the Monkey King
		$all_pivot->{'users>access'}->{$app->lincko->data['uid']} = true; //Make sure the user itself is attached

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
			foreach ($list as $item) {
				if($item){
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
					$item->pivots_format($all_pivot, false);
					//Assign tasks
					if($table=='tasks'){
						$item->pivots_format($task_pivot, false);
					}
					$item->saveHistory(false);
					$item->save();
					$users_tables[$app->lincko->data['uid']][$table] = true;
				}
			}
		}
		Projects::saveSkipper(false);
		$project_new->setPerm();
		if($parent = $project_new->getParent()){
			$users_tables = $parent->touchUpdateAt($users_tables, false, true);
		}
		Updates::informUsers($users_tables);

		return true;
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

			//Reset onboarding
			$this->resetOnboarding();

			//initialize project pivot
			$all_pivot = new \stdClass;
			$all_pivot->{'users>access'} = new \stdClass;
			$all_pivot->{'users>access'}->{'1'} = true; //Attach the Monkey King
			$all_pivot->{'users>access'}->{$app->lincko->data['uid']} = true; //Make sure the user itself is attached

			//Create a project
			if(!$this->getOnboarding('projects', 1)){
				if($project_ori = Projects::find($clone_id)){
					Projects::saveSkipper(true);
					$links = array();
					$project_new = $project_ori->clone(false, array(), $links);
					$project_new->title = $project_ori->title;
					$project_new->pivots_format($all_pivot, false);
					$project_new->saveHistory(false);
					$project_new->save();
					$this->setOnboarding($project_new, 1);
					Projects::saveSkipper(false);

					self::$monkey_king['project_new'] = $project_new;
					self::$monkey_king['links'] = $links;

					//toto => find a way to run this code in separate thread or after rendering
					self::hookAddMonkeyKing();

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
