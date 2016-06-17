<?php
// Category api-4
// Category data-1

namespace bundles\lincko\api\models\libs;

use \Exception;
use \libs\Json;
use \libs\STR;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder as Schema;

use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\libs\Comments;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Roles;

abstract class ModelLincko extends Model {

	protected static $app = null;

	use SoftDeletes;
	protected $dates = ['deleted_at'];
	protected $with_trash = false;
	protected static $with_trash_global = false;

	protected $guarded = array('*');

////////////////////////////////////////////

	protected static $schema_table = array();

	//Force to save user access for the user itself
	protected static $save_user_access = true;

	//It force to save, even if dirty is empty
	protected $force_save = false;

	//(used in "toJson()") This is the field to show in the content of history (it will add a prefix "+", which is used for search tool too)
	protected $show_field = false;

	//(used in "toJson()") All field to include in search engine, it will add a prefix "-"
	protected $search_fields = array();

	protected $name_code = 0;
	//Key: Column title to record
	//Value: Title of record
	protected $archive = array(
		'created_at' => 1,  //[{un|ucfirst}] created a new item
		'_' => 2,//[{un|ucfirst}] modified an item
		'_access_0' => 96, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to an item
		'_access_1' => 97, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to an item
		'_restore' => 98,//[{un|ucfirst}] restored an item
		'_delete' => 99,//[{un|ucfirst}] deleted an item
	);

	//When call toJson, convert fields to timestamp format if the field exists only
	protected static $class_timestamp = array(
		'created_at',
		'updated_at',
		'deleted_at',
	);
	protected $model_timestamp = array();

	//When call toJson, convert fields to integer format if the field exists only
	protected static $class_integer = array(
		'created_by',
		'updated_by',
		'deleted_by',
	);
	protected $model_integer = array();

	//When call toJson, convert fields to boolean format if the field exists only
	protected static $class_boolean = array(
		'access',
		'new',
	);
	protected $model_boolean = array();

	//Record if the user is Locked and Visible [users_id => [false, false]]
	protected static $contacts_list = array();
	protected $contactsLock = false; //If true, do not allow to delete the user from the contact list
	protected $contactsVisibility = false; //If true, it will appear in user contact list

	//NOTE: All variables in this array must exist in the database, otherwise an error will be generated during SLQ request.
	protected static $foreign_keys = array(); //Define a list of foreign keys, it helps to give a warning (missing arguments) to the user instead of an error message. Keys are columns name, Values are Models' link. It's also used to build relationships.

	protected static $relations_keys_checked = false; //(not used anymore) At false it will help to construct the list only once

	//NOTE: Must exist in child "data"
	protected static $relations_keys = array(); //(not used anymore) This is a list of parent Models, it helps the front server to know which elements to update without the need of updating all elements and overkilling the CPU usage. This should be accurate using Models' name.

	//It should be a array of [key1:val1, key2:val2, etc]
	//It helps to recover some iformation on client side
	protected $historyParameters = array();

	//Tell which parent role to check if the model doesn't have one, for example Tasks will check Projects if Tasks doesn't have role permission.
	protected static $parent_list = null;

	//This enable or disable the ability to give a permission to a single element.
	protected static $allow_single = false;
	//This enable or disable the ability to give a role permission to a single element with it's children.
	protected static $allow_role = false;

	//Is true when we are saving a new model
	protected $new_model = false;

	//turn it on for debug purpose only
	protected static $debugMode = false;

	/*
	Roles
	0: read
	1: read + create
	2: read + create + edit
	3: read + create + edit + delete
	*/
	protected static $permission_sheet = array(
		0, //[R] owner (It will be given by default for all Owner. It overwrite any other value if higher)
		0, //[R] max allow || super (this value give a limitation from the value got from the Roles table)
	);

	//Interger 1 if all users have super access to the element
	//If an array, tell super access for each user
	protected static $permission_super = array();

	//It record the permission found to speed up calculation in Data.php
	//Level of perission for the model (0: R, 1: RC, 2: RCU, 3: RCUD)
	protected static $permission_users = array();

	protected $parent_item = null;
	public $_parent = array(null, -1);

	/*
		Model variables linked to the user ID
		START
	*/

		//Return true if the user is allowed to access(read) the model. We use an attribute to avoid too many mysql request in Data.php
		protected $accessibility = null;

		//Record the current user ID, will reset some variable if the user ID change
		protected $record_user = null;

	/*
		Model variables linked to the user ID
		END
	*/

	//Vraiable used to pass some values through scopes
	protected $var = array();

	protected static $columns = array();

	//Note: In relation functions, cannot not use underscore "_", something like "tasks_users()" will not work.

	//List of relations we want to make available on client side
	protected static $dependencies_visible = array();

	//
	protected $pivots_var = null;

	//No need to abstract it, but need to redefined for the Models that use it
	public function users(){
		return false;
	}

	//Many(Roles) to Many Poly (Users)
	public function perm($users_id=false){
		$app = self::getApp();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'parent', 'users_x_roles_x', 'parent_id', 'roles_id')->where('users_id', $users_id)->withPivot('access', 'single', 'parent_id', 'parent_type')->take(1);
	}

	//One(?) to Many(Comments)
	public function comments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'parent_id')->where('parent_type', $this->getTable());
	}

	//Many(Roles) to Many Poly (Users)
	public function rolesUsers(){
		$app = self::getApp();
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'parent', 'users_x_roles_x', 'parent_id', 'users_id')->withPivot('access', 'single', 'roles_id', 'parent_id', 'parent_type')->take(1);
	}

	public function __construct(array $attributes = array()){
		$app = self::getApp();
		parent::__construct($attributes);
		//$db = Capsule::connection($this->connection);
		//$db->enableQueryLog();
		if(isset($app->lincko->data['uid'])){
			$this->record_user = $app->lincko->data['uid'];
		}
	}

