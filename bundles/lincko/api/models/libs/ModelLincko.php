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
use \bundles\lincko\api\models\libs\Updates;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\libs\Tree;
use \bundles\lincko\api\models\libs\Models;
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
	protected static $schema_default = array();

	//Force to save user access for the user itself
	protected static $save_user_access = true;

	//It force to save, even if dirty is empty
	protected $force_save = false;

	//It helps to limit a SQL if it's specified as boolean
	protected static $has_perm = null;

	//It forces to recalculate the permission field of all children
	protected $change_permission = false;

	//It forces to check the schema for all users concerned
	protected $change_schema = false;

	//(used in "toJson()") This is the field to show in the content of history (it will add a prefix "+", which is used for search tool too)
	protected $show_field = false;

	//At true we download the corresponding amount within the days specifiied (value of 15 means history of last 15 days or not viewed)
	protected $limit_history = false;

	//At true it will only download a limited number of attrbutes to makes the download much more ligther
	protected $limited = false;

	//(used in "toJson()") All field to include in search engine, it will add a prefix "-"
	protected $search_fields = array();

	protected $name_code = 0;
	//Key: Column title to record
	//Value: Title of record
	protected static $archive = array(
		'created_at' => 1,  //[{un}] created a new item
		'_' => 2,//[{un}] modified an item
		'_access_0' => 96, //[{un}] blocked [{[{cun}]}]'s access to an item
		'_access_1' => 97, //[{un}] authorized [{[{cun}]}]'s access to an item
		'_restore' => 98,//[{un}] restored an item
		'_delete' => 99,//[{un}] deleted an item
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

	//It should be a array of [key1:val1, key2:val2, etc]
	//It helps to recover some iformation on client side
	protected $historyParameters = array();

	//Tell which parent role to check if the model doesn't have one, for example Tasks will check Projects if Tasks doesn't have role permission.
	protected static $parent_list = null;
	protected static $parent_list_soft = null;

	//Keep a record of children tree structure
	protected static $children_tree = null;

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

	//From pivot table, at true it accepts only access at 1, at false it rejects access at 0 but include parent at access at 1
	protected static $access_accept = true;

	//Vraiable used to pass some values through scopes
	protected $var = array();

	protected static $columns = array();

	//Note: In relation functions, cannot not use underscore "_", something like "tasks_users()" will not work.

	//List of relations we want to make available on client side
	protected static $dependencies_visible = array();

	//Pivot to update
	protected $pivots_var = null;

	//This suffix is used for users_x_ , for users, it's specific because it's users_id and users_id_link
	protected static $pivot_users_suffix = '_id';

	public static function getPivotUsersSuffix(){
		return static::$pivot_users_suffix;
	}

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
		$this->connection = $app->lincko->data['database_data'];
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
		//if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && ($data==0 || $data==1);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validNumeric($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_numeric($data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validRCUD($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && $data>=0 && $data<=3;
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validProgress($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && $data>=0 && $data<=100;
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validDate($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validType($data, $optional=false){
		//if($optional && empty($data)){ return true; }
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
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>=0 && preg_match("/^.{0,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validTitle($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>=0 && preg_match("/^.{0,200}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validText($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validTextNotEmpty($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && strlen(trim($data))>0;
		return self::noValidMessage($return, __FUNCTION__);
	}

	//191 is limited by MySQL for Indexing
	public static function validDomain($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^.{1,191}$/u", trim($data)) && preg_match("/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validURL($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^[a-zA-Z0-9]{3,104}$/u", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	//191 is limited by MySQL for Indexing
	public static function validEmail($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^.{1,191}$/u", trim($data)) && preg_match("/^.{1,100}@.*\..{2,4}$/ui", trim($data)) && preg_match("/^[_a-z0-9-%+]+(\.[_a-z0-9-%+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", trim($data));
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function validPassword($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_string($data) && preg_match("/^[\w\d]{6,60}$/u", $data);
		return $return;
	}

	public static function validCode($data, $optional=false){
		//if($optional && empty($data)){ return true; }
		$return = is_numeric($data) && preg_match("/^[\d]{4,6}$/u", $data);
		return $return;
	}

////////////////////////////////////////////

	public static function getHasPerm(){
		return self::$has_perm;
	}

	public function getChildrenTree($item=true){
		if(is_null(self::$children_tree)){
			$list_models = Data::getModels();
			$tree_desc = new \stdClass;
			foreach($list_models as $value) {
				$model = new $value;
				//Ascendant
				$child = 'tree_'.$model->getTable();
				$table = $model->getTable();
				if( !isset(${$child}) ){
					${$child} = new \stdClass;
				}
				$parentList = array();
				$parentType = $model::getParentList();
				$parentList['tree_desc'] = 'tree_desc';
				if(is_array($parentType)){ //A list a parent
					foreach($parentType as $name) {
						if(array_key_exists($name, $list_models)){
							$parentList[$name] = 'tree_'.$name;
						}
					}
				} else if($parentType == '*' || $parentType == '+'){ //All are parents
					if($parentType == '*'){
						$parentList['tree_desc'] = 'tree_desc';
					}
					foreach($list_models as $value_bis) {
						$table_bis = (new $value_bis)->getTable();
						$parentList[$table_bis] = 'tree_'.$table_bis;
					}
				} else if(array_key_exists($parentType, $list_models)){ //Has one parent
					$parentList[$parentType] = 'tree_'.$parentType;
				}
				unset($parentList[$table]); //Avoid recursivity
				foreach($parentList as $name => $parent) {
					if( !isset(${$parent}) ){
						${$parent} = new \stdClass;
					}
					$root_child = $model->getTable();
					${$parent}->$child = ${$child};
					unset(${$parent}->$child); //This helps to delete the prefix 'tree'
					${$parent}->$root_child = ${$child};
				}
				unset($parentList);
			}
			self::$children_tree = $tree_desc;
		}
		if($item){
			if(isset(self::$children_tree->{$this->getTable()})){
				return self::$children_tree->{$this->getTable()};
			}
			return false;
		}
		return self::$children_tree;
	}

	public function getChildren(){
		$list_tables[$this->getTable()] = $this->getTable();
		$loop = true;
		if($tree_tp = $this->getChildrenTree()){
			while(count($tree_tp)>0 && $loop){
				$loop = false;
				foreach ($tree_tp as $key => $value) {
					$list_tables[$key] = $key;
					if(count($value)<=0){
						$loop = true;
						unset($tree_tp[$key]);
						foreach ($tree_tp as $key_bis => $value_bis) {
							$key_tp = array_search($key, $value_bis);
							if($key_tp!==false){
								unset($tree_tp[$key_bis][$key_tp]);
							}
						}
					}
				}
			}
		}

		$tp = Data::getModels();
		$list_models = array();
		foreach ($list_tables as $table_name) {
			if(isset($tp[$table_name])){
				$list_models[$table_name] = $tp[$table_name];
			}
		}
		
		$tree_scan = array();
		$tree_desc = new \stdClass;
		foreach($list_models as $value) {
			$model = new $value;
			//Ascendant
			$child = 'tree_'.$model->getTable();
			$table = $model->getTable();
			if( !isset(${$child}) ){
				${$child} = new \stdClass;
			}
			$parentList = array();
			$parentType = $model::getParentList();
			if(count($parentType)==0){ //It's in the root
				$parentList['tree_desc'] = 'tree_desc';
			} else if(is_array($parentType)){ //A list a parent
				foreach($parentType as $name) {
					if(array_key_exists($name, $list_models)){
						$parentList[$name] = 'tree_'.$name;
					} else if($name == null){ //It's in the root
						$parentList['tree_desc'] = 'tree_desc';
					}
				}
			} else if($parentType == '*' || $parentType == '+'){ //All are parents
				if($parentType == '*'){
					$parentList['tree_desc'] = 'tree_desc';
				}
				foreach($list_models as $value_bis) {
					$table_bis = (new $value_bis)->getTable();
					$parentList[$table_bis] = 'tree_'.$table_bis;
				}
			} else { //Has one parent
				if(array_key_exists($parentType, $list_models)){
					$parentList[$parentType] = 'tree_'.$parentType;
				} else {
					$parentList['tree_desc'] = 'tree_desc';
				}
			}
			foreach($parentList as $name => $parent) {
				if( !isset($tree_scan[$table]) ){
					$tree_scan[$table] = array();
				}
				if(array_key_exists($name, $list_models)){
					$tree_scan[$table][] = $name;
				}
			}
			unset($parentList[$table]); //Avoid recursivity
			foreach($parentList as $name => $parent) {
				if( !isset(${$parent}) ){
					${$parent} = new \stdClass;
				}
				$root_child = $model->getTable();
				${$parent}->$child = ${$child};
				unset(${$parent}->$child); //This helps to delete the prefix 'tree'
				${$parent}->$root_child = ${$child};
			}
			unset($parentList);
		}

		// Get all ID with parent dependencies
		$loop = true;
		$tree_id = array();
		$tree_id[$this->getTable()][$this->id] = $this->id;
		$current_id = array();
		$current_id[$this->getTable()][$this->id] = $this->id;
		$result = new \stdClass;
		while($loop){
			$loop = false;
			$temp = array();
			foreach ($tree_scan as $table => $children) {
				$list = array();
				foreach ($children as $child) {
					if(isset($current_id[$child])){
						$list[$child] = $current_id[$child];
					}
				}
				if(count($list)>0){
					$class = $list_models[$table];
					$class::enableTrashGlobal(true);
					$result_bis = $class::getKids($list);
					$class::enableTrashGlobal(false);

					if(isset($result->$table)){
						$result->$table = $result->$table->merge($result_bis);
					} else {
						$result->$table = $result_bis;
					}
					foreach ($result_bis as $value_bis) {
						$temp[$table][$value_bis->id] = $value_bis->id;
					}
					unset($result_bis);
				}
				
			}
			$current_id = array();
			foreach ($temp as $table => $list) {
				foreach ($list as $id) {
					if(!isset($tree_id[$table][$id])){ //Insure to not record twice the same ID to not enter inside an infinite loop
						$current_id[$table][$id] = $id;
						$tree_id[$table][$id] = $id;
					}
				}
			}
			if(count($current_id)>0){
				$loop = true;
			}
		}
		return array($tree_scan, $tree_desc, $tree_id, $result);
	}

	public function setPerm(){
		$app = self::getApp();
		if(!isset($app->lincko->data['uid'])){
			return array();
		}
		$list_models = Data::getModels();
		$all = array();

		// List up all children table
		$children = array();
		$tp = array();
		$temp = $this->getChildren();
		foreach ($temp[3] as $table => $list) {
			foreach ($list as $model) {
				$children[$table][$model->id] = $model;
			}
		}
		//Include the item into Children
		$children[$this->getTable()][$this->id] = $this;
		$all = array_replace_recursive($all, $children);
		unset($temp);

		//List up all parents
		$root = $this;
		$parents = array();
		$model = $this;
		while($model = $model->getParent()){
			$root = $model;
			$parents[$model->getTable()][$model->id] = $model;
		}
		$all = array_replace_recursive($all, $parents);

		//List up all users involved at highest level (workspace)
		$users = array();
		$users['users'] = array();
		$users_id = array();
		$users_id['users'] = array();
		$class = $root::getClass();
		$list = $class::filterPivotAccessList([$root->id], true);
		foreach ($list as $uid => $value) {
			//Normaly the root level must have $access_accept at true (workspaces, chats, users, projects)
			if($root::$access_accept && reset($value)['access']){
				$users_id['users'][$uid] = $uid; //Root users
			}
		}
		$list = Users::whereIn('id', $users_id['users'])->get();
		foreach ($list as $model) {
			$users['users'][$model->id] = $model;
		}
		$all = array_replace_recursive($all, $users);
		unset($users_id);

		//List up all roles
		$roles = array();
		$roles['roles'] = array();
		$list = Roles::getItems()->get();
		foreach ($list as $model) {
			$roles['roles'][$model->id] = $model;
		}
		$all = array_replace_recursive($all, $roles);
		unset($roles);

		//Add shared workspace manually because it does not exists
		if(
			   ( is_string($root::$parent_list) && $root::$parent_list=='workspaces' && isset($root->parent_id) && empty($root->parent_id) )
			|| ( is_array($root::$parent_list) && in_array('workspaces', $root::$parent_list) && isset($root->parent_type) && $root->parent_type=='workspace' && isset($root->parent_id) && empty($root->parent_id) )
		){
			$workspace = array();
			$root = new $list_models['workspaces'];
			$root->id = 0;
			$root->setParentAttributes();
			$workspace['workspaces'][0] = $root;
			$all = array_replace_recursive($all, $workspace);
		}
		

		//Make sure that the _perm string will be always in the same order
		foreach ($all as $table => $models) {
			ksort($all[$table]);
		}

		$all_id = array();
		foreach ($all as $table => $models) {
			foreach ($models as $id => $model) {
				$all_id[$table][$id] = $id;
				$model->setParentAttributes();
			}
		}
		$tree_access = Data::getAccesses($all_id);

		$tree_super = array(); //Permission allowed for the super user (Priority 1 / fixed), defined at workspace workspace only => Need to scan the tree to assigned children
		$tree_owner = array(); //Permission allowed for the owner (Priority 2 / fixed)
		$tree_single = array(); //Permission allowed for the user at single element level (Priority 3 / cutomized)
		$tree_role = array(); //Permission allowed for the user according the herited Roles(Priority 4 / cutomized) => Need to scan the tree to assigned children
		$tree_roles_id = array();

		//Tell if the user has super access to the workspace
		$work_super = array();
		if(isset($all_id['users'])){
			foreach ($all_id['users'] as $users_id) {
				$work_super[$users_id] = array();
				if(isset($tree_access['workspaces'][$users_id])){
					foreach ($tree_access['workspaces'][$users_id] as $value) {
						if($value['super']){
							$work_super[$users_id][(int)$value['workspaces_id']] = $value['super'];
						}
					}
				}
				if(isset($work_super[$users_id])){
					//Insure to reject super permission for shared workspace
					unset($work_super[$users_id][0]);
				}
			}
		}

		$pivot = PivotUsersRoles::getRoles($all_id);
		
		foreach ($pivot as $value) {
			$table_name = $value->parent_type;
			$id = $value->parent_id;
			$users_id = $value->users_id;
			if(isset($all[$table_name][$id])){
				$model = $all[$table_name][$id];
				//Single (ok)
				if($value->single){
					if($model::getRoleAllow()[0]){ //Single (RCUD)
						$tree_single[$table_name][$users_id][$id] = (int) $value->single;
					}
				}
				//Role (will affect children)
				if($value->roles_id && isset($all_id['roles'][$value->roles_id])){ //The last condition insure that the Role was not deleted
					if($model::getRoleAllow()[1]){ //Role (Role ID)
						$tree_roles_id[$table_name][$users_id][$id] = (int) $value->roles_id;
					}
				}
			}
		}

		//By default, give Administrator role to all users inside shared workspace
		if($app->lincko->data['workspace_id']==0){
			if(isset($all_id['users'])){
				foreach ($all_id['users'] as $users_id) {
					$tree_roles_id['workspaces'][(int)$users_id][0] = 1;
				}
			}
		}

		//Onwer (ok) , it needs to works with model, not array convertion
		foreach ($all as $table_name => $models) {
			foreach ($models as $key => $model) {
				if(isset($all_id['users'] )){
					foreach ($all_id['users'] as $users_id) {
						$tree_owner[$table_name][$users_id][$model->id] = $model->getPermissionOwner($users_id);
					}
				}
			}
		}

		//Descendant tree with IDs
		${$this->getTable().'_'.$this->id} = new \stdClass;
		for ($i = 1; $i <= 2; $i++) { //Loop 2 times to be sure to attach all IDs
			foreach ($all as $name => $models) {
				foreach ($models as $id => $model) {
					$model = $all[$name][$id];
					$pname = (string)$model->_parent[0];
					$pid = (int)$model->_parent[1];
					$id = (int)$id;
					if(empty($pname)){
						$pname = 'root';
						$pid = 0;
					}
					if(!isset(${$pname.'_'.$pid})){
						${$pname.'_'.$pid} = new \stdClass;
					}
					if(!isset(${$pname.'_'.$pid}->$name)){
						${$pname.'_'.$pid}->$name = new \stdClass;
					}
					if(isset(${$name.'_'.$id})){
						${$pname.'_'.$pid}->$name->$id = ${$name.'_'.$id};
					} else {
						${$pname.'_'.$pid}->$name->$id = false;
						${$name.'_'.$id} = new \stdClass;
					}
				}
			}
		}
		$root_0 = new \stdClass;
		$root_0->{$root->getTable()} = new \stdClass;
		$root_0->{$root->getTable()}->{$root->id} = ${$root->getTable().'_'.$root->id};

		$root_uid = array();
		//Build the tree per user
		if(isset($all_id['users'])){
			foreach ($all_id['users'] as $users_id) {
				$root_uid[(int)$users_id] = array( 0 => json_decode(json_encode($root_0)) ); //No Super applied (0) at the root level
			}
		}

		$tree_access_users = array();
		foreach ($root_uid as $users_id => $root) {

			//Super (ok) & for guesses deletes it if inaccessible
			$arr = $root_uid[$users_id];
			$arr_tp = $arr;
			$i = 1000; //Avoid infinite loop (1000 nested level, which should never happened)
			while(!empty($arr)){
				$arr_tp = array();
				foreach ($arr as $super => $list) {
					foreach ($list as $table_name => $models) {
						if( !isset($tree_access[$table_name][$users_id]) ){
							continue;
						}
						$super_perm = 0; //[R]
						$class = false;
						if(isset($list_models[$table_name])){
							$class = $list_models[$table_name];
						}
						if($super && $class){
							$super_perm = $class::getPermissionSheet()[1];
						}
						foreach ($models as $id => $model) {
							if(
								   !isset($tree_access[$table_name][$users_id][$id]['access'])
								|| !$tree_access[$table_name][$users_id][$id]['access']
							){
								continue;
							} else {
								$tree_access_users[$table_name][$users_id][$id] = true;
							}
							$super_perm_model = $super_perm;
							$super_tp = $super;
							//We only check at worspace level
							if($table_name == 'workspaces' && isset($work_super[$users_id][$id])){
								$super_tp = 0; //(workspace $id<=0) Insure nobody has super access to shared workspace
								if($id > 0){
									$super_tp = $work_super[$users_id][$id];
								}
							}
							if($super_tp != $super){
								$super_perm_model = 0;
								if($super_tp && $class){
									$super_perm_model = $class::getPermissionSheet()[1];
								}
							}
							$tree_super[$table_name][$users_id][$id] = $super_perm_model;
							if(!empty((array)$model)){
								if(!isset($arr_tp[$super_tp])){
									$arr_tp[$super_tp] = $model;
								} else {
									foreach ($model as $key => $value) {
										if(!isset($arr_tp[$super_tp]->$key)){
											$arr_tp[$super_tp]->$key = $value;
										} else {
											$arr_tp[$super_tp]->$key = (object) array_merge((array) $arr_tp[$super_tp]->$key, (array) $value);
										}
									}
								}
							}
						}
					}
				}
				$arr = $arr_tp;
				$i--;
				if($i<=0){
					$arr = array();
					break;
				}
			}

			//Role (ok)
			$roles = $all['roles'];
			$arr = $root_uid[$users_id];
			$arr_tp = $arr;
			$i = 1000; //Avoid infinite loop (1000 nested level, which should never happened)
			while(!empty($arr)){
				$arr_tp = array();
				foreach ($arr as $role => $list) {
					foreach ($list as $table_name => $models) {
						if( !isset($tree_access[$table_name][$users_id]) ){
							continue;
						}
						$role_perm = 0; //[R]
						$max_perm = 0; //[R]
						$class = false;
						$allow_role = false;
						if(isset($list_models[$table_name])){
							$class = $list_models[$table_name];
							$allow_role = $class::getRoleAllow()[1];
							if($role > 0){
								$max_perm = $class::getPermissionSheet()[1];
							}
						}
						if($max_perm > 0 && isset($roles[$role])){
							if(isset($roles[$role]->{'perm_'.$table_name})){ //Per model
								$role_perm = $roles[$role]->{'perm_'.$table_name};
							} else { //General
								$role_perm = $roles[$role]->perm_all;
							}
							//We check the limit of the permission
							if($role_perm > $max_perm){
								$role_perm = $max_perm;
							}
						}
						foreach ($models as $id => $model) {
							if(
								   !isset($tree_access[$table_name][$users_id][$id]['access'])
								|| !$tree_access[$table_name][$users_id][$id]['access']
							){
								continue;
							}
							$role_perm_elem = $role_perm;
							$role_tp = $role;
							if($allow_role && isset($tree_roles_id[$table_name][$users_id][$id])){
								$role_tp = $tree_roles_id[$table_name][$users_id][$id];
							} else {
								$tree_roles_id[$table_name][$users_id][$id] = $role_tp;
							}
							if($role_tp != $role){
								$max_perm_elem = 0; //[R]
								if($role_tp > 0 && $class){
									$max_perm_elem = $class::getPermissionSheet()[1];
								}
								if($max_perm_elem > 0 && isset($roles[$role_tp])){
									if(isset($roles[$role_tp]->{'perm_'.$table_name})){ //Per model
										$role_perm_elem = $roles[$role_tp]->{'perm_'.$table_name};
									} else { //General
										$role_perm_elem = $roles[$role_tp]->perm_all;
									}
									//We check the limit of the permission
									if($role_perm_elem > $max_perm_elem){
										$role_perm_elem = $max_perm_elem;
									}
								}
							}
							$tree_role[$table_name][$users_id][$id] = $role_perm_elem;
							if(!empty((array)$model)){
								if(!isset($arr_tp[$role_tp])){
									$arr_tp[$role_tp] = $model;
								} else {
									foreach ($model as $key => $value) {
										if(!isset($arr_tp[$role_tp]->$key)){
											$arr_tp[$role_tp]->$key = $value;
										} else {
											$arr_tp[$role_tp]->$key = (object) array_merge((array) $arr_tp[$role_tp]->$key, (array) $value);
										}
									}
								}
							}
						}
					}
				}
				$arr = $arr_tp;
				$i--;
				if($i<=0){
					$arr = array();
					break;
				}
			}
		}
//\libs\Watch::php($children, '$children', __FILE__, false, false, true);
		$all_perm = array();
		$tree_models = array();
		foreach ($children as $table_name => $models) {
			foreach ($children[$table_name] as $id => $temp) {
				$perm_new = '';
				$perm_old = (string) $children[$table_name][$id]->_perm;
				if(isset($all_id['users'])){
					//Set permission per user
					foreach ($all_id['users'] as $users_id) {
						//Check access first
						if( !isset($tree_access_users[$table_name][$users_id][$id]) ){
							continue;
						}
						$perm_owner = 0; //tree_owner
						if(isset($tree_owner[$table_name][$users_id][$id])){ $perm_owner = $tree_owner[$table_name][$users_id][$id]; }
						$perm_super = 0; //tree_super
						if(isset($tree_super[$table_name][$users_id][$id])){ $perm_super = $tree_super[$table_name][$users_id][$id]; }
						$perm_single = 0; //tree_single (priority on single over Role)
						$perm_role = 0; //tree_role
						if(isset($tree_single[$table_name][$users_id][$id])){
							$perm_single = $tree_single[$table_name][$users_id][$id];
						} else if(isset($tree_role[$table_name][$users_id][$id])){
							$perm_role = $tree_role[$table_name][$users_id][$id];
						}
						$role_id = 0; //tree_role
						if(isset($tree_roles_id[$table_name][$users_id][$id])){
							$role_id = $tree_roles_id[$table_name][$users_id][$id];
						}
						if(!isset($all['roles'][$role_id])){
							$role_id = 0; //tree_role
							$perm_role = 0; //If the role is not register we set to viewer
						}
						if(empty($perm_new)){
							$perm_new = new \stdClass;
						}
						$perm_new->$users_id = array(
							(int)max($perm_owner, $perm_super, $perm_single, $perm_role),
							(int)$role_id,
						);
					}
				}
				$perm_new = (string) json_encode($perm_new);
				if($perm_new != $perm_old){
					if(empty($perm_new)){ //Because a key cannot be empty
						$perm_new = '_';
					}
					$all_perm[$perm_new][$table_name][$id] = $id;

					$old = json_decode($perm_old, true);
					if(!is_array($old)){
						$old = array();
					}
					$new = json_decode($perm_new, true);
					if(!is_array($new)){
						$new = array();
					}
					$plus = array_keys(array_diff_key($new, $old));
					$less = array_keys(array_diff_key($old, $new));
					if(count($plus)>0){
						Models::plus($table_name, $id, $plus);
					}
					if(count($less)>0){
						Models::less($table_name, $id, $less);
					}
				}
			}
		}

		//\libs\Watch::php($all_perm, '$all_perm', __FILE__, false, false, true);

		$users_tables = array();
		$time = $this->freshTimestamp();
		foreach ($all_perm as $json => $list) {
			if($json=='_'){ //Underscore means empty string
				$json = '';
			}
			foreach ($list as $table_name => $ids) {
				if(isset($list_models[$table_name])){
					$class = $list_models[$table_name];
					if((is_bool($class::$has_perm) && $class::$has_perm) || in_array('_perm', $class::getColumns())){ //has_perm is a shortcut to limit some SQL calls
						$class::getQuery()->whereIn('id', $ids)->update(['updated_at' => $time, '_perm' => $json]);
						$users = json_decode($json);
						if(is_object($users)){
							foreach ($users as $users_id => $value) {
								$users_tables[$users_id][$table_name] = true;
							}
						}
					}
					$users_tables[$app->lincko->data['uid']][$table_name] = true;
				}
			}
		}
		
		Updates::informUsers($users_tables, $time);

		return $users_tables; //Give the list of what has been updated

	}

	//Scan the list and tell if the user has an access to it by filtering it (mainly used for Data.php)
	//The unaccesible one will be deleted in Data.php by hierarchy
	public static function filterPivotAccessList(array $list, $all=false){
		$suffix = static::getPivotUsersSuffix();
		$result = array();
		$table = (new static)->getTable();
		$attributes = array( 'table' => $table, );
		$pivot = new PivotUsers($attributes);
		if($pivot->tableExists($pivot->getTable())){
			$pivot = $pivot->whereIn($table.$suffix, $list)->withTrashed()->get();
			foreach ($pivot as $key => $value) {
				if($all || $value->access){
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
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array(), $default = array('access' => 1)){
		if(!static::$access_accept){
			foreach ($uid_list as $uid) {
				foreach ($list as $value) {
					if(!isset($result[$uid][$value])){
						$result[$uid][$value] = (array) $default;
					} else if(!$result[$uid][$value]['access']){
						unset($result[$uid][$value]);
					}
				}
			}
		}
		return $result;
	}

	//By default just return the list as it is
	public static function filterPivotAccessGetDefault(){
		$result = array();
		$list = static::filterPivotAccessListDefault(array('users'), array(0));
		if(isset($list[0]['users'])){
			$result = $list[0]['users'];
		}
		if(isset(static::getDependenciesVisible()['users'])){
			$visible = static::getDependenciesVisible()['users'][1];
			foreach ($result as $key => $value) {
				if(!in_array($key, $visible)){
					unset($result[$key]);
				}
			}
		}
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
				$data = $db->select( $sql , [$database] );
				foreach ($data as $value) {
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

	public function getDefaultValue($table){
		$connection = $this->getConnectionName();
			if(!isset(self::$schema_default[$connection])){
				if($this->tableExists($table)){
				self::$schema_table[$connection] = array();
				$database = Capsule::schema($connection)->getConnection()->getDatabaseName();
				$sql = 'select table_name, column_name, column_default from `information_schema`.`columns` where `table_schema` = ?;';
				$db = Capsule::connection($connection);
				$data = $db->select( $sql , [$database] );
				foreach ($data as $value) {
					if(!isset(self::$schema_default[$connection][$value->table_name])){
						self::$schema_default[$connection][$value->table_name] = array();
					}
					self::$schema_default[$connection][$value->table_name][$value->column_name] = $value->column_default;
				}
			}
		}
		if(isset(self::$schema_default[$connection][$table])){
			return self::$schema_default[$connection][$table];
		}
		return false;
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

	public function scopegetLinked($query, $with=false, $trash=false){
		//Why did I use getTree with parents to get ID? It seems useless since getItems() should return the same list
		//If no issue over the time, just delete the code below
		$arr = array();
		if($with){
			$parentType = $this::getParentList();
			if(count($parentType)>0){
				if(is_string($parentType)){
					if(method_exists(get_called_class(), $parentType)){
						$arr[$parentType] = $parentType;
					}
				} else if(is_array($parentType)){
					foreach ($parentType as $type) {
						if(method_exists(get_called_class(), $type)){
							$arr[$type] = $type;
						}
					}
				}
			}
			foreach ($arr as $type) {
				//$query = $query->with($type);
			}
		}
		$list = Data::getTrees($arr, 2);
		if($trash || $this->with_trash){
			$query = $query->withTrashed();
		}
		return $query->getItems($list);
	}

	public function scopegetKids($query, $list=array()){
		$app = self::getApp();
		$this->var['condition'] = false; //this insire that there is at least one condition to avoid to list all table
		$table = $this->getTable();
		$query = $query
		->where(function ($query) use ($list, $table) {
			foreach ($list as $table_name => $list_id) {
				if(is_array($this::$parent_list) && in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
					$this->var['condition'] = true;
					$query = $query
					->orWhere(function ($query) use ($table_name, $list_id, $table) {
						$query
						->where($table.'.parent_type', $table_name)
						->whereIn($table.'.parent_id', $list_id);
					});
				} else if($table_name == $this::$parent_list && $this::getClass($table_name)){
					$this->var['condition'] = true;
					$query = $query
					->orWhere(function ($query) use ($table_name, $list_id, $table) {
						$query
						->whereIn($table.'.parent_id', $list_id);
					});
				}
			}
		});
		if(!$this->var['condition']){
			return false;
		}
		if(self::$with_trash_global){
			$query = $query->withTrashed();
		}
		$result = $query->get();
		return $result;
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
		if(!isset(self::$columns[$model->getTable()])){
			self::$columns[$model->getTable()] = array();
			$schema = $model->getConnection()->getSchemaBuilder();
			self::$columns[$model->getTable()] = $schema->getColumnListing($model->getTable());
		}
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
		if(!is_null($this->parent_item)){
			return $this->_parent;
		}
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

	public static function getParentListSoft(){
		return static::$parent_list_soft;
	}

	public function getParent(){
		if(!is_null($this->parent_item)){
			return $this->parent_item;
		}
		$this->setParentAttributes();
		if(is_string($this->parent_type) && is_integer($this->_parent[1]) && $class = $this::getClass($this->parent_type)){
			if($this->parent_item = $class::find($this->_parent[1])){
				return $this->parent_item;
			}
		}
		$this->parent_item = false;
		return $this->parent_item;
	}

	public function getParentAccess(){
		$app = self::getApp();
		$parent = $this->getParent();
		if(!$parent && (empty($this->parent_type) || ($this->_parent[0]=='workspaces' && $this->_parent[1]==0)) ){ //We allow shared workspace
			return true; //Accept any model attached to root
		}
		if($parent && $parent->checkAccess(false)){
			return $parent;
		}
		$msg = $app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
		\libs\Watch::php($this, $msg, __FILE__, true);
		$json = new Json($msg, true, 406);
		$json->render(406);
		return false;
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
	public function setForceSchema($all=false){
		$timestamp = time();
		if($all){
			Users::getQuery()->update(['check_schema' => $timestamp]);
			return true;
		}
		if(isset($this->_perm)){
			$list = json_decode($this->_perm);
			$users = array();
			if(!empty($list)){
				foreach ($list as $users_id => $perm) {
					$users[$users_id] = $users_id;
				}
			}
			if(!empty($users)){
				Users::getQuery()->whereIn('id', $users)->update(['check_schema' => $timestamp]);
				return true;
			}
		}
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
		$dependencies = array();
		foreach ($classes as $table => $class) {
			$model = new $class;
			$data = null;
			$dependencies_visible = $model::getDependenciesVisible();
			//NOTE: Give a priority for Deptasks() overs tasks()
			if(count($dependencies_visible)>0){
				foreach ($dependencies_visible as $dependency => $dependencies_fields) {
					if(count($dependencies_fields)>0 && isset($list_id[$table]) && (method_exists($class, 'Dep'.$dependency) || method_exists($class, $dependency)) ) {
						if(method_exists($class, 'Dep'.$dependency)){
							$dependency = 'Dep'.$dependency;
						}
						if(is_null($data)){
							$data = $model::whereIn($model::getTableStatic().'.id', $list_id[$table]);
						}
						$data = $data->with($dependency);
					}
				}
				if(!is_null($data)){
					$data = $data->where(function ($query) use ($class, $list_id, $table, $dependencies_visible) {
						foreach ($dependencies_visible as $dependency => $dependencies_fields) {
							if(isset($list_id[$table]) && (method_exists($class, 'Dep'.$dependency) || method_exists($class, $dependency)) ) {
								if(method_exists($class, 'Dep'.$dependency)){
									$dependency = 'Dep'.$dependency;
								}
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
								$depatt = $dependency;
								if(isset($dep->{'Dep'.$dependency})){
									$depatt = 'Dep'.$dependency;
								}
								if(isset($dep->$depatt)){
									foreach ($dep->$depatt as $key => $value) {
										if(isset($value->pivot->access) && isset($dependencies_visible[$dependency])){
											foreach ($dependencies_fields[1] as $field) {
												if(isset($value->pivot->$field) || is_null($value->pivot->$field)){
													$field_value = $dep->formatAttributes($field, $value->pivot->$field);
													$dependencies[$table][$dep->id]['_'.$dependency][$value->id][$field] = $field_value;
												}
											}
											if(!$value->pivot->access){
												$dependencies[$table][$dep->id]['_'.$dependency][$value->id]['access'] = false;
											}
										}
									}
								}
							}
						}
					} catch (Exception $obj_exception) {
						//Do nothing to continue
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
			if(isset($list_id[$table]) && count($model::$archive)>0){
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
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->by = (integer)$value->createdBy();
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->not = (boolean)$not;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->cod = (integer)$value->code;
							$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->att = (string)$value->attribute;
							if(!empty($value->parameters)){
								$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->par = json_decode($value->parameters);
							}
							if($history_detail){
								if(strlen($value->old)<500){
									$history->{$value->parent_type}->{$value->parent_id}->history->$created_at->{$value->id}->old = $value->old;
								}
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
		if(count($this::$archive)>0 && isset($this->id)){
			$records = History::whereParentType($this->getTable())->whereParentId($this->id)->get();
			foreach ($records as $key => $value) {
				$created_at = (new \DateTime($value->created_at))->getTimestamp();
				$not = false;
				if(strpos($value->noticed_by, ';'.$app->lincko->data['uid'].';') === false){
					$not = true; //True if need notification
				}
				if(!isset($history->$created_at)){ $history->$created_at = new \stdClass; }
				if(!isset($history->$created_at->{$value->id})){ $history->$created_at->{$value->id} = new \stdClass; }
				$history->$created_at->{$value->id}->att = (string)$value->attribute;
				$history->$created_at->{$value->id}->by = (integer)$value->createdBy();
				$history->$created_at->{$value->id}->not = (boolean)$not;
				$history->$created_at->{$value->id}->cod = (integer)$value->code;
				if(!empty($value->parameters)){
					$parameters = $history->$created_at->{$value->id}->par = json_decode($value->parameters);
				}
				if($history_detail){
					if(strlen($value->old)<500){
						$history->$created_at->{$value->id}->old = $value->old;
					}
				}
			}
		}
		$history = (object) array_merge((array) $history, (array) $this->getHistoryCreation());
		return $history;
	}

	public function getHistoryCreation($history_detail=false, array $parameters = array(), $items=false){
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
		if(array_key_exists('created_at', $this::$archive)){
			$code = $this::$archive['created_at'];
		}
		$history->$created_at = new \stdClass;
		$history->$created_at->{'0'} = new \stdClass;
		//Because some models doesn't have creacted_by column (like the workspaces)
		$created_by = null;
		if(isset($this->created_by)){
			$created_by = $this->created_by;
		}
		$history->$created_at->{'0'}->att = 'created_at';
		$history->$created_at->{'0'}->by = (integer) $created_by;
		$history->$created_at->{'0'}->not = (boolean) $not;
		$history->$created_at->{'0'}->cod = (integer) $code;
		if(!empty($parameters)){
			$history->$created_at->{'0'}->par = (object) $parameters;
		}
		if($history_detail){
			$history->$created_at->{'0'}->old = null;
		}
		return $history;
	}

	/*
		$parameters should be a array of [key1:val1, key2:val2, etc]
	*/
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		$app = self::getApp();
		$namespace = (new \ReflectionClass($this))->getNamespaceName();
		if(count($this::$archive)==0 || $this->getTable()=='history' || $namespace!='bundles\lincko\api\models\data'){ //We exclude history itself to avoid looping
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
		foreach ($this::$archive as $key => $value) {
			$titles->$value = $app->trans->getBRUT('data', 1, $value);
		}
		$titles->{'0'} = $app->trans->getBRUT('data', 1, $this->name_code);
		return $titles;
	}

	protected function getArchiveCode($column, $value){
		if(is_bool($value)){
			$value = (int) $value;
		}
		$value = (string) $value;
		if(array_key_exists($column.'_'.$value, $this::$archive)){
			return $this::$archive[$column.'_'.$value];
		} else if(array_key_exists($column, $this::$archive)){
			return $this::$archive[$column];
		}
		return $this::$archive['_']; //Neutral comment
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
				$this::where('id', $this->id)->getQuery()->update(['viewed_by' => $viewed_by]); //toto => with about 200+ viewed, it crashes (1317 Query execution was interrupted)
				$this->touchUpdateAt();
				usleep(5000); //5ms (trying to avoid crash (1317 Query execution was interrupted) when over +200 viewed to updated)
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

	public function getPerm(){
		return $this->_perm;
	}

	public function getPermissionMax($users_id = false){
		$app = self::getApp();
		$this->checkUser();
		if(!$users_id){
			$users_id = $app->lincko->data['uid'];
		}
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
		if(isset(self::$permission_users[$users_id][$this->getTable()][$this->id])){
			return self::$permission_users[$users_id][$this->getTable()][$this->id];
		}
		$check_single = true; //We only check single of the model itself, not its parents
		$suffix = $this->getTable();
		$role = false;
		$perm = 0; //Only allow reading

		$model = $this;
		if(!$this->id){ //If new item, we check role of parent only
			if($parent = $model->getParent()){
				$model = $parent;
				$check_single = false;
			}
		}

		$role = false;
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
					}
				}
			} else {
				if($check_single && $model::$allow_single && $pivot[2]){ //Priority on single over Role
					$perm = $pivot[2];
				} else if($model::$allow_role && $pivot[1]){
					if($role = Roles::find($pivot[1])){
						if(isset($role->{'perm_'.$suffix})){ //Per model
							$perm = $role->{'perm_'.$suffix};
						} else { //General
							$perm = $role->perm_all;
						}
					}
				}
			}
		}

		self::$permission_users[$users_id][$this->getTable()][$this->id] = $perm;

		return $perm;
	}

	//It checks if the user has access to it
	public function checkAccess($show_msg=true){
		$app = self::getApp();
		$this->checkUser();
		$uid = $app->lincko->data['uid'];
		if(!is_bool($this->accessibility)){
			$this->accessibility = (bool) false; //By default, for security reason, we do not allow the access
			//If the element exists, we check if we can find it by getLinked
			if(isset($this->id)){
				if(isset($this->_perm)){
					$perm = json_decode($this->_perm);
					if(!empty($perm) && isset($perm->$uid)){
						$this->accessibility = (bool) true;
					}
				}
				else if($this->getLinked(true)->whereId($this->id)->take(1)->count() > 0){
					$this->accessibility = (bool) true;
				}
			}
			//If it's a new element, we check if we can access it's parent (we don't have to care about element linked to Workspaces|NULL since ID will be current workspace, so $parent will exists)
			else {
				$parent = $this->getParent();
				if($parent){
					if(isset($parent->_perm)){
						$perm = json_decode($parent->_perm);
						if(!empty($perm) && isset($perm->$uid)){
							$this->accessibility = (bool) true;
						}
					} else {
						$this->accessibility = $parent->checkAccess($show_msg);
					}
				}
				//Root directory
				else if(empty($parent_type) && empty($parent_id)){
					$this->accessibility = (bool) true;
				}
			}
			
		}
		if($this->accessibility){
			return true;
		} else if($show_msg){
			$suffix = $this->getTable();
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			$suffix = $this->getTable();
			\libs\Watch::php($suffix." :\n".parent::toJson(), $msg, __FILE__, true);
			if(!self::$debugMode){
				$json = new Json($msg, true, 406);
				$json->render(406);
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
			$json->render(406);
		}
		return false;
	}

	public function forceSaving($force=true){
		$this->force_save = (boolean) $force;
	}

	public function getDirty(){
		$dirty = parent::getDirty();
		$columns = self::getColumns();
		foreach ($dirty as $key => $value) {
			if(!in_array($key, $columns)){
				unset($dirty[$key]);
			}
		}
		return $dirty;
	}

	public function getUsersTable($users_tables=array()){
		$app = self::getApp();
		if(!isset($app->lincko->data['uid'])){
			return $users_tables;
		}
		$users_tables[$app->lincko->data['uid']][$this->getTable()] = true;
		if(isset($this->_perm) && !empty($this->_perm) ){
			$temp = json_decode($this->_perm);
			if(!empty($temp)){
				foreach ($temp as $key => $value) {
					$users_tables[$key][$this->getTable()] = true;
				}
			}
		} else if($this->getTable()=='users'){
			$temp = Users::filterPivotAccessList(array($app->lincko->data['uid']), true);
			if($temp){
				foreach ($temp as $key => $value) {
					$users_tables[$key]['users'] = true;
				}
			}
		} else if($this->getTable()=='settings'){
			$users_tables[$app->lincko->data['uid']][$this->getTable()] = true;
		} else {
			$temp = $this->users;
			if($temp){
				try {
					foreach ($temp as $key => $value) {
						$users_tables[$value->id][$this->getTable()] = true;
					}
				} catch (Exception $obj_exception) {
					$users_tables[$temp->id][$this->getTable()] = true;
				}
			}
		}
		return $users_tables;
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
			$this->change_permission = true;
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
			$json->render(406);
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
		if(isset($dirty['parent_type']) || isset($dirty['parent_id'])){
			if(isset($this->id)){
				$this->change_schema = true;
			}
			$this->change_permission = true;
		}

		$return = false;
		try {
			$return = parent::save($options);
			if($new){
				$this->new_model = true;
				if($this->getTable()=='users'){
					$app->lincko->data['uid'] = $this->id;
				}
			}
			//Reapply the fields that were not part of table columns
			foreach ($attributes as $key => $value) {
				$this->attributes[$key] = $value;
			}
			$this->pivots_save();
		} catch(\Exception $e){
			\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, true);
		}

		//Make sure we set here users_tables before setPerm to inform users that migth be removed, Users::informUsers is also called inside setPerm for new Users

		$users_tables = $this->getUsersTable();

		//$parent
		if($parent){
			$users_tables = $parent->touchUpdateAt($users_tables, false);
		}

		if($this->change_schema){
			$this->setForceSchema();
		}
		if($this->change_permission){
			$users_tables_updated = $this->setPerm();
			foreach ($users_tables_updated as $key => $value) {
				foreach ($value as $table_name => $value) {
					unset($users_tables[$key][$table_name]); //No need to informed twice users already informed, it reduces SQL calls
				}
			}
		}

		Updates::informUsers($users_tables);

		//We do not record any setup for new model, but only change for existing model
		if(!$new){
			foreach($dirty as $key => $value) {
				//We exclude "created_at" if not we will always record it on history table, that might fulfill it too quickly
				if($key != 'created_at' && $key != 'updated_at'){
					$old = null;
					$new = null;
					if(isset($original[$key])){ $old = $original[$key]; }
					if(isset($dirty[$key])){ $new = $dirty[$key]; }
					//We excluse default modification
					if(isset($this::$archive[$key]) || isset($this::$archive[$key.'_'.$new])){
						$this->setHistory($key, $new, $old);
					}
				}
			}
		}

		//toto
		//We record Tree for faster query
		$users_list = array();
		$users_list[$app->lincko->data['uid']] = $app->lincko->data['uid'];
		foreach ($users_tables as $users_id => $value) {
			$users_list[$users_id] = $users_id;
		}
		//Tree::TreeUpdateOrCreate($this, $users_list, true);
		if($this->change_schema || $this->change_permission){
			$class = $this::getClass();
			$table = $this->getTable();
			$suffix = $class::getPivotUsersSuffix();
			$pivot = new PivotUsers(array($table));
			try {
				if($pivot->tableExists($pivot->getTable()) && $pivot->withTrashed()->where($table.$suffix, $this->id)->get(['users_id', $table.$suffix, 'access'])){
					foreach ($pivot as $model) {
						$users_id = $model->users_id;
						$fields = array(
							'access' => $model->access,
						);
						//Tree::TreeUpdateOrCreate($this, array($users_id), false, $fields);
						//\libs\Watch::php( $item->id, $table, __FILE__, false, false, true);
					}
				}
			} catch (\Exception $e) {
				\libs\Watch::php( $e->getFile()."\n".$e->getLine()."\n".$e->getMessage(), $table, __FILE__, false, false, true);
			}
		}

		return $return;
		
	}

	//Note: this is unsafe because it skip every step of checking
	public function brutSave(array $options = array()){
		return Model::save($options);
	}

	//This will update updated_at, even if the user doesn't have write permission
	public function touchUpdateAt($users_tables=array(), $inform=true){
		$app = self::getApp();
		if (!$this->timestamps || !isset($this->updated_at) || !isset($this->id)) {
			return false;
		}

		$time = $this->freshTimestamp();
		$result = $this::where('id', $this->id)->getQuery()->update(['updated_at' => $time]);

		$users_tables = $this->getUsersTable($users_tables);

		if($inform){ //We do by default
			Updates::informUsers($users_tables);
		}

		return $users_tables;
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
			}
			parent::withTrashed()->where('id', $this->id)->delete();
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
			}
			parent::withTrashed()->where('id', $this->id)->restore();
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
	public static function enableTrashGlobal($trash=false){
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

		if(isset($this->_perm)){
			$temp->_perm = json_decode($this->_perm);
		}
		
		$temp = json_encode($temp, $options);
		return $temp;
	}

	public function toVisible(){
		$this->checkAccess(); //To avoid too many mysql connection, we can set the protected attribute "accessibility" to true if getLinked is used using getItems()
		$app = self::getApp();
		$this->setParentAttributes();
		$model = new \stdClass;
		if(!empty($this->visible)){
			foreach ($this->visible as $key) {
				$prefix = $this->getPrefix($key); //It's used for word search on Front side (+/- prefix)
				$model->{$prefix.$key} = $this->$key;
			}
		} else {
			$model = json_decode(parent::toJson());
			foreach ($model as $key => $value) {
				$prefix = $this->getPrefix($key);
				$model->{$prefix.$key} = $value;
			}
		}

		//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
		$model->new = false;
		if(isset($this->viewed_by)){
			if(strpos($this->viewed_by, ';'.$app->lincko->data['uid'].';') === false){
				$model->new = true;
			}
		}

		foreach(self::$class_timestamp as $value) {
			if(isset($model->$value)){ $model->$value = (int) (new \DateTime($model->$value))->getTimestamp(); }
		}
		foreach($this->model_timestamp as $value) {
			if(isset($model->$value)){ $model->$value = (int) (new \DateTime($model->$value))->getTimestamp(); }
		}
		//Convert number to integer. NULL will stay NULL thanks to isset()
		foreach(self::$class_integer as $value) {
			if(isset($model->$value)){ $model->$value = (int) $model->$value; }
		}
		foreach($this->model_integer as $value) {
			if(isset($model->$value)){ $model->$value = (int) $model->$value; }
		}
		//Convert boolean.
		foreach(self::$class_boolean as $value) {
			if(isset($model->$value)){ $model->$value = (boolean) $model->$value; }
		}
		foreach($this->model_boolean as $value) {
			if(isset($model->$value)){ $model->$value = (boolean) $model->$value; }
		}

		if(isset($this->_perm)){
			$model->_perm = json_decode($this->_perm);
			if(empty($this->_perm)){
				$model->_perm = new \stdClass;
			}
		}

		return $model;
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
			$prefix = '+';
		} else if(in_array($value, $this->search_fields)){
			$prefix = '-';
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

	protected function setPivotExtra($type, $column, $value){
		$pivot_array = array(
			$column => $value,
		);
		return $pivot_array;
	}

	public function pivots_save(array $parameters = array()){
		$app = self::getApp();
		$namespace = (new \ReflectionClass($this))->getNamespaceName();
		if($namespace!='bundles\lincko\api\models\data'){ //We exclude users_x_roles_x itself to avoid looping
			return true;
		}
		$success = true;
		$touch = false;
		$users_tables = array();
		//checkAccess and CheckPermissionAllow are previously used in save()
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
						//Do not convert into string a NULL value (ifnot it will return a 0 timestamp or empty string instead of NULL value)
						if(!is_null($value)){
							$value = (string) $value;
						}
						if(!$success){ break; }
						$pivot = false;
						$pivot_array = $this->setPivotExtra($type, $column, $value);
						if(method_exists(get_called_class(), $type)){ //Check if the pivot call exists
							$pivot_relation = $this->$type();
							if($pivot_relation !== false && method_exists($pivot_relation,'updateExistingPivot') && method_exists($pivot_relation,'attach')){
								if($pivot = $pivot_relation->find($type_id)){ //Check if the pivot exists
									//We delete created_at since it already exists
									unset($pivot_array['created_at']);
									//Update an existing pivot
									if(is_null($pivot->pivot->$column)){ //Do not convert a NULL into a string
										$value_old = $pivot->pivot->$column;
									} else {
										$value_old = (string) $pivot->pivot->$column;
									}
									if($value_old != $value){
										if($pivot_relation->updateExistingPivot($type_id, $pivot_array)){
											if($column=='access'){
												$this->change_permission = true;
												if(!(bool)$value){
													$this->change_schema = true;
												}
											}
											$touch = true;
											$class = $this::getClass($type);
											if($model = $class::withTrashed()->find($type_id)){
												$users_tables = $model->touchUpdateAt($users_tables, false);
											}
											if($history_save){
												$parameters = array();
												if($type=='users'){
													$parameters['cun'] = Users::find($type_id)->username; //Storing this value helps to avoid many SQL calls later
												}
												//We excluse default modification
												if(isset($this::$archive['pivot_'.$column]) || isset($this::$archive['pivot_'.$column.'_'.$value])){
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
									if($column!='access' && !isset($this->pivots_var->$type->$type_id->access)){
										//By default, if we affect a new pivot, we always authorized access if it's not specified (for instance a user assigned to a task will automaticaly have access to it)
										$pivot_array['access'] = true;
									}
									$pivot_relation->attach($type_id, $pivot_array); //attach() return nothing
									$this->pivot_extra_array = false;
									if($column=='access'){
										$this->change_permission = true;
										if(!(bool)$value){
											$this->change_schema = true;
										}
									}
									$touch = true;
									$class = $this::getClass($type);
									if($class && $model = $class::withTrashed()->find($type_id)){
										$users_tables = $model->touchUpdateAt($users_tables, false);
									}
									if($history_save){
										$parameters = array();
										if($type=='users'){
											$parameters['cun'] = Users::find($type_id)->username; //Storing this value helps to avoid many SQL calls later
										}
										//We excluse default modification
										if(isset($this::$archive['pivot_'.$column]) || isset($this::$archive['pivot_'.$column.'_'.$value])){
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
		Updates::informUsers($users_tables);
		if($touch){
			$this->touchUpdateAt();
		}
		return $success;
	}

	//By preference, keep it protected
	public function getUserPivotValue($column, $users_id=false){
	//protected function getUserPivotValue($column, $users_id=false){
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
		if(isset($this->_perm)){
			$perm = json_decode($this->_perm);
			if(isset($perm->$users_id)){
				$roles_id = null;
				$single = null;
				if($this::$allow_role){
					$roles_id = $perm->{$users_id}[1];
				}
				if($this::$allow_single){
					$single = $perm->{$users_id}[0];
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
					$this->touchUpdateAt();
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
				$this->touchUpdateAt();
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
