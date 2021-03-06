<?php
// Category 6

namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \libs\Datassl;
use \libs\STR;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\Inform;

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
		'fav',
		'approved_by',
		'locked_by',
		'locked_fp',
		'title',
		'comment',
		'search',
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
		'search',
		'viewed_by',
		'locked_by',
		'locked_fp',
	);

	protected $name_code = 500;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => array(true, 501), //[{un}] created a new task
		'_' => array(true, 502), //[{un}] modified a task
		'title' => array(true, 503), //[{un}] changed a task title
		'comment' => array(true, 504), //[{un}] modified a task content
		'duration' => array(true, 506), //[{un}] modified a task due date
		'fixed' => array(true, 502), //[{un}] modified a task
		'milestone' => array(true, 502), //[{un}] modified a task
		'status' => array(true, 502), //[{un}] modified a task
		'start' => array(true, 507), //[{un}] modified a task due date
		'progress' => array(true, 502), //[{un}] modified a task
		'parent_id' => array(true, 505), //[{un}] moved a task to the project "[{pj|ucfirst}]"
		'pivot_tasksup_delay' => array(true, 550), //[{un}] modified a task delay
		'pivot_tasksdown_delay' => array(true, 550), //[{un}] modified a task delay
		'pivot_users_in_charge_0' => array(true, 551), //[{cun}] is unassigned from a task
		'pivot_users_in_charge_1' => array(true, 552), //[{cun}] is in charge of a task
		'pivot_users_approver_0' => array(true, 553), //[{cun}] is no longer an approver to a task
		'pivot_users_approver_1' => array(true, 554), //[{cun}] becomes an approver to a task
		'approved_0' => array(true, 555), //[{un}] reopened a task
		'approved_1' => array(true, 556), //[{un}] completed a task
		'pivot_users_access_0' => array(true, 596), //[{un}] blocked [{cun}]'s access to a task
		'pivot_users_access_1' => array(true, 597), //[{un}] authorized [{cun}]'s access to a task
		'_restore' => array(true, 598), //[{un}] restored a task
		'_delete' => array(true, 599), //[{un}] deleted a task
		
		//toto => Need to be refactor because of later Team/Entreprise accounts
		'tasksdown_created_at' => array(true, 10501), //[{un}] created a new task
		'tasksdown_' => array(true, 10502), //[{un}] modified a task
		'tasksdown_title' => array(true, 10503), //[{un}] changed a task title
		'tasksdown_comment' => array(true, 10504), //[{un}] modified a task content
		'tasksdown_approved_0' => array(true, 10555), //[{un}] reopened a task
		'tasksdown_approved_1' => array(true, 10556), //[{un}] completed a task
		'tasksdown__restore' => array(true, 10598), //[{un}] restored a task
		'tasksdown__delete' => array(true, 10599), //[{un}] deleted a task
		
	);

	protected static $history_xdiff = array('comment');

	protected static $history_visible = array(
		'title' => true,
		'start' => true,
		'duration' => true,
		'tasksdown_title' => true,
	);

	protected static $parent_list = 'projects';
	protected static $parent_list_get = array('projects', 'users');

	protected $model_timestamp = array(
		'approved_at',
		'start',
	);

	protected $model_integer = array(
		'fav',
		'approved_by',
		'locked_by',
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
		'users' => array('users_x_tasks', array('fav', 'in_charge', 'approver')),
		'tasksup' => array('tasks_x_tasks', array('access')),
		'tasksdown' => array('tasks_x_tasks', array('fav', 'delay', 'position')),
		'files' => array('tasks_x_files', array('fav')),
		'notes' => array('tasks_x_notes', array('fav')),
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
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_tasks', 'tasks_id', 'tasks_id_sub')->withPivot('access', 'fav', 'delay', 'position');
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
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'tasks')->withPivot('access', 'fav', 'created_at', 'exit_at');
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
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array(), $default = array('access' => 1, 'fav' => 0)){
		$default = array(
			'access' => 1,
			'fav' => 0,
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
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			//It will get all tasks with access 1, and all tasks which are not in the relation table, but the second has to be in conjonction with projects
			if(isset($list['projects']) && count($list['projects'])>0){
				$query = $query
				->whereIn('tasks.parent_id', $list['projects']);
			} else {
				$query = $query
				->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include 'projects'
			}
			$query = $query
			->whereHas('users', function($query) {
				$app = ModelLincko::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where('access', 0);
			}, '<', 1)
			//This exclude subtask from tasks deleted
			->whereHas('tasksup', function($query) {
				$table_alias = $query->getModel()->getTable();
				$query
				->withTrashed()
				->whereNotNull($table_alias.'.deleted_at');
			}, '<', 1);
			if(self::$with_trash_global){
				$query = $query->withTrashed();
			}
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
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
		$app = ModelLincko::getApp();
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
				return false; //Allow return if we don't want double record of task move
			} else {
				$parameters['pj'] = $app->trans->getBRUT('api', 0, 2); //unknown
			}
		}

		//toto => temporary solution, it will need to be refactored because of later Team/Entreprise accounts, in a gantt chart each task will act as single task with dependencies, not only as a subtask
		$dependency = $this->getDependency();
		if($dependency && isset($dependency[$this->getTable()]) && isset($dependency[$this->getTable()][$this->id]) && isset($dependency[$this->getTable()][$this->id]['_tasksup']) && count($dependency[$this->getTable()][$this->id]['_tasksup'])>0){
			$tasksup_id = array_keys((array) $dependency[$this->getTable()][$this->id]['_tasksup'])[0]; //Get the first parent
			if($tasksup = $this->getModel($tasksup_id)){
				$tasksup->setHistory('tasksdown_'.$key, $new, $old, $parameters);
				$tasksup->touchUpdateAt();
				return false; //Do not record element itself, only the parent one
			}
		}

		if($key == 'start'){
			if(!is_null($old) && !is_null($this->duration)){
				$duedate = Carbon::createFromFormat('Y-m-d H:i:s', $old);
				$duedate->second = $duedate->second + $this->duration;
				$parameters['dd'] = $duedate->timestamp;
			} else {
				$parameters['dd'] = null;
			}
		} else if($key == 'duration'){
			if(!is_null($old) && !is_null($this->start)){
				$duedate = Carbon::createFromFormat('Y-m-d H:i:s', $this->start);
				$duedate->second = $duedate->second + $old;
				$parameters['dd'] = $duedate->timestamp;
			} else {
				$parameters['dd'] = null;
			}
		}

		if($history = parent::setHistory($key, $new, $old, $parameters, $pivot_type, $pivot_id)){
			//Make sure we notify assigned and unassigned
			if($history->code==551 || $history->code==552){
				$this->pushNotif(false, $history);
				$history = false;
			}
		}

		return $history;
	}

	public function getHistoryCreationCode(&$items=false){
		//toto => temporary solution, it will need to be refactored because of later Team/Entreprise accounts, in a gantt chart each task will act as single task with dependencies, not only as a subtask
		$dependency = $this->getDependency();
		if($dependency && isset($dependency[$this->getTable()]) && isset($dependency[$this->getTable()][$this->id]) && isset($dependency[$this->getTable()][$this->id]['_tasksup']) && count($dependency[$this->getTable()][$this->id]['_tasksup'])>0){
			$tasksup_id = array_keys((array) $dependency[$this->getTable()][$this->id]['_tasksup'])[0]; //Get the first parent
			if($tasksup = $this->getModel($tasksup_id)){
				return false; //Return an empty creation for subtasks
			}
		}
		return parent::getHistoryCreationCode($items);
	}

	public function getHistoryCreation($history_detail=false, array $parameters = array(), &$items=false){
		$history = new \stdClass;
		//toto => temporary solution, it will need to be refactored because of later Team/Entreprise accounts, in a gantt chart each task will act as single task with dependencies, not only as a subtask
		$dependency = $this->getDependency();
		if($dependency && isset($dependency[$this->getTable()]) && isset($dependency[$this->getTable()][$this->id]) && isset($dependency[$this->getTable()][$this->id]['_tasksup']) && count($dependency[$this->getTable()][$this->id]['_tasksup'])>0){
			$tasksup_id = array_keys((array) $dependency[$this->getTable()][$this->id]['_tasksup'])[0]; //Get the first parent
			if($tasksup = $this->getModel($tasksup_id)){
				return $history; //Return an empty creation for subtasks
			}
		}

		$history = parent::getHistoryCreation($history_detail, $parameters, $items);

		return $history;
	}


	public function pushNotif($new=false, $history=false){
		$app = ModelLincko::getApp();
		if(!$new && !$history){
			return false;
		}
		if($this->updated_by==0){
			return false;
		}

		//Get all users that didn't silence the project
		$type = 'projects';
		$users_accept = array();
		$pivot = new PivotUsers(array($type));
		if($this->tableExists($pivot->getTable())){
			if($list = $pivot->where($type.'_id', $this->parent_id)->where('access', 1)->where('silence', 0)->get(array('users_id'))){
				foreach ($list as $value) {
					$users_accept[$value->users_id] = $value->users_id;
				}
			}
		}

		if(isset($history->pivot_type) && $history->pivot_type=='users'){
			$users_accept[$history->pivot_id] = $history->pivot_id;
		}
		if(isset($history->parent_type) && $history->parent_type=='users'){
			$users_accept[$history->parent_id] = $history->parent_id;
		}

		$users = false;
		$type = 'tasks';
		$pivot = new PivotUsers(array($type));
		if($this->tableExists($pivot->getTable())){
			$users = $pivot
			->where($type.'_id', $this->id)
			->whereIn('users_id', $users_accept)
			->where('access', 1)
			->where(function ($query) use ($history){
				$query = $query->orWhere('tasks_id', -1);
				if(isset($history->attribute) && $history->attribute!='pivot_users_approver'){
					$query = $query
					->orWhere('approver', 1);
				}
				if(isset($history->attribute) && $history->attribute!='pivot_users_in_charge'){
					$query = $query
					->orWhere('in_charge', 1);
				}
				if(isset($history->parent_type) && $history->parent_type=='users'){
					$query = $query
					->orWhere('users_id', $history->parent_id);
				}
				if(isset($history->pivot_type) && $history->pivot_type=='users'){
					$query = $query
					->orWhere('users_id', $history->pivot_id);
				}
			})
			->get(array('users_id'));
		}

		if($users){
			if($this->updated_by==0){
				$sender = $app->trans->getBRUT('api', 0, 11); //LinckoBot
			} else {
				$sender = Users::find($this->updated_by)->getUsername();
			}
			$title = $this->title;
			$param = array('un' => $sender);
			if($history && isset($history->parameters)){
				if($json = json_decode($history->parameters)){
					foreach ($json as $key => $value) {
						$param[$key] = $value;
					}
				}
			}
			$info_lang = array();
			foreach ($users as $value) {
				if($value->users_id != $this->updated_by && $value->users_id != $app->lincko->data['uid']){
					$user = Users::find($value->users_id);
					$language = $user->getLanguage();
					if(!isset($info_lang[$language])){
						$delete_user = true;
						if($history){
							$content = $app->trans->getBRUT('data', 1, $history->code, array(), $language);
						} else if($new){
							$content = $app->trans->getBRUT('data', 1, 501, array(), $language); //[{un}] created a new task
						} else {
							continue;
						}
						foreach ($param as $search => $replace) {
							$content = str_replace('[{'.$search.'}]', $replace, $content);
							if($search=='pvid' && $replace!=$app->lincko->data['uid'] && ($history->code==551 || $history->code==552)){
								$delete_user = false;
							}
						}
						if($delete_user){
							continue;
						}
						$info_lang[$language] = array(array(), $content);
					}
					$info_lang[$language][0][$value->users_id] = $user->getSha();
				}
			}
			if(!empty($info_lang)){
				foreach ($info_lang as $value) {
					$alias = $value[0];
					$content = $value[1];
					$inform = new Inform($title, $content, false, $alias, $this, array(), array('email')); //Exclude email
					$inform->send();
				}
			}
		}
		return true;
	}
	

	public function save(array $options = array()){
		$app = ModelLincko::getApp();
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
		if(!isset($this->id)){
			$approver = false;
			$pivots = new \stdClass;
			$pivots->{'users>approver'} = new \stdClass;
			if(isset($this->pivots_var->users)){
				foreach ($this->pivots_var->users as $users_id => $user) {
					if(isset($user->approver) && $user->approver){
						//There is already one approver setup
						$approver = true;
						break;
					}
				}
				if(!$approver){
					foreach ($this->pivots_var->users as $users_id => $user) {
						if(isset($user->in_charge) && $user->in_charge){
							$approver = true;
							$pivots->{'users>approver'}->$users_id = true;
						}
					}
				}
			}
			if(!$approver){
				$pivots->{'users>approver'}->{$app->lincko->data['uid']} = true;
			}
			$this->pivots_format($pivots, false);
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

		$new = false;
		if(!isset($this->id)){
			$new = true;
		}

		$return = parent::save($options);

		//toto => temporary solution, it will need to be refactored because of later Team/Entreprise accounts, in a gantt chart each task will act as single task with dependencies, not only as a subtask
		if($new){
			$dependency = $this->getDependency();
			if($dependency && isset($dependency[$this->getTable()]) && isset($dependency[$this->getTable()][$this->id]) && isset($dependency[$this->getTable()][$this->id]['_tasksup']) && count($dependency[$this->getTable()][$this->id]['_tasksup'])>0){
				$tasksup_id = array_keys((array) $dependency[$this->getTable()][$this->id]['_tasksup'])[0]; //Get the first parent
				if($tasksup = $this->getModel($tasksup_id)){
					$tasksup->setHistory('tasksdown_created_at');
					$tasksup->touchUpdateAt();
				}
			}
		}
		
		return $return;
	}

	//$deadline (Carbon)
	public function overdue($deadline){
		if(!is_null($this->start) && !$this->approved){
		//if(!is_null($this->start) && !$this->approved && $this->start < $deadline){ //toto => Enable this line once we disable negative duration, it will speed up the resume calculation
			$duedate = Carbon::createFromFormat('Y-m-d H:i:s', $this->start);
			$duedate->second = $duedate->second + $this->duration;
			if($duedate < $deadline){
				return $deadline->diffInSeconds($duedate, true);
			}
		}
		return false;
	}

	public function toJson($detail=true, $options = 256){ //256: JSON_UNESCAPED_UNICODE
		$this->locked_by = $this->checkLock()[0];
		return parent::toJson($detail, $options);
	}

	public function toVisible(){
		$this->locked_by = $this->checkLock()[0];
		return parent::toVisible();
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array('comments')){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return null;
		}
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}
		$clone = $this->replicate();
		$clone->forceGiveAccess();

		$clone->created_by = $uid;
		if(!is_null($clone->deleted_by)){ $clone->deleted_by = $uid; }
		if(!is_null($clone->approved_by)){ $clone->approved_by = $uid; }
		foreach ($attributes as $key => $value) {
			$clone->$key = $value;
		}
		//Initialization of attributes
		$clone->temp_id = '';
		if(!is_null($clone->deleted_at)){
			$clone->deleted_at = Carbon::createFromFormat('Y-m-d H:i:s', $clone->deleted_at)->addSeconds($offset);
		}
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->locked_by = null;
		$clone->locked_at = null;
		if(!is_null($clone->start)){
			$clone->start = Carbon::createFromFormat('Y-m-d H:i:s', $clone->start)->addSeconds($offset);
		}
		$clone->extra = null;

		//Pivots
		$pivots = new \stdClass;
		$dependencies_visible = $clone::getDependenciesVisible();
		$extra = $this->extraDecode();
		foreach ($dependencies_visible as $dep => $value) {
			if(!in_array($dep, $exclude_pivots) && isset($dependencies_visible[$dep][1])){
				if($extra && (!isset($extra->{'_'.$dep}) || empty($extra->{'_'.$dep}))){
					continue;
				}
				$items = $this->$dep; //Use the relation table
				foreach ($items as $item) {
					$table = $item->getTable();
					if(isset($links[$table][$item->id])){
						if(!isset($pivots->{$dep.'>access'})){ $pivots->{$dep.'>access'} = new \stdClass; }
						$pivots->{$dep.'>access'}->{$links[$table][$item->id]->id} = $item->pivot->access;
						foreach ($dependencies_visible[$dep][1] as $field) {
							if(isset($item->pivot->$field)){
								if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }
								$pivots->{ $dep.'>'.$field}->{$links[$table][$item->id]->id} = $item->pivot->$field;
								//If it's a Carbon object, we add the offset
								if($offset!=0){
									if(preg_match("/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/ui", $item->pivot->$field)){
										try {
											$item->pivot->$field = Carbon::createFromFormat('Y-m-d H:i:s', $item->pivot->$field)->addSeconds($offset);
										} catch (\Exception $e) {}
									}
								}
							}
						}
					}
				}
			}
		}
		$clone->pivots_format($pivots, false);

		$clone->saveHistory(false);
		$clone->save();
		$links[$this->getTable()][$this->id] = $clone;
		if(static::$permission_sheet[0]){ //Permission of owner
			self::$permission_users[$uid][$clone->getTable()][$clone->id] = static::$permission_sheet[0];
		}

		$text = $this->comment;
		$parent_id = $this->parent_id;
		if(preg_match_all("/src=\".+?\/file\/(\d+)\/(.+?)\/(thumbnail|link|download)\/(\d+)\/(.+?)\?.*?\"/ui", $text, $matches,  PREG_SET_ORDER)){
			foreach ($matches as $match) {
				$ori = $match[0];
				$w = $match[1];
				$shaold = $match[2];
				$type = $match[3];
				$fileid = $match[4];
				$filename = $match[5];
				usleep(10000);
				$new = false;
				if($file = Files::withTrashed()->find($fileid)){
					$shanew = base64_encode(Datassl::encrypt_smp($file->link));
					$new = str_replace("/file/$w/$shaold/$type/$fileid/", "/file/$w/$shanew/$type/$fileid/", $ori);
				} else if($file = Files::withTrashed()->where('parent_type', 'projects')->where('parent_id', $this->parent_id)->where('name', $filename)->first()){ //Correct errors
					//If no puid we try to grab it from another similar file
					$puid = $file->puid;
					if(!$puid && $file_puid = Files::withTrashed()->where('link', $file->link)->whereNotNull('puid')->first()){
						$puid = $file_puid->puid;
					}
					if($puid){
						$fileidbis = $file->id;
						$shanew = base64_encode(Datassl::encrypt_smp($file->link));
						$new = str_replace("/file/$w/$shaold/$type/$fileid/", "/file/$w/$shanew/$type/$fileidbis/", $ori);
					}
				}
				if($new){
					//Use https by default
					$new = str_replace("http://", "https://", $new);
					$new = str_replace(":8080/", ":8443/", $new);
					$text = str_replace($ori, $new, $text);
				} else {
					continue; //Do not convert if any issue
				}
			}
			$text = STR::HTMLwithReturnLine($text);
			$time = $this->freshTimestamp();
			$clone::withTrashed()->where('id', $clone->id)->getQuery()->update(['comment' => $text, 'updated_at' => $time, 'extra' => null]);
		}

		//Clone comments (files)
		if(!in_array('comments', $exclude_links)){
			$attributes = array(
				'parent_type' => 'tasks',
				'parent_id' => $clone->id,
			);
			if($comments = $this->comments){
				foreach ($comments as $comment) {
					$comment->clone($offset, $attributes, $links);
				}
			}
		}

		return $clone; //$link is directly modified as parameter &$link
	}

}
