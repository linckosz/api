<?php
// Category 6

namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
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
		'exit_at',
		'approved_at',
		'created_by',
		'updated_by',
		'approved_by',
		'title',
		'comment',
		'duration', //A negative value means there is no due date
		'fixed',
		'approved',
		'status',
		'start',
		'progress',
		'milestone',
		'_parent',
		'_tasksup',
		'_tasksdown',
		'_users',
		'_files',
		'_notes',
		'_spaces',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected static $prefix_fields = array(
		'title' => '+title',
		'comment' => '-comment',
	);

	protected static $hide_extra = array(
		'temp_id',
		'title',
		'comment',
		'viewed_by',
	);

	protected $name_code = 500;

	protected static $archive = array(
		'created_at' => 501, //[{un}] created a new task
		'_' => 502,//[{un}] modified a task
		'title' => 503,//[{un}] changed a task title
		'comment' => 504, //[{un}] modified a task content
		'duration' => 506, //[{un}] modified a task due date
		'fixed' => 502, //[{un}] modified a task
		'milestone' => 502, //[{un}] modified a task
		'status' => 502, //[{un}] modified a task
		'start' => 506, //[{un}] modified a task due date
		'progress' => 502, //[{un}] modified a task
		'parent_id' => 505, //[{un}] moved a task to the project "[{pj|ucfirst}]"
		'pivot_delay' => 550, //[{un}] modified a task delay
		'pivot_in_charge_0' => 551, //[{cun}] is in charge of a task
		'pivot_in_charge_1' => 552, //[{cun}] is unassigned from a task
		'pivot_approver_0' => 553, //[{cun}] becomes an approver to a task
		'pivot_approver_1' => 554, //[{cun}] is no longer an approver to a task
		'approved_0' => 555, //[{un}] reopened a task
		'approved_1' => 556, //[{un}] completed a task
		'pivot_access_0' => 596, //[{un}] blocked [{[{cun}]}]'s access to a task
		'pivot_access_1' => 597, //[{un}] authorized [{[{cun}]}]'s access to a task
		'_restore' => 598,//[{un}] restored a task
		'_delete' => 599,//[{un}] deleted a task

		/*
		//toto => Need to be refactor because of later Team/Entreprise accounts
		'created_at' => 501, //[{un}] created a new task
		'_' => 502,//[{un}] modified a task
		'title' => 503,//[{un}] changed a task title
		'comment' => 504, //[{un}] modified a task content
		'approved_0' => 555, //[{un}] reopened a task
		'approved_1' => 556, //[{un}] completed a task
		'_restore' => 598,//[{un}] restored a task
		'_delete' => 599,//[{un}] deleted a task
		*/
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
		'position',
	);

	protected $model_boolean = array(
		'fixed',
		'milestone',
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

	protected static $has_perm = true;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_tasks', array('in_charge', 'approver')),
		'tasksup' => array('tasks_x_tasks', array('access')),
		'tasksdown' => array('tasks_x_tasks', array('delay', 'position')),
		'files' => array('tasks_x_files', array('access')),
		'notes' => array('tasks_x_notes', array('access')),
		'spaces' => array('spaces_x', array('created_at', 'exit_at')),
	);

	//Many(Tasks) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//Many(Tasks) to Many(Tasks)
	public function tasksup(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks', 'tasks_id_sub', 'tasks_id')->withPivot('access');
	}

	//Many(Tasks) to Many(Tasks)
	public function tasksdown(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks', 'tasks_id', 'tasks_id_sub')->withPivot('access', 'delay', 'position');
	}

	//Many(Tasks) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_tasks', 'tasks_id', 'users_id')->withPivot('access', 'in_charge', 'approver');
	}

	//Many(Tasks) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'tasks_x_files', 'tasks_id', 'files_id')->withPivot('access');
	}

	//Many(Tasks) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'tasks_x_notes', 'tasks_id', 'notes_id')->withPivot('access');
	}

	//Many(Tasks) to Many(Spaces)
	public function spaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'tasks')->withPivot('access', 'created_at', 'exit_at');
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
			|| (isset($form->milestone) && !self::validBoolean($form->milestone, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//This is used because by default not all IDs are stored in pivot table
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array(), $default = array('access' => 1)){
		$default = array(
			'access' => 1,
			'in_charge' => 0,
			'approver' => 0,
		);
		return parent::filterPivotAccessListDefault($list, $uid_list, $result, $default);
	}

	protected function setPivotExtra($type, $column, $value){
		$pivot_array = array(
			$column => $value,
		);
		if($type=='spaces'){
			$pivot_array['parent_type'] = 'tasks';
			$pivot_array['created_at'] = $this->freshTimestamp();
			if($column=='access'){
				if($value){
					$pivot_array['exit_at'] = null;
				} else {
					$pivot_array['exit_at'] = $pivot_array['created_at'];
				}
			}
		}
		return $pivot_array;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all tasks with access 1, and all tasks which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) use ($list) {
				if(isset($list['projects']) && count($list['projects'])>0){
					$query = $query
					->whereIn('tasks.parent_id', $list['projects']);
				} else {
					$query = $query
					->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include 'projects'
				}
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
		$app = self::getApp();
		if($key == 'parent_id'){
			$parameters['tt'] = $this->title;
			$parameters['tid'] = $this->id;
			if($project_from = Projects::find($old)){
				$project_from->setHistory('_tasks', 0, 1, $parameters); //Move out
				$project_from->touchUpdateAt();
			}
			if($project_to = Projects::find($new)){
				$project_to->setHistory('_tasks', 1, 0, $parameters); //Move in
				$project_to->touchUpdateAt();
				$parameters['pj'] = $project_to->title;
				return true; //Allow return if we don't want double record of task move
			} else {
				$parameters['pj'] = $app->trans->getBRUT('api', 0, 2); //unknown
			}
		}
		parent::setHistory($key, $new, $old, $parameters, $pivot_type, $pivot_id);
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
		$projects = Projects::getModel($this->parent_id);
		if($projects && $projects->personal_private == $app->lincko->data['uid']){
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

	//$deadline (Carbon)
	public function overdue($deadline){
		if(!$this->approved && $this->start < $deadline){
			$duedate = Carbon::createFromFormat('Y-m-d H:i:s', $this->start);
			$duedate->second = $this->duration;
			if($duedate < $deadline){
				return $deadline->diffInSeconds($duedate, true);
			}
		}
		return false;
	}

	//For pivot tasksup and tasksdown, make sure we return the class Tasks
	public static function getClass($class=false){
		if($class=='tasksup' || $class=='tasksdown'){
			$class = 'tasks';
		}
		return parent::getClass($class);
	}

}
