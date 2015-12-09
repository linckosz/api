<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;

class Tasks extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'tasks';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'done_at',
		'created_by',
		'updated_by',
		'projects_id',
		'note',
		'title',
		'comment',
		'duration',
		'fixed',
		'status',
		'start',
		'progress',
		'_tasks',
		'_users',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
		'comment',
	);

	protected $archive = array(
		'created_at' => 501, //[{un|ucfirst}] created a new [{nt}].
		'_' => 502,//[{un|ucfirst}] modified a [{nt}].
		'title' => 503,//[{un|ucfirst}] changed a [{nt}] title.
		'comment' => 504, //[{un|ucfirst}] modified a [{nt}] content.
		'duration' => 502, //[{un|ucfirst}] modified a [{nt}].
		'fixed' => 502, //[{un|ucfirst}] modified a [{nt}].
		'status' => 502, //[{un|ucfirst}] modified a [{nt}].
		'start' => 502, //[{un|ucfirst}] modified a [{nt}].
		'progress' => 502, //[{un|ucfirst}] modified a [{nt}].
		'projects_id' => 505, //[{un|ucfirst}] moved a [{nt}] to the project "[{pj|ucfirst}]".
		'_delay' => 550, //[{un|ucfirst}] modified a [{nt}] delay.
		'_in_charge_0' => 551, //[{cun|ucfirst}] is in charge of a [{nt}].
		'_in_charge_1' => 552, //[{cun|ucfirst}] is unassigned from a [{nt}].
		'_approver_0' => 553, //[{cun|ucfirst}] becomes an approver to a [{nt}].
		'_approver_1' => 554, //[{cun|ucfirst}] is no longer an approver to a [{nt}].
		'_access_0' => 596, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a [{nt}].
		'_access_1' => 597, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a [{nt}].
		'_restore' => 598,//[{un|ucfirst}] restored a [{nt}].
		'_delete' => 599,//[{un|ucfirst}] deleted a [{nt}].
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'projects_id' => '\\bundles\\lincko\\api\\models\\data\\Projects',
	);

	protected static $relations_keys = array(
		'users',
		'projects',
	);

	protected $dependencies_visible = array(
		'users',
		'tasks',
	);
	
////////////////////////////////////////////

	//Many(Tasks) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'projects_id');
	}

	//Many(Tasks) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks', 'tasks_id', 'tasks_id_link')->withPivot('access', 'delay');
	}

	//Many(Tasks) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_tasks', 'tasks_id', 'users_id')->withPivot('access', 'in_charge', 'approver');
	}

////////////////////////////////////////////
	
	public static function validTitle($data){
		$return = is_string($data) && strlen(trim($data))>0;
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validComment($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		return self::noValidMessage($return, __FUNCTION__);
	}

	/*
		In Note: Only a textarea available (as text note), it's Title only
		In Task: Title as "Short description", and Comment as "Comments"
	*/
	public static function isValid($form){
		if(!isset($form->title)){ self::noValidMessage(false, 'title'); } //Required
		return
			     isset($form->title) && self::validTitle($form->title)
			&& (!isset($form->comment) || self::validComment($form->comment)) //Optional
			;
	}

////////////////////////////////////////////

	public function scopegetLinked($query){
		return $query->whereHas('projects', function ($query) {
			$query->getLinked();
		});
		//Ideally we should cut all Tasks with Access 0, but we cannot by Query Builder, so we do it manually in Data.php	
	}

	//Get all users that are linked to the task
	public function getUsersContacts(){
		$contacts = parent::getUsersContacts();
		$list = $this->users()->get();
		foreach($list as $key => $value) {
			$id = $value->id;
			$contacts->$id = $this->getContactsInfo();
		}
		return $contacts;
	}

	public function getCompany(){
		return $this->projects->getCompany();
	}

	protected function get_NoteTask(){
		$app = self::getApp();
		if($this->note){
			return $app->trans->getBRUT('api', 6, 1); //note
		} else {
			return $app->trans->getBRUT('api', 6, 2); //task
		}
	}

	public function setHistory($key=null, $new=null, $old=null, array $parameters = array()){
		$parameters['nt'] = $this->get_NoteTask();
		if($key == 'projects_id'){
			if($project = Projects::find($new)){
				$parameters['pj'] = $project->title;
			}
		}
		parent::setHistory($key, $new, $old, $parameters);
	}

	protected function getHistoryCreation($history_detail=false, array $parameters = array()){
		$parameters['nt'] = $this->get_NoteTask();
		return parent::getHistoryCreation($history_detail, $parameters);
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		$return = parent::save($options);
		if($new){
			$this->setUserPivotValue($app->lincko->data['uid'], 'in_charge', 1, false);
			$this->setUserPivotValue($app->lincko->data['uid'], 'approver', 1, false);
		}
		return $return;
	}

}
