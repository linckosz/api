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

	public function answered($id){
		if(is_numeric($id) && $item = Comments::getModel($id)){
			if($item->created_by==0){
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
	}

	//Launch the next onboarding
	public function next($next){
		$app = self::getApp();

		if(!is_numeric($next)){
			return false;
		}

		$next = ''.$next; //Convert it to string for object key

		//This is the entry where to start onboarding system (Initialiaze the first onboarding)
		if($next==10001){

			//Create a project
			$item = new Projects();
			$item->title = $app->trans->getBRUT('api', 2000, 1); //Onboarding project
			$item->description = $app->trans->getBRUT('api', 2000, 2); //This project helps you to learn how to use Lincko. Be free to modify it as you want.
			$item->parent_id = 0; //Shared folder only
			$item->save();
			//Set the user as Manager "id:2" (cannot delete items then)
			PivotUsersRoles::setMyRole($item, 2);
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			$item->setPerm();
			$this->setOnboarding($item, 1);
			$project = $item;
			unset($item);

			//initialze task pivot
			$task_pivot = new \stdClass;
			$task_pivot->{'users>in_charge'} = new \stdClass;
			$task_pivot->{'users>in_charge'}->{$app->lincko->data['uid']} = true;
			$task_pivot->{'users>approver'} = new \stdClass;
			$task_pivot->{'users>approver'}->{$app->lincko->data['uid']} = true;

			//Add it a task
			$item = new Tasks();
			$item->title = $app->trans->getBRUT('api', 2000, 3); //A sample task
			$item->comment = $app->trans->getBRUT('api', 2000, 4); //Do some operations here.
			$item->parent_id = $project->id;
			$item->pivots_format($task_pivot, false);
			$item->save();
			//Lock the deletion
			PivotUsersRoles::setMyRole($item, null, 2);
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			$item->setPerm();
			$this->setOnboarding($item, 2);
			$tasks_late = $item;
			unset($item);

			//Add it a task
			$item = new Tasks();
			$item->title = $app->trans->getBRUT('api', 2000, 5); //A task in late
			$item->comment = $app->trans->getBRUT('api', 2000, 6); //Hurry up, Boss is coming!
			$item->parent_id = $project->id;
			$item->pivots_format($task_pivot, false);
			$item->duration = 86400; //1 day
			$start = Carbon::today();
			$start->second = -2 * $item->duration; //Make it in late
			$item->start = $start->format('Y-m-d H:i:s');
			$item->save();
			//Lock the deletion
			PivotUsersRoles::setMyRole($item, null, 2);
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			$item->setPerm();
			$this->setOnboarding($item, 3);
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//I’m here to start you on your journey. Use Lincko to take your teamwork and projects to new heights! We’ve already created a project and assigned you some tasks. Ready to get started? Respond to me by tapping or clicking one of the messages below.
			$comment->ob->$next = new \stdClass;
			//Wait a second, who are you?
			$comment->ob->$next->{'11001'} = array(
				'next',
				10002,
			);
			//Let’s accomplish some stuff.
			$comment->ob->$next->{'11002'} = array(
				'action',
				10003,
				'tasks',
				$tasks_late->id,
				1, //Number of action to do
			);
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);
		}

		else if($next==10002){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//I'm a humble master in the way of projects and I'm here to be your guide. I'll get you started using Lincko, and I’ll give you updates on the activity in your projects as you complete tasks, add files and notes, and generally accomplish great things!
			$comment->ob->$next = new \stdClass;
			//Let’s accomplish some stuff.
			$comment->ob->$next->{'11002'} = array(
				'next',
				10003,
			);
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);
		}

		else if($next==10003){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//OK - let's start by making sure your settings are correct:
			$comment->ob->$next = new \stdClass;
			//Update my username, profile photo, and/or language.
			$comment->ob->$next->{'11003'} = array(
				'next',
				10004,
			);
			//Let's go straight to the project.
			$comment->ob->$next->{'11004'} = array(
				'next',
				10004,
			);
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);
		}

		else if($next==10004){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//I’m going to take you to the project automatically. Lincko helps you plan the tasks for your projects in a familiary way. Go ahead and complete the tasks that you see. Once you’re done, I’ll reappear.
			$comment->ob->$next = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);
		}

	}

	//It save something only if there is a change
	$this->saveOnboarding(); //Save if we have any new item to keep track

}
