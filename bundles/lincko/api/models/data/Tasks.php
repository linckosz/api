<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;

class Tasks extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'tasks';
	protected $morphClass = 'tasks';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'updated_at',
		'deleted_at',
		'approved_at',
		'created_by',
		'updated_by',
		'approved_by',
		'title',
		'comment',
		'duration',
		'fixed',
		'approved',
		'status',
		'start',
		'progress',
		'_parent',
		'_tasks',
		'_users',
		'_files',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
		'comment',
	);

	protected $name_code = 500;

	protected $archive = array(
		'created_at' => 501, //[{un|ucfirst}] created a new task
		'_' => 502,//[{un|ucfirst}] modified a task
		'title' => 503,//[{un|ucfirst}] changed a task title
		'comment' => 504, //[{un|ucfirst}] modified a task content
		'duration' => 502, //[{un|ucfirst}] modified a task
		'fixed' => 502, //[{un|ucfirst}] modified a task
		'status' => 502, //[{un|ucfirst}] modified a task
		'start' => 502, //[{un|ucfirst}] modified a task
		'progress' => 502, //[{un|ucfirst}] modified a task
		'parent_id' => 505, //[{un|ucfirst}] moved a task to the project "[{pj|ucfirst}]"
		'pivot_delay' => 550, //[{un|ucfirst}] modified a task delay
		'pivot_in_charge_0' => 551, //[{cun|ucfirst}] is in charge of a task
		'pivot_in_charge_1' => 552, //[{cun|ucfirst}] is unassigned from a task
		'pivot_approver_0' => 553, //[{cun|ucfirst}] becomes an approver to a task
		'pivot_approver_1' => 554, //[{cun|ucfirst}] is no longer an approver to a task
		'approved_0' => 555, //[{un|ucfirst}] reopened a task
		'approved_1' => 556, //[{un|ucfirst}] completed a task
		'pivot_access_0' => 596, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a task
		'pivot_access_1' => 597, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a task
		'_restore' => 598,//[{un|ucfirst}] restored a task
		'_delete' => 599,//[{un|ucfirst}] deleted a task
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'parent_id' => '\\bundles\\lincko\\api\\models\\data\\Projects',
	);

	protected static $relations_keys = array(
		'users',
		'projects',
	);

	protected static $parent_list = 'projects';

	protected $model_timestamp = array(
		'approved_at',
		'start',
	);

	protected $model_integer = array(
		'approved_by',
		'duration',
		'progress',
		'status',
		'delay',
	);

	protected $model_boolean = array(
		'fixed',
		'approved',
		'in_charge',
		'approver',
	);

	protected static $allow_single = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);

	protected static $access_accept = false;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_tasks', array('in_charge', 'approver')),
		'tasks' => array('tasks_x_tasks', array('delay')),
		'files' => array('tasks_x_files', array('access')),
	);

	//Many(Tasks) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//Many(Tasks) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks', 'tasks_id', 'tasks_id_link')->withPivot('access', 'delay');
	}

	//Many(Tasks) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_tasks', 'tasks_id', 'users_id')->withPivot('access', 'in_charge', 'approver');
	}

	//Many(Tasks) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'tasks_x_files', 'tasks_id', 'files_id')->withPivot('access');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
			|| (isset($form->comment) && !self::validText($form->comment, true))
			|| (isset($form->start) && !self::validDate($form->start, true))
			|| (isset($form->duration) && !self::validNumeric($form->duration, true))
			|| (isset($form->fixed) && !self::validBoolean($form->fixed, true))
			|| (isset($form->approved) && !self::validBoolean($form->approved, true))
			|| (isset($form->status) && !self::validNumeric($form->status, true))
			|| (isset($form->progress) && !self::validProgress($form->progress, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//This is used because by default not all IDs are stored in pivot table
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array()){
		$default = array(
			'access' => 1, //Default is accessible
			'in_charge' => 0,
			'approver' => 0,
		);
		foreach ($uid_list as $uid) {
			foreach ($list as $value) {
				if(!isset($result[$uid][$value])){
					$result[$uid][$value] = (array) $default;
				}
			}
		}
		return $result;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all tasks with access 1, and all tasks which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) {
				if(isset($list['projects']) && count($list['projects'])>0){
					$query = $query
					->whereIn('tasks.parent_id', $list['projects']);
				}
			})
			->orWhere(function ($query) {
				$query = $query
				->whereHas('projects', function ($query) {
					$query->getItems();
				});
			});
		})
		->whereHas("users", function($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 0);
		}, '<', 1);
		if(self::$with_trash_global){
			$query = $query->withTrashed();
		}
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
			}
			return $result;
		} else {
			return $query;
		}
	}

	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		if($key == 'parent_id'){
			if($project = Projects::find($new)){
				$parameters['pj'] = $project->title;
			}
		}
		parent::setHistory($key, $new, $old, $parameters);
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		$dirty = $this->getDirty();
		foreach($dirty as $key => $value) {
			if($key=='approved'){
				if($this->approved){
					$this->approved = 1;
					$this->approved_at = $this->freshTimestamp();
					$this->approved_by = $app->lincko->data['uid'];
				} else {
					$this->approved = 0;
					$this->approved_at = null;
					$this->approved_by = null;
				}
				break;
			}
			/*
				//toto => the line below allow edit for user that are only readers but with right of approver or assignment
				if($details = $this->users()->theUser()->first()){
					$pivot = $details->pivot;
					//Need to continue to code
				}
			*/
		}
		if(Projects::getModel($this->parent_id)->personal_private == $app->lincko->data['uid']){
			$pivots = new \stdClass;
			$pivots->{'users>in_charge'} = new \stdClass;
			$pivots->{'users>in_charge'}->{$app->lincko->data['uid']} = true;
			$pivots->{'users>approver'} = new \stdClass;
			$pivots->{'users>approver'}->{$app->lincko->data['uid']} = true;
			$this->pivots_format($pivots, false);
		}
		$return = parent::save($options);
		
		return $return;
	}

}