////////////////////////////////////////////
	//VALIDATION METHODS

	public static function isValid($form){
		return true;
	}

	public static function noValidMessage($return, $function=__FUNCTION__){
		if(!$return){
			$app = self::getApp();
			$app->lincko->data['fields_not_valid'][] = preg_replace('/^valid/ui', '', $function, 1);
		}
		return $return;
	}

	//The value has to be previously converted (int)boolval(var) because of MySQL => 0|1
	public static function validBoolean($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && ($data==0 || $data==1);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validNumeric($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_numeric($data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validRCUD($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && $data>=0 && $data<=3;
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validProgress($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && $data>=0 && $data<=100;
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validDate($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validType($data, $optional=false){
		if($optional && empty($data)){ return true; }
		if(is_null($data)){ //It can be at root level
			return true;
		} else {
			$parent_list = static::$parent_list;
			if(!is_array($parent_list) && is_string($parent_list)){
				$parent_list = array($parent_list);
			}
			$return = is_string($data) && !empty($parent_list) && in_array($data, $parent_list) && preg_match("/^[a-z]{0,104}$/u", $data);
		}
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validChar($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>=0 && preg_match("/^.{0,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validTitle($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>=0 && preg_match("/^.{0,200}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validText($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validTextNotEmpty($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>0;
		return self::noValidMessage($return, __FUNCTION__);
	}

	//191 is limited by MySQL for Indexing
	public static function validDomain($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^.{1,191}$/u", trim($data)) && preg_match("/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validURL($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^[a-zA-Z0-9]{3,104}$/u", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	//191 is limited by MySQL for Indexing
	public static function validEmail($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^.{1,191}$/u", trim($data)) && preg_match("/^.{1,100}@.*\..{2,4}$/ui", trim($data)) && preg_match("/^[_a-z0-9-%+]+(\.[_a-z0-9-%+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validPassword($data, $optional=false){
		if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^[\w\d]{6,60}$/u", $data);
		return $return;
	}

////////////////////////////////////////////

	//Scan the list and tell if the user has an access to it by filtering it (mainly used for Data.php)
	//The unaccesible one will be deleted in Data.php by hierarchy
	public static function filterPivotAccessList(array $list, $suffix='_id'){
		$result = array();
		$table = (new static)->getTable();
		$attributes = array( 'table' => $table, );
		$pivot = new PivotUsers($attributes);
		if($pivot->tableExists($pivot->getTable())){
			$pivot = $pivot->whereIn($table.$suffix, $list)->withTrashed()->get();
			foreach ($pivot as $key => $value) {
				if($value->access){
					$uid = (integer) $value->users_id;
					$id = (integer) $value->{$table.$suffix};
					if(!isset($result[$uid])){ $result[$uid] = array(); }
					$result[$uid][$id] = (array) $value->attributes;
				}
			}
		}
		return $result;
	}

	//By default just return the list as it is
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array()){
		return $result;
	}

	public static function getApp(){
		if(is_null(self::$app)){
			self::$app = \Slim\Slim::getInstance();
		}
		return self::$app;
	}

	public static function getTableStatic(){
		return (new static())->getTable();
	}


	public function tableExists($table){
		$app = self::getApp();
		$connection = $this->getConnectionName();
		if(!isset(self::$schema_table[$connection])){
			self::$schema_table[$connection] = array();
			if(isset($app->lincko->databases[$connection]) && isset($app->lincko->databases[$connection]['database'])){
				$sql = 'select `table_name` from `information_schema`.`tables` where `table_schema` = ?;';
				$db = Capsule::connection($connection);
				$database = Capsule::schema($connection)->getConnection()->getDatabaseName();
				$tables = $db->select( $sql , [$database] );
				foreach ($tables as $value) {
					if(isset($value->table_name)){
						self::$schema_table[$connection][$value->table_name] = true;
					}
				}
			}
		}
		if(isset(self::$schema_table[$connection][$table])){
			return true;
		}
		return false;
	}

	public static function getTablesList(){
		return self::$schema_table;
	}

	//This function helps to get all instance related to the user itself only
	//It needs to redefine the related function users() too
	//IMPORTANT: getLinked must check if the user has access to it, a good example is Tasks model which include all tasks with access 1 and tasks that belongs to projects with access authorized.
	//$list is used to add more IDs previously found by other methods
	//$get also force the accessibility attribute to true
	public function scopegetItems($query, $list=array(), $get=false){
		if(method_exists(get_called_class(), 'users')){
			// user() must be defined has a relation, if not it will crash
			$query = $query
			->whereHas('users', function ($query) {
				$app = self::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where('access', 1);
			});
		} else {
			$query = $query
			->where('id', -1); //Force to return null
		}
		if(self::$with_trash_global){
			$query = $query->withTrashed();
		}
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true; //Because getLinked() only return all with Access allowed
			}
			return $result;
		} else {
			return $query;
		}
	}

	//This function does the same as getItems, but the calculation is heavier since it's requesting every parent, getItems is using a list to simulate dependencies (previously got in Data.php)
	public function scopegetLinked($query, $with=false){
		$arr = array();
		$parentType = $this::getParentList();
		if(count($parentType)>0){
			if(is_string($parentType)){
				if(method_exists(get_called_class(), $parentType)){
					$arr[] = $parentType;
				}
			} else if(is_array($parentType)){
				foreach ($parentType as $type) {
					if(method_exists(get_called_class(), $type)){
						$arr[] = $type;
					}
				}
			}
		}
		if($with){
			foreach ($arr as $type) {
				$query = $query->with($type);
			}
		}
		$list = (new Data())->getTrees($arr)[2];
		if($this->with_trash){
			$query = $query->withTrashed();
		}
		return $query->getItems($list);
	}

	public static function isParent($type){
		$parentType = self::getParentList();
		if(is_string($parentType) && $type==$parentType){
			return true;
		} else if(is_array($parentType) && in_array($type, $parentType)){
			return true;
		}
		return false;
	}

	public static function getColumns(){
		$model = new static();		
		$schema = $model->getConnection()->getSchemaBuilder();
		if(!isset(self::$columns[$model->getTable()])){
			self::$columns[$model->getTable()] = array();
		}
		self::$columns[$model->getTable()] = $schema->getColumnListing($model->getTable());
		return self::$columns[$model->getTable()];
	}

	public function getWorkspaceID(){
		if(!$this->checkAccess(false)){
			return -1;
		}
		$parent = $this->getParent(); //It has to be called first to insure _parent is setup
		if($this->getTable()=='workspaces'){
			return (int) $this->id;
		} else if($this->parent_type=='workspaces' && $this->parent_id==0){
			return 0;
		} else if($parent){
			return (int) $parent->getWorkspaceID();
		}
		return -1; //this insure that we cannot find the workspace (-1 means that we are in root directory)
	}

	public function createdBy(){
		if(isset($this->created_by)){
			return $this->created_by;
		}
		return false;
	}

	//Check if the user has access to the object
	public static function getModel($id){
		if($model = static::find($id)){
			if($model->checkAccess(false)){
				return $model;
			}
		}
		return false;
	}

	public function setParentAttributes(){
		$app = self::getApp();
		if(is_array($this::$parent_list) && isset($this->parent_type) && in_array($this->parent_type, $this::$parent_list)){
			$this->_parent[0] = (string) $this->parent_type;
		} else if(is_string($this::$parent_list)){
			$this->_parent[0] = (string) $this::$parent_list;
		} else {
			$this->_parent[0] = null;
		}

		if(!isset($this->parent_id)){
			$this->_parent[1] = 0;
			if($this->_parent[0]=='workspaces'){
				$this->_parent[1] = (int) $app->lincko->data['workspace_id'];
			} else {
				$this->_parent[0] = null;
			}
		} else if(empty($this->_parent[0])){
			$this->_parent[0] = null;
			$this->_parent[1] = 0;
		} else {
			$this->_parent[1] = (int) $this->parent_id;
		}

		$this->parent_type = $this->_parent[0];
		$this->parent_id = $this->_parent[1];
		return $this->_parent;
	}

	public static function getParentList(){
		return static::$parent_list;
	}

	public function getParent(){
		$this->setParentAttributes();
		if(is_string($this->parent_type) && is_integer($this->_parent[1]) && $class = $this::getClass($this->parent_type)){
			if($this->parent_item = $class::find($this->_parent[1])){
				return $this->parent_item;
			}
		}
		$this->parent_item = false;
		return $this->parent_item;
	}

	public static function setDebugMode($onoff=false){
		$onoff = (bool) $onoff;
		self::$debugMode = $onoff;
	}

	public static function getClass($class=false){
		if(!$class){
			$class = self::getTableStatic();
		}
		$fullClass = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC($class);
		if(class_exists($fullClass)){
			return $fullClass;
		}
		return false;
	}

	//Only check the structure of the database
	public function setForceSchema(){
		$timestamp = time();
		$list = array(
			$this->getTable() => array($this->id),
		);
		Users::getUsers($list)->getQuery()->update(['check_schema' => $timestamp]);
		return true;
	}

	//Force to redownload the whole database
	public static function setForceReset($only_workspace=false){
		$app = self::getApp();
		$timestamp = time();
		if($only_workspace){
			$list = array(
				'workspaces' => array($app->lincko->data['workspace_id']),
			);
			Users::getUsers($list)->getQuery()->update(['force_schema' => $timestamp]);
		} else {
			// getQuery() helps to not update Timestamps updated_at and get ride off checkAccess
			Users::getQuery()->update(['force_schema' => $timestamp]);
		}
		return true;
	}

	public function getForceSchema(){
		return false;
	}

	public static function getDependenciesVisible(){
		return static::$dependencies_visible;
	}

	//For any Many to Many that we want to make dependencies visible
	//Add an underscore "_"  as prefix to avoid any conflict ($this->_tasks vs $this->tasks)
	public static function getDependencies(array $list_id, array $classes){
		$dependencies = new \stdClass;
		foreach ($classes as $table => $class) {
			$model = new $class;
			$data = null;
			$dependencies_visible = $model::getDependenciesVisible();
			if(count($dependencies_visible)>0){
				foreach ($dependencies_visible as $dependency => $dependencies_fields) {
					if(count($dependencies_fields)>0 && isset($list_id[$table]) && method_exists($class, $dependency)) {
						if(is_null($data)){
							$data = $model::whereIn($model::getTableStatic().'.id', $list_id[$table]);
						}
						$data = $data->with($dependency);
					}
				}
				if(!is_null($data)){
					$data = $data->where(function ($query) use ($class, $list_id, $table, $dependencies_visible) {
						foreach ($dependencies_visible as $dependency => $dependencies_fields) {
							if(isset($list_id[$table]) && method_exists($class, $dependency)) {
								$query->orWhereHas($dependency, function ($query){
									$query->where('access', 1);
								});
							}
						}
					});
				}
				if(!is_null($data)){
					try { //In case access in not available for the model
						$data = $data->get(['id']);
						foreach ($data as $dep) {
							foreach ($dependencies_visible as $dependency => $dependencies_fields) {
								foreach ($dep->$dependency as $key => $value) {
									if(isset($value->pivot->access) && isset($dependencies_visible[$dependency])){
										if(!isset($dependencies->$table)){ $dependencies->$table = new \stdClass; }
										if(!isset($dependencies->$table->{$dep->id})){ $dependencies->$table->{$dep->id} = new \stdClass; }
										if(!isset($dependencies->$table->{$dep->id}->{'_'.$dependency})){ $dependencies->$table->{$dep->id}->{'_'.$dependency} = new \stdClass; }
										if(!isset($dependencies->$table->{$dep->id}->{'_'.$dependency}->{$value->id})){ $dependencies->$table->{$dep->id}->{'_'.$dependency}->{$value->id} = new \stdClass; }
										foreach ($dependencies_fields as $field) {
											if(isset($value->pivot->$field)){
												$field_value = $dep->formatAttributes($field, $value->pivot->$field);
												$dependencies->$table->{$dep->id}->{'_'.$dependency}->{$value->id}->$field = $field_value;
											}
										}
									}
								}
							}
						}
					} catch (Exception $obj_exception) {
						//Do nothing to continue
						continue;
					}
				}
			}
		}
		return $dependencies;
	}

	public static function getHistories(array $list_id, array $classes, $history_detail=false){
		$app = self::getApp();
		$history = new \stdClass;
		$data = null;
		foreach ($classes as $table => $class) {
			$model = new $class;
			if(isset($list_id[$table]) && count($model->archive)>0){
				if(is_null($data)){
					$data = History::orWhere(function ($query) use ($list_id, $table) {
						$query
						->whereParentType($table)
						->whereIn('history.parent_id', $list_id[$table]);
					});
				} else {
					$data = $data->orWhere(function ($query) use ($list_id, $table) {
						$query
						->whereParentType($table)
						->whereIn('history.parent_id', $list_id[$table]);
					});
				}
			}
		}
		if(!is_null($data)){
			try { //In case access in not available for the model
				$data = $data->get();
				foreach ($data as $key => $value) {
					try { //In case access in not available for the model
						if(isset($classes[$value->parent_type])){
							$model = new $classes[$value->parent_type];
							$created_at = (new \DateTime($value->created_at))->getTimestamp();
							$not = false;
							if(strpos($value->noticed_by, ';'.$app->lincko->data['uid'].';') === false){
								$not = true; //True if need notification
							}
							if(!isset($history->{$value->parent_type})){ $history->{$value->parent_type} = new \stdClass; }
							if(!isset($history->{$value->parent_type}->{$value->parent_id})){ $history->{$value->parent_type}->{$value->parent_id} = new \stdClass; }
							if(!isset($history->{$value->parent_type}->{$value->parent_id}->history)){ $history->{$value->parent_type}->{$value->parent_id}->history = new \stdClass; }
							if(!isset($history->{$value->parent_type}->{$value->parent_id}->history->$created_at)){ $history->{$value->parent_type}->{$value->parent_id}->history->$created_at = new \stdClass; }
							if(!isset($history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id})){ $history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id} = new \stdClass; }
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->timestamp = (integer)$created_at;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->type = (string)$value->parent_type;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->id = (integer)$value->parent_id;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->by = (integer)$value->createdBy();
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->att = (string)$value->attribute;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->not = (boolean)$not;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->cod = (integer)$value->code;
							if($history_detail || strlen($value->old)<500){
								$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->old = $value->old;
							}
							if(!empty($value->parameters)){
								$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->par = json_decode($value->parameters);
							}
						}
					} catch (Exception $obj_exception) {
						continue;
					}
				}
			} catch (Exception $obj_exception) {
				//Do nothing to continue
			}
		}
		return $history;
	}

	//detail help to get history detail of an item, we do not allow it at the normal use avoiding over quota memory
	public function getHistory($history_detail=false){
		$app = self::getApp();
		$history = new \stdClass;
		$parameters = array();
		if(count($this->archive)>0 && isset($this->id)){
			$records = History::whereParentType($this->getTable())->whereParentId($this->id)->get();
			foreach ($records as $key => $value) {
				$created_at = (new \DateTime($value->created_at))->getTimestamp();
				$not = false;
				if(strpos($value->noticed_by, ';'.$app->lincko->data['uid'].';') === false){
					$not = true; //True if need notification
				}
				if(!isset($history->$created_at)){ $history->$created_at = new \stdClass; }
				if(!isset($history->$created_at->{$value->id})){ $history->$created_at->{$value->id} = new \stdClass; }
				$history->$created_at->{$value->id}->timestamp = (integer)$created_at;
				$history->$created_at->{$value->id}->type = (string)$value->parent_type;
				$history->$created_at->{$value->id}->id = (integer)$value->parent_id;
				$history->$created_at->{$value->id}->by = (integer)$value->createdBy();
				$history->$created_at->{$value->id}->att = (string)$value->attribute;
				$history->$created_at->{$value->id}->not = (boolean)$not;
				$history->$created_at->{$value->id}->cod = (integer)$value->code;
				if($history_detail || strlen($value->old)<500){
					$history->$created_at->{$value->id}->old = $value->old;
				}
				if(!empty($value->parameters)){
					$parameters = $history->$created_at->{$value->id}->par = json_decode($value->parameters);
				}
			}
		}
		$history = (object) array_merge((array) $history, (array) $this->getHistoryCreation());
		return $history;
	}

	public function getHistoryCreation(array $parameters = array()){
		$app = self::getApp();
		$history = new \stdClass;
		$created_at = (new \DateTime($this->created_at))->getTimestamp();
		$not = false;
		if(isset($this->noticed_by)){
			if(strpos($this->noticed_by, ';'.$app->lincko->data['uid'].';') === false){
				$not = true; //True if need notification
			}
		}
		$code = 1; //Default created_at comment
		if(array_key_exists('created_at', $this->archive)){
			$code = $this->archive['created_at'];
		}
		$history->$created_at = new \stdClass;
		$history->$created_at->{'0'} = new \stdClass;
		//Because some models doesn't have creacted_by column (like the workspaces)
		$created_by = null;
		if(isset($this->created_by)){
			$created_by = $this->created_by;
		}
		$history->$created_at->{'0'}->timestamp = (integer) $created_at;
		$history->$created_at->{'0'}->type = (string) $this->getTable();
		$history->$created_at->{'0'}->id = (integer) $this->id;
		$history->$created_at->{'0'}->by = (integer) $created_by;
		$history->$created_at->{'0'}->att = 'created_at';
		$history->$created_at->{'0'}->old = null;
		$history->$created_at->{'0'}->not = (boolean) $not;
		$history->$created_at->{'0'}->cod = (integer) $code;
		if(!empty($parameters)){
			$history->$created_at->{'0'}->par = (object) $parameters;
		}
		return $history;
	}

	/*
		$parameters should be a array of [key1:val1, key2:val2, etc]
	*/
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		$app = self::getApp();
		$namespace = (new \ReflectionClass($this))->getNamespaceName();
		if(count($this->archive)==0 || $this->getTable()=='history' || $namespace!='bundles\lincko\api\models\data'){ //We exclude history itself to avoid looping
			return true;
		}
		$history = new History;
		$history->created_by = $app->lincko->data['uid'];
		$history->parent_id = $this->id;
		$history->parent_type = $this->getTable();
		$history->pivot_type = $pivot_type;
		$history->pivot_id = $pivot_id;
		$history->code = $this->getArchiveCode($key, $new);
		$history->attribute = $key;
		if(!is_null($old)){ $history->old = $old; }
		if(!empty($parameters)){
			$history->parameters = json_encode($parameters, JSON_FORCE_OBJECT);
		}
		$history->save();
	}

	public function getHistoryTitles(){
		$app = self::getApp();
		$connectionName = $this->getConnectionName();
		$titles = new \stdClass;
		foreach ($this->archive as $key => $value) {
			$titles->$value = $app->trans->getBRUT($connectionName, 1, $value);
		}
		$titles->{'0'} = $app->trans->getBRUT($connectionName, 1, $this->name_code);
		return $titles;
	}

	protected function getArchiveCode($column, $value){
		if(is_bool($value)){
			$value = (int) $value;
		}
		$value = (string) $value;
		if(array_key_exists($column.'_'.$value, $this->archive)){
			return $this->archive[$column.'_'.$value];
		} else if(array_key_exists($column, $this->archive)){
			return $this->archive[$column];
		}
		return $this->archive['_']; //Neutral comment
	}

	public function getContactsLock(){
		return $this->contactsLock;
	}

	public function getContactsVisibility(){
		return $this->contactsVisibility;
	}

	public function setContacts(){
		$users = array();
		if(isset($this->created_by) && $this->created_by>0){
			$users[] = $this->created_by;
		}
		if(isset($this->updated_by) && $this->updated_by>0){
			$users[] = $this->updated_by;
		}
		if($this->getTable() == 'users'){
			$users[] = $this->id;
		}
		$users = array_keys(array_flip($users));
		$contactsLock = $this->getContactsLock();
		$contactsVisibility = $this->getContactsVisibility();
		if($contactsLock || $contactsVisibility){
			foreach ($users as $value) {
				if(!isset(self::$contacts_list[$value])){
					self::$contacts_list[$value] = array($contactsLock, $contactsVisibility);
				} else if($contactsLock){
					self::$contacts_list[$value][0] = true;
				} else if($contactsVisibility){
					self::$contacts_list[$value][1] = true;
				}
			}
		}
		return $users;
	}

	//This function helps to delete the indicator as new for an item, it means we already saw it once
	//It also place at false all notifications since the user aknowledge the latest information by viewing the element
	public function viewed(){
		$app = self::getApp();
		//$this->noticed($category, $id, true); //We place at false all notifications (considerate as viewed) => after brainstorming, we keep up notification, even if the tasks as been opened
		if (isset($this->id) && isset($this->viewed_by)) {
			if(strpos($this->viewed_by, ';'.$app->lincko->data['uid'].';') === false){
				$viewed_by = $this->viewed_by = $this->viewed_by.';'.$app->lincko->data['uid'].';';
				$this::where('id', $this->id)->getQuery()->update(['viewed_by' => $viewed_by]);
				$this->touchUpdateAt();
				return true;
			}
		}
		return false;
	}

	public function noticed(){
		$app = self::getApp();
		$list = array();
		$list[$this->getTable()] = array( $this->id => true, );
		History::historyNoticed($list);
	}

	//In case the developer change the user ID, we reset all access
	public function checkUser(){
		$app = self::getApp();
		if(!isset($app->lincko->data['uid'])){
			$errmsg = $app->trans->getBRUT('api', 0, 2); //Please sign in.
			$this::errorMsg('No user logged', $errmsg);
			return false;
		} else if($this->record_user != $app->lincko->data['uid']){
			$this->record_user = null;
			$this->accessibility = null;
			return $this->record_user = $app->lincko->data['uid'];
		}
		return $app->lincko->data['uid'];
	}

	public function getPermissionMax($users_id = false){
		$app = self::getApp();
		$this->checkUser();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		if( !isset(self::$permission_users[$users_id]) ){ self::$permission_users[$users_id] = array(); }
		if( !isset(self::$permission_users[$users_id][$this->getTable()]) ){ self::$permission_users[$users_id][$this->getTable()] = array(); }
		if(  isset(self::$permission_users[$users_id][$this->getTable()][$this->id]) ){
			return self::$permission_users[$users_id][$this->getTable()][$this->id];
		}
		//Check in order or speed code priority
		$perm = 0;
		//Check ownership (faster to check)
		if(static::$permission_sheet[0] > $perm){
			$perm = $this->getPermissionOwner($users_id);
		}
			
		//Check role
		if(static::$permission_sheet[1] > $perm){
			if(self::getWorkspaceSuper($users_id)){ //Check if super user (highest priority)
				$perm = static::$permission_sheet[1];
			} else {
				$role_perm = $this->getRole($users_id);
				if($role_perm > static::$permission_sheet[1]){ //There is a limitation allowed per model that can be different than the database setup.
					$role_perm = static::$permission_sheet[1];
				}
				if($role_perm > $perm){
					$perm = $role_perm;
				}
			}
		}

		$perm = intval($perm);
		self::$permission_users[$users_id][$this->getTable()][$this->id] = $perm;

		return $perm;
	}

	public static function getWorkspaceSuper($users_id=false){
		$app = self::getApp();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		$super = 0;
		if(isset(static::$permission_super[$users_id])){
			return static::$permission_super[$users_id];
		} else if($workspace = Workspaces::find($app->lincko->data['workspace_id'])){ //This insure to return 0 at shared workspace
			$pivot = $workspace->getUserPivotValue('super', $users_id);
			if($pivot[0]){
				$super = (int) $pivot[1];
			}
		}
		static::$permission_super[$users_id] = $super;
		return $super;
	}

	public static function getPermissionSheet(){
		return static::$permission_sheet;
	}

	public function getPermissionOwner($users_id = false, $perm = 0){
		$app = self::getApp();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		if(static::$permission_sheet[0] > $perm && $this->createdBy() == $users_id){
			$perm = static::$permission_sheet[0];
		}
		return $perm;
	}

	//It checks if the user has access to edit it
	public function getRole($users_id=false, $suffix=false){
		$app = self::getApp();
		$this->checkUser();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		if( isset(self::$permission_users[$users_id]) && isset(self::$permission_users[$users_id][$this->getTable()]) && isset(self::$permission_users[$users_id][$this->getTable()][$this->id]) ){
			return self::$permission_users[$users_id][$this->getTable()][$this->id];
		}
		$model = $this;
		$check_single = true; //We only check single of the model itself, not its parents
		$suffix = $this->getTable();
		$role = false;
		$perm = 0; //Only allow reading
		$table = array();
		$loop = 20; //It avoids infinite loop
		while($loop>=0){
			$loop--;
			$role = false;
			if(!in_array($model->getTable(), $table)){ //It avoids to loop the same model
				if($model::$allow_role || $model::$allow_single){
					$pivot = $model->getRolePivotValue($users_id);
					if(!$pivot[0]){ //Check for shared workspace
						$model->setParentAttributes();
						if($model->parent_type=='workspaces' && $model->parent_id==0){
							//By default, give Administrator role to all users inside shared workspace
							if($role = Roles::find(1)){
								if(isset($role->{'perm_'.$suffix})){ //Per model
									$perm = $role->{'perm_'.$suffix};
								} else { //General
									$perm = $role->perm_all;
								}
								$loop = 0;
								break;
							}
						}
					}
					if($pivot[0]){
						if($check_single && $model::$allow_single && $pivot[2]){ //Priority on single over Role
							$perm = $pivot[2];
							$loop = 0;
							break;
						} else if($model::$allow_role && $pivot[1]){
							if($role = Roles::find($pivot[1])){
								if(isset($role->{'perm_'.$suffix})){ //Per model
									$perm = $role->{'perm_'.$suffix};
								} else { //General
									$perm = $role->perm_all;
								}
								$loop = 0;
								break;
							}
						}
					}
				}
				$table[] = $model->getTable();
				if($model = $model->getParent()){
					$check_single = false;
					continue;
				}
			}
			$loop = 0;
			break;
		}
		return $perm;
	}

	//It checks if the user has access to it
	public function checkAccess($show_msg=true){
		$app = self::getApp();
		$this->checkUser();
		if(!is_bool($this->accessibility)){
			$this->accessibility = (bool) false; //By default, for security reason, we do not allow the access
			//If the element exists, we check if we can find it by getLinked
			if(isset($this->id)){
				if($this->getLinked()->whereId($this->id)->take(1)->count() > 0){
					$this->accessibility = (bool) true;
				}
			}
			//If it's a new element, we check if we can access it's parent (we don't have to care about element linked to Workspaces|NULL since ID will be current workspace, so $parent will exists)
			else {
				$parent = $this->getParent();
				if($parent){
					$this->accessibility = $parent->checkAccess($show_msg);
				}
				//Root directory
				else if(empty($parent_type) && empty($parent_id)){
					$this->accessibility = (bool) true;
				}
			}
			
		}
		if($this->accessibility){
			return true;
		} else {
			$suffix = $this->getTable();
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$suffix = $this->getTable();
			\libs\Watch::php($suffix." :\n".parent::toJson(), $msg, __FILE__, true);
			if($show_msg && !self::$debugMode){
				$json = new Json($msg, true, 406);
				$json->render();
			}
			return false;
		}
	}

	protected function formatLevel($level){
		if(is_string($level)){
			if(strtolower($level) == 'create' || strtolower($level) == 'creation' || strtolower($level) == 'creating'){ $level = 1; } //create
			else if(strtolower($level) == 'edit' || strtolower($level) == 'edition' || strtolower($level) == 'editing'){ $level = 2; } //edit
			else if(strtolower($level) == 'delete' || strtolower($level) == 'deletion' || strtolower($level) == 'deleting'){ $level = 3; } //delete
			else if(strtolower($level) == 'error'){ $level = 4; } //force error
			else { $level = 0; } //read
		} else if(is_integer($level)){
			if($level <0 && $level > 4){ $level = 4; }
		} else {
			$level = 4;
		}
		return $level;
	}

	//It checks if the user has access to edit it
	public function checkPermissionAllow($level, $msg=false){
		$app = self::getApp();
		$this->checkUser();
		if(!$this->checkAccess()){
			return false;
		}
		$allow = false;
		$level = $this->formatLevel($level);
		if($level<=0){
			$allow = true;
		} else if($level<4){
			$perm = $this->getPermissionMax();
			if($level<=$perm){
				$allow = true;
			}
		}
		if($allow){
			return true;
		}
		$suffix = $this->getTable();
		$this::errorMsg($suffix." :\n".parent::toJson(), $msg);
		return false;
	}

	protected static function errorMsg($detail='', $msg=false){
		$app = self::getApp();
		if(!$msg){
			$msg = $app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		}
		\libs\Watch::php($detail, $msg, __FILE__, true);
		if(!self::$debugMode){
			$json = new Json($msg, true, 406);
			$json->render();
		}
		return false;
	}

	public function forceSaving($force=true){
		$this->force_save = (boolean) $force;
	}

	//When save, it helps to keep track of history
	public function save(array $options = array()){
		if(!$this->checkAccess()){
			return false;
		} else if(!isset($this->id) && !$this->checkPermissionAllow('create')){
			return false;
		} else if(isset($this->id) && !$this->checkPermissionAllow('edit')){
			return false;
		}
		$app = self::getApp();

		//Insure that the user has at least a read access to the element where it's attached
		//The access to the parent has been previously check in $this->checkAccess()
		$parent = $this->getParent();
		$columns = self::getColumns();

		//Indicate that the user itself has already viewed the last modification
		if(in_array('viewed_by', $columns)){
			//On front end, if the developper wants to know if it's a new or updated element, he can compare created_at and updated_at
			$viewed_by = ';'.$app->lincko->data['uid'].';';
			$this->viewed_by = $viewed_by; //Reset
		}

		//Indicate that the user aknowledge the creation notification
		if(in_array('noticed_by', $columns)){
			$noticed_by = ';'.$app->lincko->data['uid'].';';
			if(strpos($this->noticed_by, $noticed_by) === false){
				$noticed_by .= $this->noticed_by;
			}
			$this->noticed_by = $noticed_by;
		}

		$dirty = $this->getDirty();
		$original = $this->getOriginal();

		$new = !isset($this->id);

		//Only check foreign keys for new items
		if($new){
			if(in_array('created_by', $columns)){
				$this->created_by = $app->lincko->data['uid'];
			}
			if(in_array('updated_by', $columns)){
				$this->updated_by = $app->lincko->data['uid'];
			}

			$missing_arguments = array();
			foreach($this::$foreign_keys as $key => $value) {
				if(!isset($this->$key)){
					$missing_arguments[] = $key;
				}
			}
			if(!empty($missing_arguments)){
				$app->lincko->translation['missing_arguments'] = implode(", ", $missing_arguments);
				$msg = $app->trans->getBRUT('api', 4, 1); //Unable to create the item, your request lacks arguments: @@missing_arguments~~
				\libs\Watch::php($msg, 'Missing arguments', __FILE__, true);
				$json = new Json($msg, true, 406);
				$json->render();
				return false;
			}
		} else {
			if(in_array('updated_by', $columns)){
				$this->updated_by = $app->lincko->data['uid'];
			}
			$this->updateTimestamps();
		}
		$app->lincko->translation['fields_not_valid'] = $app->trans->getBRUT('api', 4, 3); //[unknwon]
		$app->lincko->data['fields_not_valid'] = array();
		if(!$this::isValid($this)){
			if(!empty($app->lincko->data['fields_not_valid'])){
				$app->lincko->translation['fields_not_valid'] = implode(", ", $app->lincko->data['fields_not_valid']);
			}
			$app->lincko->translation['table_name'] = $this->table;
			$msg = $app->trans->getBRUT('api', 4, 2); //The format is not valid (@@table_name~~), some fields do not match the minimum request, or are missing: @@fields_not_valid~~
			\libs\Watch::php($this, $msg, __FILE__, true);
			$json = new Json($msg, true, 406);
			$json->render();
			return false;
		}

		//Give access to the user itself
		if($new && isset($app->lincko->data['uid'])){
			$pivots = new \stdClass;
			$pivots->{'users>access'} = new \stdClass;
			$pivots->{'users>access'}->{$app->lincko->data['uid']} = true;
			$this->pivots_format($pivots, false);
		}

		//For debug mode, do not record new model
		if($new && self::$debugMode){
			return true;
		}

		$attributes = array();
		//Insure to not send to mysql any field that does not exists (for example parent_type)
		//Store thoese values to reapply them after saving
		foreach ($this->attributes as $key => $value) {
			if(!in_array($key, $columns)){
				$attributes[$key] = $this->attributes[$key];
				unset($this->attributes[$key]);
			}
		}
		$dirty = $this->getDirty();
		//do nothing if dirty is empty
		if(!$this->force_save && count($dirty)<=0){
			return true;
		}
		$db = Capsule::connection($this->connection);
		$db->beginTransaction(); //toto: It has to be tested with user creation because it involve 2 transaction together
		try {
			$return = parent::save($options);
			//Reapply the fields that were not part of table columns
			foreach ($attributes as $key => $value) {
				$this->attributes[$key] = $value;
			}
			$this->pivots_save();
			if($parent){
				$parent->touchUpdateAt();
			}
			$db->commit();
		} catch(\Exception $e){
			$return = null;
			$db->rollback();
		}

		if($new){
			$this->new_model = true;
		}
		//We do not record any setup for new model, but only change for existing model
		if(!$new){
			foreach($dirty as $key => $value) {
				//We exclude "created_at" if not we will always record it on history table, that might fulfill it too quickly
				if($key != 'created_at' && $key != 'updated_at'){
					$old = null;
					$new = null;
					if(isset($original[$key])){ $old = $original[$key]; }
					if(isset($dirty[$key])){ $new = $dirty[$key]; }
					$code = $this->getArchiveCode($key, $new);
					$code_key = array_search($code, $this->archive);
					if($code_key && $code_key != '_'){ //We excluse default modification
						$this->setHistory($key, $new, $old);
					}
				}
			}
		}
		return $return;
		
	}

	//This will update updated_at, even if the user doesn't have write permission
	public function touchUpdateAt(){
		if (!$this->timestamps || !isset($this->updated_at) || !isset($this->id)) {
			return false;
		}
		$time = $this->freshTimestamp();
		return $this::where('id', $this->id)->getQuery()->update(['updated_at' => $time]);
	}
	
	public function delete(){
		if(!$this->checkPermissionAllow('delete')){
			return false;
		}
		//We don't delete in debug mode
		if(self::$debugMode){
			return true;
		}
		if(!isset($this->deleted_at) && isset($this->attributes) && array_key_exists('deleted_at', $this->attributes)){
			if(array_key_exists('deleted_by', $this->attributes)){
				$app = self::getApp();
				$this->deleted_by = $app->lincko->data['uid'];
				$this->setHistory('_delete');
				$this->save();
				$this->setForceSchema();
			}
			parent::delete();
		}
		return true;
	}

	public function restore(){
		$this->enableTrash(true);
		$permission = $this->checkPermissionAllow('delete');
		$this->enableTrash(false);
		if(!$permission){
			return false;
		}
		//We don't restore in debug mode
		if(self::$debugMode){
			return true;
		}
		if(isset($this->deleted_at) && isset($this->attributes) && array_key_exists('deleted_at', $this->attributes)){
			if(array_key_exists('deleted_by', $this->attributes)){
				$this->deleted_at = null;
				$this->setHistory('_restore');
				$this->save();
				$this->setForceSchema();
			}
			parent::restore();
		}
		return true;
	}

	//True: will display with trashed
	//False (default): will display only not deleted
	public function enableTrash($trash=false){
		$trash = (boolean) $trash;
		$this->with_trash = $trash;
	}

	//True: will display with trashed
	//False (default): will display only not deleted
	public static function enableTrashGlocal($trash=false){
		$trash = (boolean) $trash;
		self::$with_trash_global = $trash;
	}

	/*
		Note: The following information are note built in this method, but are necessary to outup a element Read:
			- _parent: array(0,1)
			- _"dependencies"
			- _perm
			- history
	*/
	public function toJson($detail=true, $options = 0){
		$this->checkAccess(); //To avoid too many mysql connection, we can set the protected attribute "accessibility" to true if getLinked is used using getItems()
		$app = self::getApp();
		$this->setParentAttributes();
		if($detail){ //It's used for word search on Front side (+/- prefix)
			$temp = json_decode(parent::toJson($options));
			foreach ($temp as $key => $value) {
				$prefix = $this->getPrefix($key);
				if(!empty($prefix)){
					$temp_field = $temp->$key;
					unset($temp->$key);
					$temp->{$prefix.$key} = (string)$temp_field;
				}
			}
			if(isset($this->deleted_at) && !is_null($this->deleted_at) && $this->deleted_at instanceof Carbon){
				$temp->deleted_at = $this->deleted_at->format('Y-m-d H:i:s');
			}
			$temp = json_encode($temp, $options);
		} else {
			$temp = parent::toJson($options);
		}
		//Convert DateTime to Timestamp for JS use, it avoid location hour issue. NULL will stay NULL thanks to isset()
		$temp = json_decode($temp);
		//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
		$temp->new = 0;
		if(isset($this->viewed_by)){
			if(strpos($this->viewed_by, ';'.$app->lincko->data['uid'].';') === false){
				$temp->new = 1;
			}
		}

		foreach(self::$class_timestamp as $value) {
			if(isset($temp->$value)){  $temp->$value = (int)(new \DateTime($temp->$value))->getTimestamp(); }
		}
		foreach($this->model_timestamp as $value) {
			if(isset($temp->$value)){  $temp->$value = (int)(new \DateTime($temp->$value))->getTimestamp(); }
		}
		//Convert number to integer. NULL will stay NULL thanks to isset()
		foreach(self::$class_integer as $value) {
			if(isset($temp->$value)){  $temp->$value = (int)$temp->$value; }
		}
		foreach($this->model_integer as $value) {
			if(isset($temp->$value)){  $temp->$value = (int)$temp->$value; }
		}
		//Convert boolean.
		foreach(self::$class_boolean as $value) {
			if(isset($temp->$value)){  $temp->$value = (boolean)$temp->$value; }
		}
		foreach($this->model_boolean as $value) {
			if(isset($temp->$value)){  $temp->$value = (boolean)$temp->$value; }
		}
		
		$temp = json_encode($temp, $options);
		return $temp;
	}

	protected function formatAttributes($attribute, $value){
		if(in_array($attribute, self::$class_timestamp) || in_array($attribute, $this->model_timestamp)){
			$value = (int)(new \DateTime($value))->getTimestamp();
		} else if(in_array($attribute, self::$class_integer) || in_array($attribute, $this->model_integer)){
			$value = (int)$value;
		} else if(in_array($attribute, self::$class_boolean) || in_array($attribute, $this->model_boolean)){
			$value = (boolean)$value;
		}
		return $value;
	}

	protected function getPrefix($value){
		$prefix = '';
		if($value === $this->show_field){
			$prefix = "+";
		} else if(in_array($value, $this->search_fields)){
			$prefix = "-";
		}
		return $prefix;
	}

	public function pivots_format($form, $history_save=true){
		//toto => if the value saved is default or unchanged, we do not record history
		$app = self::getApp();
		$save = false;
		foreach ($form as $key => $list) {
			if( preg_match("/^([a-z0-9_]+)>([a-z0-9_]+)$/ui", $key, $match) && is_object($list) && count((array)$list)>0 ){
				$type = $match[1];
				$column = $match[2];
				foreach ($list as $type_id => $value) {
					//We cannot block or authorize itself
					if($column=='access' && $type_id==$app->lincko->data['uid'] && !static::$save_user_access){
						continue;
					} else
					if(is_numeric($type_id) && (int)$type_id>0){
						$save = true;
						if($this->pivots_var==null){ $this->pivots_var = new \stdClass; }
						if(!isset($this->pivots_var->$type)){ $this->pivots_var->$type = new \stdClass; }
						if(!isset($this->pivots_var->$type->$type_id)){ $this->pivots_var->$type->$type_id = new \stdClass; }
						if(!isset($this->pivots_var->$type->$type_id->$column)){ $this->pivots_var->$type->$type_id->$column = array($value, $history_save); }
					}	
				}
			}
		}
		return $save;	
	}

	protected function pivots_save(array $parameters = array()){
		$app = self::getApp();
		$namespace = (new \ReflectionClass($this))->getNamespaceName();
		if($namespace!='bundles\lincko\api\models\data'){ //We exclude users_x_roles_x itself to avoid looping
			return true;
		}
		$success = true;
		$touch = false;
		//\libs\Watch::php( 'pivots_save', '$pivots_save', __FILE__, false, false, true);
		//checkAccess and checkPermissionAllow are previously used in save()
		//\libs\Watch::php($this, '$this->pivots_var1', __FILE__, false, false, true);
		//\libs\Watch::php($this->pivots_var, '$this->pivots_var2', __FILE__, false, false, true);
		if(is_object($this->pivots_var)){
			foreach ($this->pivots_var as $type => $type_id_list) {
				if(!$success){ break; }
				foreach ($type_id_list as $type_id => $column_list) {
					if(!$success){ break; }
					foreach ($column_list as $column => $result) {
						$history_save = true;
						if(is_array($result)){
							$value = $result[0];
							$history_save = $result[1];
						} else {
							$value = $result;
						}
						//Convert the value to be compriable with database
						if(is_bool($value)){
							$value = (int) $value;
						}
						$value = (string) $value;
						if(!$success){ break; }
						$pivot = false;
						if(method_exists(get_called_class(), $type)){ //Check if the pivot call exists
							$pivot_relation = $this->$type();
							if($pivot_relation !== false && method_exists($pivot_relation,'updateExistingPivot') && method_exists($pivot_relation,'attach')){
								if($pivot = $pivot_relation->find($type_id)){ //Check if the pivot exists
									//Update an existing pivot
									$value_old = (string) $pivot->pivot->$column;
									if($value_old != $value){
										if($pivot_relation->updateExistingPivot($type_id, array($column => $value))){
											$touch = true;
											if($history_save){
												$parameters = array();
												if($type=='users'){
													$parameters['cun'] = Users::find($type_id)->username; //Storing this value helps to avoid many SQL calls later
												}
												$code = $this->getArchiveCode('pivot_'.$column, $value);
												$code_key = array_search($code, $this->archive);
												if($code_key && $code_key != '_'){ //We excluse default modification
													$this->setHistory($column, $value, $value_old, $parameters, $type, $type_id);
												}
											}
										} else {
											$success = false;
										}
									}
									continue;
								} else {
									//Create a new pivot line
									if($column=='access' || !isset($this->pivots_var->$type->type_id->access)){
										//By default, if we affect a new pivot, we always authorized access if it's not specified (for instance a user assigned to a task will automaticaly have access to it)
										$pivot_relation->attach($type_id, array('access' => true, $column => $value)); //attach() return nothing
									} else {
										$pivot_relation->attach($type_id, array($column => $value)); //attach() return nothing
									}
									$touch = true;
									if($history_save){
										$parameters = array();
										if($type=='users'){
											$parameters['cun'] = Users::find($type_id)->username; //Storing this value helps to avoid many SQL calls later
										}
										$code = $this->getArchiveCode('pivot_'.$column, $value);
										$code_key = array_search($code, $this->archive);
										if($code_key && $code_key != '_'){ //We excluse default modification
											$this->setHistory($column, $value, null, $parameters, $type, $type_id);
										}
									}
									continue;
								}
							}
							$success = false;
							break;
						}
						$success = false;
						break;
					}
				}
			}
		}
		if($touch){
			$this->touch();
			//$this->setForceSchema();
			$this->setForceReset(); //[toto] This is wrong, it should be setForceSchema, but this is a quicker way to solve temporary issue (_perm, _tasks, etc  were not refreshed, setForceSchema must include some kind of md5 to compare content of object)
		}
		return $success;
	}

	//By preference, keep it protected
	//public function getUserPivotValue($column, $users_id=false){
	protected function getUserPivotValue($column, $users_id=false){
		$app = self::getApp();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
		$column = strtolower($column);
		if($users = $this->users()){
			if($user = $users->find($users_id)){
				if(isset($user->pivot->$column)){
					return array(true, $user->pivot->$column);
				}
			}
		}
		return array(false, null);
	}

	public static function getRoleAllow(){
		return array(static::$allow_single, static::$allow_role);
	}

	//By preference, keep it protected
	public function getRolePivotValue($users_id){
	//protected function getRolePivotValue($users_id){
		if($roles = $this->rolesUsers()){ //This insure that the Role was not previously deleted
			if($role = $roles->wherePivot('users_id', $users_id)->first()){
				$roles_id = null;
				$single = null;
				if($this::$allow_role){
					$roles_id = $role->pivot->roles_id;
				}
				if($this::$allow_single){
					$single = $role->pivot->single;
				}
				return array(true, $roles_id, $single);
			}
		}
		return array(false, null, null);
	}

	//By preference, keep it protected, public is only for test
	public function setRolePivotValue($users_id, $roles_id=null, $single=null, $history=true){
		$app = self::getApp();

		//We don't allow non-administrator to modify user permission
		if(self::getWorkspaceSuper() == 0){
			$this::errorMsg('No super permission');
			$this->checkPermissionAllow(4);
			return false;
		}
		//We don't record if the model doesn't exists in the database
		if(!isset($this->id)){
			$this::errorMsg('The model does not exists');
			return false;
		}
		$return = false;
		$pivot = $this->getRolePivotValue($users_id);

		//We cannot modify own's permission
		if(!$this->new_model && $users_id == $app->lincko->data['uid']){
			$this::errorMsg('Same user issue');
			return false;
		}

		//It's useless to insert an element wich cannot be checked
		if(!$this::$allow_role && !$this::$allow_single){
			$this::errorMsg('Allow issue');
			return false;
		}

		//If the pivot doesn't exist, we exit
		$user = $this->rolesUsers();
		if($user === false || !method_exists($user,'updateExistingPivot') || !method_exists($user,'attach')){
			$this::errorMsg('Method issue');
			return false;
		}

		$roles_id_old = $pivot[1];
		$single_old = $pivot[2];
		if(!$this::$allow_role){
			$roles_id = null;
		}
		if(!$this::$allow_single){
			$single = null;
		}
		if($pivot[0]){
			if($roles_id_old != $roles_id || $single_old != $single){
				//Modify a line
				$user->updateExistingPivot($users_id, array('roles_id' => $roles_id, 'single' => $single));
				if($history){
					if($roles_id_old != $roles_id){
						$value = $roles_id;
						$value_old = $roles_id_old;
					}
					if($single_old != $single){
						$value = $single;
						$value_old = $single_old;
					}
					$this->setHistory('_', $value, $value_old, array('cun' => Users::find($users_id)->username));
					$this->touch();
				}
				$return = true;
			}
		} else if($roles_id!=null || $single!=null){
			//Create a new line
			$user->attach($users_id, array('roles_id' => $roles_id, 'single' => $single));
			if($history){
				if($roles_id_old != $roles_id){
						$value = $roles_id;
						$value_old = $roles_id_old;
					}
					if($single_old != $single){
						$value = $single;
						$value_old = $single_old;
					}
				$this->setHistory('_', $value, $value_old, array('cun' => Users::find($users_id)->username));
				$this->touch();
			}
			$return = true;
		}
		if($return){
			//Force all linked users to reupload the full data
			self::setForceReset(true);
		}
		
		//Do not change anything, it's the same
		return $return;
	}

}
