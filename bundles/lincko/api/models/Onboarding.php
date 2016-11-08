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

	public function isAnswered($id){
		$is_answered = true;
		if(is_numeric($id) && $item = Comments::getModel($id)){
			if($item->created_by==0){
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
		}
		return $is_answered;
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
	public function next($next, $answer=false){
		$app = self::getApp();

		//the user answered the question
		if($answer){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$item->comment = $answer;
			$item->save();
			unset($item);
		}

		if(!is_numeric($next)){
			return false;
		}

		$next = ''.$next; //Convert it to string for object key

		//This is the entry where to start onboarding system (Initialiaze the first onboarding)
		if($next==10001){

			//initialze project pivot
			$project_pivot = new \stdClass;
			$project_pivot->{'users>access'} = new \stdClass;
			$project_pivot->{'users>access'}->{'1'} = true; //Attach the Monkey Key

			//Create a project
			$item = new Projects();
			$item->title = $app->trans->getBRUT('api', 2000, 1); //Welcome to Lincko!
			$item->description = $app->trans->getBRUT('api', 2000, 2); //This project helps you to learn how to use Lincko. Be free to modify it as you want.
			$item->parent_id = 0; //Shared folder only
			$item->pivots_format($project_pivot, false);
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
			$item->title = $app->trans->getBRUT('api', 2000, 3); //Get started using Lincko
			$item->comment = $app->trans->getBRUT('api', 2000, 4); //
			$item->parent_id = $project->id;
			$item->pivots_format($task_pivot, false);
			$item->approved = true; //Marked as approved
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
			$item->title = $app->trans->getBRUT('api', 2000, 5); //Mark this task complete
			$item->comment = $app->trans->getBRUT('api', 2000, 6); //
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
			$this->setOnboarding($item, 3);
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//I’m here to start you on your journey. Use Lincko to take your teamwork and projects to new heights! We’ve already created a project and assigned you some tasks. Ready to get started? Respond to me by tapping or clicking one of the messages below.
			$comment->ob->{'10001'} = new \stdClass;
			//Wait a second, who are you?
			$comment->ob->{'10001'}->{'11001'} = array(
				'next',
				10002, //I'm a humble master in the way of projects and I'm here to be your guide. I'll get you started using Lincko, and I’ll give you updates on the activity in your projects as you complete tasks, add files and notes, and generally accomplish great things!
			);
			//Let’s accomplish some stuff.
			$comment->ob->{'10001'}->{'11002'} = array(
				'next',
				10003, //OK - let's start by making sure your settings are correct:
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
			//[image]/lincko/app/images/generic/onboarding/LinckoMeditate.gif[/image]
			$comment->ob->{'10005'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//I'm a humble master in the way of projects and I'm here to be your guide. I'll get you started using Lincko, and I’ll give you updates on the activity in your projects as you complete tasks, add files and notes, and generally accomplish great things!
			$comment->ob->{'10002'} = new \stdClass;
			//Let’s accomplish some stuff.
			$comment->ob->{'10002'}->{'11002'} = array(
				'next',
				10003, //OK - let's start by making sure your settings are correct:
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
			$comment->ob->{'10003'} = new \stdClass;
			//Update my username, profile photo, and/or language.
			$comment->ob->{'10003'}->{'11003'} = array(
				'action',
				10004,
				'[1] Update my username, profile photo, and/or language',
				1,
			);
			//Let's go straight to the project.
			$comment->ob->{'10003'}->{'11004'} = array(
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
			$comment->ob->{'10004'} = new \stdClass;
			//Take me there, LinckoBot, I'm ready!
			$comment->ob->{'10004'}->{'11005'} = array(
				'action',
				10006, //Good work… but it looks like my friend the MonkeyKing doesn’t like you delegating work to him…but don’t worry you can add some coworkers or friends to collaborate with on Lincko. We help teams accomplish great things.
				'[2] Chat closes and Project opened - shows task lists',
				2, //[2] Chat closes and Project opened - shows task lists
				'tasks',
				$this->getOnboarding('tasks', 1),
				'tasks',
				$this->getOnboarding('tasks', 2),
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

		else if($next==10006){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Good work… but it looks like my friend the MonkeyKing doesn’t like you delegating work to him…but don’t worry you can add some coworkers or friends to collaborate with on Lincko. We help teams accomplish great things.
			$comment->ob->{'10006'} = new \stdClass;
			//Invite colleagues now - it’s free
			$comment->ob->{'10006'}->{'11006'} = array(
				'action',
				10007, //​​​​​​​Okay, you can add contacts at anytime by clicking on this button: [image] on your main menu. Don’t forget to also add them to the projects you want to work with them on.
				'[3] Take them to a special invite screen - where the user can add people',
				3,
			);
			//I’m a maverick, I want to work alone for now.
			$comment->ob->{'10006'}->{'11007'} = array(
				'next',
				10007, //​​​​​​​Okay, you can add contacts at anytime by clicking on this button: [image] on your main menu. Don’t forget to also add them to the projects you want to work with them on.
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

		else if($next==10007){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Okay, you can add contacts at anytime by clicking on this button: [image] on your main menu. Don’t forget to also add them to the projects you want to work with them on.
			$comment->ob->{'10007'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Each project has Tasks, Notes, Chats, and Files - use tasks to set the goals of your project, use notes to store important information for the team - like meeting notes, processes, policies, designs and requirements, or other longer information.
			$comment->ob->{'10008'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Use Chats for quick communication, and use Files for all your important documents and images. Every project has these four areas to keep you organised.
			$comment->ob->{'10009'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Anytime you upload a file to your project Chat, or attach it to a task or note - it will automatically be stored in the Files section of your project. You can link existing notes and files to tasks as well.  
			$comment->ob->{'10010'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//[image]/lincko/app/images/generic/onboarding/NavigationRepeat.gif[/image]
			$comment->ob->{'10010'} = new \stdClass;
			//What else ?
			$comment->ob->{'10010'}->{'11008'} = array(
				'next',
				10012, //​​​​​​​No project goes according to plan - quickly turn any line item in a note (including the action items in your meeting notes) into a task, or convert any chat message into a task by long pressing or clicking on the chat message. This let’s you turn communication into action.
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

		else if($next==10012){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//No project goes according to plan - quickly turn any line item in a note (including the action items in your meeting notes) into a task, or convert any chat message into a task by long pressing or clicking on the chat message. This let’s you turn communication into action.
			$comment->ob->{'10012'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Let's try it. I'm going to send you a message, click or long press on the message to turn it into a task.
			$comment->ob->{'10013'} = new \stdClass;
			//Let's do this, LinckoBot !
			$comment->ob->{'10013'}->{'11009'} = array(
				'next',
				10014, //​​​​​​​Remember to invite others to use Lincko and have the chance to win a free stuff.
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

		else if($next==10014){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Remember to invite others to use Lincko and have the chance to win a free stuff.
			$comment->ob->{'10014'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//We need to know the comment ID
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			$comment->ob->{'10014'} = new \stdClass;
			$comment->ob->{'10014'}->{'11009'} = array(
				'now',
				10015, //As you progress on your project - I will give a daily and weekly  update about the progress in your project activity feed. This is a special Chat group automatically created when you create a project. You can add other discussion groups in the project with different themes.
				'[4] once the user clicks - and the task is created - chat continues',
				4,
				'comments',
				$item->id,
			);
			$item->comment = json_encode($comment);
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);
		}

		else if($next==10015){
			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//As you progress on your project - I will give a daily and weekly  update about the progress in your project activity feed. This is a special Chat group automatically created when you create a project. You can add other discussion groups in the project with different themes.
			$comment->ob->{'10015'} = new \stdClass;
			//LinckoBot, please show me
			$comment->ob->{'10015'}->{'11010'} = array(
				'next',
				10016, //​​​​​​​[image]/lincko/app/images/generic/onboarding/ProjectActivity.gif[/image]
			);
			//What else ?
			$comment->ob->{'10015'}->{'11008'} = array(
				'next',
				10017, //​​​​​​​​​​​​​​Okay, we’re almost done - everyone has their own personal space - this is a special project that only you have access to. Everything you store will not be shared. We have also given you access to a sample project, so you can see how one team sets their tasks and goals in the project, and uses Notes, Chat groups and Files.
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

		else if($next==10016){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//​​​​​​​[image]/lincko/app/images/generic/onboarding/ProjectActivity.gif[/image]
			$comment->ob->{'10016'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$this->next(10017); //Okay, we’re almost done - everyone has their own personal space - this is a special project that only you have access to. Everything you store will not be shared. We have also given you access to a sample project, so you can see how one team sets their tasks and goals in the project, and uses Notes, Chat groups and Files.
		}

		else if($next==10017){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Okay, we’re almost done - everyone has their own personal space - this is a special project that only you have access to. Everything you store will not be shared. We have also given you access to a sample project, so you can see how one team sets their tasks and goals in the project, and uses Notes, Chat groups and Files.
			$comment->ob->{'10017'} = new \stdClass;
			//LinckoBot, please show me
			$comment->ob->{'10017'}->{'11010'} = array(
				'next',
				10018, //​​​​​​​[image]/lincko/app/images/generic/onboarding/PersonalSpace.png[/image]
			);
			//Help me create my own project
			$comment->ob->{'10017'}->{'11011'} = array(
				'next',
				10019, //​​​​​​​
				'Open project creation submenu, focus on title then create button',
				5,
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

		else if($next==10018){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//Okay, we’re almost done - everyone has their own personal space - this is a special project that only you have access to. Everything you store will not be shared. We have also given you access to a sample project, so you can see how one team sets their tasks and goals in the project, and uses Notes, Chat groups and Files.
			$comment->ob->{'10018'} = new \stdClass;
			$item->comment = json_encode($comment);
			$item->save();
			//Force to use LinckoBot as creator
			$item->created_by = 0;
			$item->updated_by = 0;
			$item->noticed_by = '';
			$item->viewed_by = '';
			$item->brutSave();
			unset($item);

			$this->next(10019);
		}

		else if($next==10019){

			$item = new Comments();
			$item->parent_type = 'projects';
			$item->parent_id = $this->getOnboarding('projects', 1);
			$comment = new \stdClass;
			$comment->ob = new \stdClass;
			//You're on you're way to being a Lincko master now! I'll be back to send you regular updates on your progess, but for now, thanks for trying out Lincko. 
			$comment->ob->{'10019'} = new \stdClass;
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

		//It save something only if there is a change
		$this->saveOnboarding(); //Save if we have any new item to keep track

	}

	

}
