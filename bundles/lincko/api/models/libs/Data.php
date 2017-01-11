<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;
use Carbon\Carbon;

use \bundles\lincko\api\models\Notif;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\Updates;
use \bundles\lincko\api\models\libs\Models;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Spaces;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Roles;
use \bundles\lincko\api\models\data\Messages;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected static $models = NULL;
	protected $lastvisit = false; //Format 'Y-m-d H:i:s'
	protected $lastvisit_timestamp = 0;
	protected $lastvisit_object = false;
	protected $partial = NULL;

	protected $full_schema = false;
	protected $item_detail = true;
	protected $history_detail = false;
	protected $action = NULL;
	protected static $delete_temp_id = true;

	protected $limit_json = false; //(integer) Indicate the number of items downloaded at a time to avoid browser memory crash

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$data = $this->data = json_decode($app->request->getBody());
		//Get lastvisit field if it's part of form field of upload
		if(!$this->data && isset($_FILES) && !empty($_FILES)){
			$this->data = new \stdClass;
			$this->data->data = new \stdClass;
			$post = $app->request->post();
			if(!empty($post)){
				$post = (object) $post;
				if(isset($post->lastvisit)){
					$this->data->data->lastvisit = (int) $post->lastvisit;
				}
			}
		}
		if(isset($data->data) && isset($data->data->limit_json) && $data->data->limit_json){
			$this->limit_json = $data->data->limit_json;
		}
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		return true;
	}

	public function reinit(){
		$this->lastvisit = false;
		$this->partial = NULL;
		$this->item_detail = true;
		$this->history_detail = false;
	}

	protected static function setDeleteTempId($bool=true){
		self::$delete_temp_id = (bool) $bool;
	}

	public function dataUpdateConfirmation($msg, $status=200, $show=false, $lastvisit=0, $delete_temp_id=true, $schema=null){
		$app = $this->app;
		self::setDeleteTempId($delete_temp_id); //We keep temp_id usually at creation (set to false)
		if($app->lincko->data['lastvisit_enabled'] && $lastvisit && $this->setLastVisit()){
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 9), //You got the latest updates.
					'lastvisit' => $app->lincko->data['lastvisit'], //Make sure that setLastvisit is time()-1
					'partial' => $this->getLatest(),
					'schema' => $schema,
				),
				$msg
			);
		} else {
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 9), //You got the latest updates.
					'partial' => $this->getLatest($lastvisit),
					'schema' => $schema,
				),
				$msg
			);
		}
		$app->render($status, array('msg' => $msg, 'show' => $show,));
		return true;
	}

	protected function setLastVisit($timestamp='false'){ //toto => Why a string?
		$this->lastvisit_timestamp = 0;
		$this->lastvisit_object = false;
		$this->lastvisit = false;
		if(is_integer($timestamp)){
			if($timestamp>0){
				$this->lastvisit_timestamp = $timestamp;
				$this->lastvisit_object = new \DateTime('@'.$timestamp);
				return $this->lastvisit = $this->lastvisit_object->format('Y-m-d H:i:s');
			}
		} else if(isset($this->data->data->lastvisit)){
			if(is_integer($this->data->data->lastvisit) && $this->data->data->lastvisit>0){
				$timestamp = $this->data->data->lastvisit - 1;
				$this->lastvisit_timestamp = $timestamp;
				$this->lastvisit_object = new \DateTime('@'.$timestamp);
				return $this->lastvisit = $this->lastvisit_object->format('Y-m-d H:i:s');
			}
		}
		return $this->lastvisit = false;
	}

	public function getTimestamp(){
		if($this->lastvisit_timestamp){
			return $this->lastvisit_timestamp;
		} else if($this->lastvisit){
			return $this->lastvisit_timestamp = (new \DateTime($this->lastvisit))->getTimestamp();
		} else if($this->setLastVisit()){
			return $this->lastvisit_timestamp;
		} else {
			return 0;
		}
	}

	//Rebuild all _perm (can be a very long operation)
	public static function setForcePerm(){
		$app = \Slim\Slim::getInstance();
		$time_record = $app->lincko->time_record;
		$app->lincko->time_record = true;
		//The permission purge
		if(function_exists('proc_nice')){proc_nice(30);}
		set_time_limit(24*3600); //Set to 1 day workload at the most
		//Reinitialize all permissions
		$models = Data::getModels();
		$count = array();
		\time_checkpoint('start permission');
		//First reset all root objects (help to save time becaue if reset dependencies at the same time)
		foreach ($models as $table => $class) {
			$count[$table] = 0;
			$all = $class::withTrashed()->get();
			foreach ($all as $model) {
				$model->setParentAttributes();
				if(!$model->parent_id){ //To run this, make sure that all _perm are empty first
					$model->setPerm();
					$count[$table]++;
				}
			}
			\time_checkpoint($table.' => '.$count[$table]);
		}
		\time_checkpoint('end');
		\libs\Watch::php( $count, '$count', __FILE__, __LINE__, false, false, true);
		if(function_exists('proc_nice')){proc_nice(0);}
		$app->lincko->time_record = $time_record;
	}

	protected function setPartial($force_partial=false){
		$app = $this->app;
		if($force_partial){
			$this->partial = $force_partial;
		} else if(isset($this->data->data->partial)){
			if(is_object($this->data->data->partial)){
				$this->partial = $this->data->data->partial;
			}
		}
		//This will help missing and history to not scan the whole database if we do not provide a partial parameter
		$uid = $app->lincko->data['uid'];
		if(!isset($this->partial)){ $this->partial = new \stdClass; }
		if(!isset($this->partial->$uid)){ $this->partial->$uid = new \stdClass; }
		return $this->partial;
	}

	public static function getModels(){
		if(is_null(self::$models)){
			$app = \Slim\Slim::getInstance();
			$sql = 'SHOW TABLES;';
			$db = Capsule::connection($app->lincko->data['database_data']);
			$data = $db->select( $db->raw($sql) );
			$classes = array();
			foreach ($data as $key => $value) {
				$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC(array_values((array) $value)[0]);
				if(class_exists($tp)){
					$table_name = $tp::getTableStatic();
					$classes[$table_name] = $tp;
				}
			}
			self::$models = $classes;
		}
		return self::$models;
	}

	//IMPORTANT => be carefull how to use, if $list_tp is ['chats', 'files', 'comments'], it will not return a chats that belongs to a projects because 'projects' is not included. Make sure that parents are included to avoid strange results
	public static function getTrees($list_tp=false, $field=false){
		if($list_tp===false || !is_array($list_tp)){
			$list_models = self::getModels();
		} else {
			$tp = self::getModels();
			$list_models = array();
			foreach ($list_tp as $table_name) {
				if(isset($tp[$table_name])){
					$list_models[$table_name] = $tp[$table_name];
				}
			}
		}
		$tree_scan = array();
		$tree_desc = new \stdClass;
		$tree_id = array();
		$not_trashed = array();
		$result = new \stdClass;

		if(!empty($list_models)){
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
				unset($parentList[$table]); //Avoid recursivity
				foreach($parentList as $name => $parent) {
					if( !isset($tree_scan[$table]) ){
						$tree_scan[$table] = array();
					}
					if(array_key_exists($name, $list_models)){
						$tree_scan[$table][] = $name;
					}

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

			if(is_numeric($field) && $field===0){
				return $tree_scan;
			} else if(is_numeric($field) && $field===1){
				return $tree_desc;
			}

			/*
			//toto => don't use models, it generates deadlocks
			$models = array();
			if($temp = Models::getItems(array_flip($list_models), true)){
				foreach ($temp as $value) {
					$models[$value->type] = array_filter( explode(';', $value->list), 'strlen' );
				}
			}
			*/

			// Get all ID with parent dependencies
			$loop = true;
			$tree_tp = $tree_scan;
			while(count($tree_tp)>0 && $loop){
				$loop = false;
				foreach ($tree_tp as $key => $value) {
					if(count($value)<=0){
						$loop = true;
						//Get all ID including whereIn if some parents
						if(isset($list_models[$key])){
							$class = $list_models[$key];
							$list = array();
							foreach ($tree_scan[$key] as $value_bis) {
								if(isset($tree_id[$value_bis])){
									$list[$value_bis] = $tree_id[$value_bis];
									if(isset($not_trashed[$value_bis])){
										foreach ($not_trashed[$value_bis] as $key_trashed => $value_trashed) {
											if(!$value_trashed && $value_bis=='projects'){ //It only concerns projects deletion
												unset($list[$value_bis][$key_trashed]); //Helps to not download children of any delete item, but keep deleted items for activity feed purpose
											}
										}
									}
								}
							}
							$tree_id[$key] = array();
							$not_trashed[$key] = array();
							$result_bis = false;
							$nested = true;
							while($nested){ //$nested is used for element that are linked to each others
								$nested = false;
								$break = false;
								/*
								//toto => don't use models, it generates deadlocks
								if(isset($models[$key]) && count($models[$key])>0){ //toto => Models has an issue when plus a single ID and the row doesn't exists yet, it will ignore previous IDs
									$result_bis = $class::withTrashed()->whereIn('id', $models[$key])->get(); //toto => It seems that it's slower that the jointure, need to be confirmed with heavy database
									$break = true; //We force to exit because the list of IDs already contain all IDs
								} else {
									$class::enableTrashGlobal(true);
									$result_bis = $class::getItems($list, true);
									$class::enableTrashGlobal(false);
								}
								*/
								$class::enableTrashGlobal(true);
								$result_bis = $class::getItems($list, true);
								$class::enableTrashGlobal(false);
								if(isset($result->$key)){
									$result->$key = $result->$key->merge($result_bis);
								} else {
									$result->$key = $result_bis;
								}
								$list = array();
								$list[$key] = array();
								//toto => this loop use 200-400ms
								foreach ($result_bis as $value_bis) {
									if(!isset($tree_id[$key][$value_bis->id])){ //Insure to not record twice the same ID to not enter inside an infinite loop
										$list[$key][$value_bis->id] = $value_bis->id;
									}
									$tree_id[$key][$value_bis->id] = $value_bis->id;
									$not_trashed[$key][$value_bis->id] = true;
									if(isset($value_bis->deleted_at) && $value_bis->deleted_at!=null){
										$not_trashed[$key][$value_bis->id] = false; //It's a deleted item
									}
								}
								unset($result_bis);
								if($break){
									break;
								}
								if(!empty($list[$key]) && $class::isParent($key)){
									$nested = true;
								}
							}
						}
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

		if(is_numeric($field) && $field>=0 && $field<=3){
			if($field===0){
				return $tree_scan;
			} else if($field===1){
				return $tree_desc;
			} else if($field===2){
				return $tree_id;
			} else if($field===3){
				return $result;
			} 
		} else {
			return array($tree_scan, $tree_desc, $tree_id, $result);
		}
		
	}

	public static function getAccesses($tree_id){
		//\libs\Watch::php($tree_id, '$tree_id', __FILE__, __LINE__, false, false, true);
		$app = \Slim\Slim::getInstance();
		$tree_access = array();
		if(isset($tree_id['users'])){
			$list_models = self::getModels();
			foreach ($tree_id as $table => $list) {
				if(isset($list_models[$table])){
					$class = $list_models[$table];
					$tree_access[$table] = $class::filterPivotAccessList($list, true); //Getting real Pivot value
				}
			}
			//\libs\Watch::php($tree_access, '$tree_access', __FILE__, __LINE__, false, false, true);
			$users = array();
			foreach ($tree_access as $type => $type_list) {
				foreach ($type_list as $users_id => $value) {
					$users[$users_id] = $users_id;
				}
			}
			foreach ($tree_id as $table => $list) {
				if(isset($list_models[$table])){
					$class = $list_models[$table];
					$tree_access[$table] = $class::filterPivotAccessListDefault($list, $users, $tree_access[$table]); //Applying default pivot value if need
				}
			}
			//By default, give access to all users inside shared workspace
			if(!isset($tree_access['workspaces'])){ $tree_access['workspaces'] = array(); }
			foreach ($users as $users_id) {
				if(!isset($tree_access['workspaces'][$users_id])){ $tree_access['workspaces'][$users_id] = array(); }
				$tree_access['workspaces'][$users_id][0] = array(
					'access' => 1, //Give access to all user
					'super' => 0, //Prohibit super permission to shared workspace
				);
			}
		}
		return $tree_access;
	}

	public function getLatest($timestamp=false){
		$this->action = 'latest';
		$this->reinit();
		$this->setLastVisit($timestamp);
		$this->full_schema = false;
		if($this->lastvisit_timestamp<=0){
			$this->full_schema = true;
		}
		$this->partial = NULL;
		return $this->getList();
	}

	public function getSchema(){
		$this->action = 'schema';
		$this->reinit();
		$this->lastvisit = false;
		$this->full_schema = true; //We force to get the whole tree
		$this->partial = NULL;
		$this->item_detail = false;
		return $this->getList();
	}

	public function getMissing($force_partial=false){
		$this->action = 'missing';
		$this->reinit();
		$this->lastvisit = false;
		$this->setPartial($force_partial);
		return $this->getList();
	}

	public function getHistory(){
		$this->action = 'history';
		$this->reinit();
		$this->lastvisit = false;
		$this->history_detail = true;
		$this->setPartial();
		return $this->getList();
	}

	protected function getList(){
		$app = $this->app;
		//$db = Capsule::connection($app->lincko->data['database_data']);
		//$db->enableQueryLog();
		$uid = $app->lincko->data['uid'];
		$workid = $app->lincko->data['workspace_id'];
		$list_models = self::getModels();

		//---OK---
		$updates = array();
		if($this->action == 'latest'){
			if(!is_null($this->partial) && !isset($this->partial->$uid) && empty((array)$this->partial->$uid)){
				return null;
			} else if(isset($this->partial) && isset($this->partial->$uid)){
				foreach ($this->partial->$uid as $table => $value) {
					$updates[$table] = true;
				}
			}
			if($arr = Updates::find($uid)){
				foreach ($list_models as $table => $value) {
					if(isset($arr->{$table})){
						$time = new \DateTime($arr->{$table});
						if($time >= $this->lastvisit_object){
							$updates[$table] = true;
						}	
					}
				}
			}
			if(empty($updates)){
				$result_bis = new \stdClass;
				return null;
			}
		}

		//---OK---
		//MEDIUM CPU hunger
		$tp = $this::getTrees();
		$tree_scan = $tp[0];
		$tree_desc = $tp[1];
		$tree_id = $tp[2];
		$result = $tp[3];
		unset($tp);

		//---OK---
		$users = array();
		foreach ($result as $models) {
			foreach ($models as $model) {
				//toto => MEDIUM CPU hunger
				$users = array_merge($users, $model->setContacts());
			}
		}

		//---OK---
		$visible = array();
		if(isset($tree_id['users'])){
			$visible = $tree_id['users'];
			//If inside a workspace, automatically add users as visible
			if($app->lincko->data['workspace_id']>0 && !empty($app->lincko->data['workspace'])){
				$workspace = Workspaces::getWorkspace();
				$workspace_users = $workspace->users;
				foreach ($workspace_users as $value) {
					$visible[$value->id] = $value->id;
				}
			}
			$users = array_merge($tree_id['users'], $users);
		}

		//---OK---
		foreach ($users as $users_id) {
			if(isset($tree_id['users'])){
				$tree_id['users'][$users_id] = $users_id;
			}
		}
	
		//---OK---
		$tree_access = array();
		foreach ($result as $type => $models) {
			if($type!='users'){ //We can exclude users, we should not include the contact list, and users oject doesn't have _perm column too
				foreach ($models as $model) {
					if(isset($model->_perm)){
						if($perm = json_decode($model->_perm)){
							foreach ($perm as $users_id => $value) {
								$tree_access[$type][$users_id][$model->id] = true;
							}
						}
					}
				}
			}
		}

		//---OK---
		//Get the list of all users that have access
		foreach ($tree_access as $type => $type_list) {
			foreach ($type_list as $users_id => $value) {
				if(isset($tree_id['users'])){
					$tree_id['users'][$users_id] = $users_id;
				}
			}
		}
		
		//---OK---
		$result->users = Users::getUsersContacts($tree_id['users'], $visible);

		//---OK---
		$users = array();
		$users['users'] = $tree_id['users'];
		$users_access = $this::getAccesses($users); //Check if at least other users have access (since we narrow to users only, the calulation is light)
		foreach ($users_access as $type => $type_list) {
			foreach ($type_list as $users_id => $models) {
				foreach ($models as $id => $value) {
					$tree_access[$type][$users_id][$id] = true;
				}
			}
		}

		//---OK---
		//Get files of profile pcitures
		if($profiles = Files::getProfilePics($tree_id['users'])->get()){
			if(isset($result->files)){
				foreach ($profiles as $model) {
					$result->files[] = $model;
				}
			} else {
				$result->files = $profiles;
			}
		}
		
		//---OK---
		$result_bis = new \stdClass;
		$result_bis->$uid = new \stdClass;
		if($this->action=='latest' && $this->lastvisit_timestamp>0){ //latest
			//Limit result with timestamp of lastvisit
			foreach ($result as $key => $models) {
				foreach ($models as $key_bis => $model) {
					if( $model->updated_at >= $this->lastvisit_object ){
						if(!isset($result_bis->$uid->$key)){
							$result_bis->$uid->$key = new \stdClass;
						}
						$result_bis->$uid->$key->{$model->id} = $model;
					}
				}
			}
		} else if(is_object($this->partial)){ //missing + history
			//Only get a part of the data
			foreach ($result as $key => $models) {
				if(isset($this->partial->$uid->$key)){
					$result_bis->$uid->$key = new \stdClass;
					foreach ($models as $key_bis => $model) {
						if(isset($this->partial->$uid->$key->{$model->id})){
							$result_bis->$uid->$key->{$model->id} = $model;
						}
					}
				}
			}
		} else { // schema + latest(0)
			//Get all
			foreach ($result as $key => $models) {
				$result_bis->$uid->$key = new \stdClass;
				foreach ($models as $key_bis => $model) {
					$result_bis->$uid->$key->{$model->id} = $model;
				}
			}
		}
		unset($result);

		//---OK---
		$list_id = array();
		$list_id_extra = array();
		$list_id_no_extra = array();
		$result_no_extra = array();
		foreach ($result_bis->$uid as $table_name => $models) {
			$list_id[$table_name] = array();
			foreach ($models as $id => $model) {
				$model->accessibility = true;
				$list_id[$table_name][$id] = $id;
				unset($temp);
				if($temp = $model->extraDecode()){
					//Double check that extra field is up to date
					if(isset($model->updated_at) && isset($temp->updated_at) && $temp->updated_at != $model->updated_at->getTimestamp()){
						$temp = false;
					} else {
						$list_id_extra[$table_name][$id] = $id;
					}
				}
				if(!$temp){
					$result_no_extra[$table_name][$id] = $model;
					$list_id_no_extra[$table_name][$id] = $id;
					$temp = new \stdClass;
					if($this->item_detail){
						$temp = $model->toVisible();
						//Get only creation history to avoid mysql overload
						$temp->history = $model->getHistoryCreation(false, array(), $result_bis->$uid);
						if(empty($temp->history)){
							unset($temp->history);
						}
					} else {
						//need delete information for schema
						$temp->deleted_at = $model->deleted_at;
					}

					$temp->_parent = $model->setParentAttributes();
				}
				$temp->new = false;
				if(isset($temp->viewed_by)){
					if(strpos($temp->viewed_by, ';'.$uid.';')===false){
						$temp->new = true;
					}
					unset($temp->viewed_by);
				}
				unset($temp->id); //Delete ID property since it becomes the key of the table
				$result_bis->$uid->$table_name->$id = $temp;
			}
		}

		$root_0 = new \stdClass;
		//Descendant tree with IDs
		$root_0->workspaces = new \stdClass;
		${'workspaces_'.$workid} = $root_0->workspaces->$workid = new \stdClass; //Must initialize for share workspace because the database doesn't exists
		for ($i = 1; $i <= 2; $i++) { //Loop 2 times to be sure to attach all IDs
			foreach ($result_bis->$uid as $name => $models) {
				foreach ($models as $id => $model) {
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

		if(!$this->item_detail){
			foreach ($result_bis->$uid as $table_name => $models) {
				foreach ($models as $id => $model) {
					if(isset($model->deleted_at) && !is_null($model->deleted_at)){
						$result_bis->$uid->$table_name->$id = false;
					} else {
						$result_bis->$uid->$table_name->$id = true;
					}
				}
			}

		} else {

			//---OK---
			//Get dependency (all ManyToMany that have other fields than access)
			$dependencies = ModelLincko::getDependencies($list_id_no_extra, $list_models);
			foreach ($dependencies as $table_name => $deps_ids) {
				foreach ($deps_ids as $id => $attributes) {
					foreach ($attributes as $attribute => $value) {
						if($attribute!='_users'){
							$temp = (array) $value;
							foreach ($value as $item_id => $att) {
								$arr = (array) $att;
								if(isset($arr['access']) && !$arr['access']){
									unset($temp[$item_id]);
									continue; //Skip recording non accessed dependencies
								}
							}
						} else {
							$temp = $value;
						}
						$result_bis->$uid->$table_name->$id->$attribute = (object) $temp;
					}
				}
			}
			
			//---OK---
			//For _users to fulfill with default for all users (it's slightly different than $dependencies which only get existing links, it does not default)
			//toto => need to follow closely over time to make sure that replacing "$result_bis->$uid" by "result_no_extra" does not affect anything (_users)
			foreach ($result_no_extra as $table_name => $models) {
				if(!isset($dependencies[$table_name])){
					continue;
				}
				$class = $list_models[$table_name];
				$default = false;
				$default_list = array();
				if(isset($class::getDependenciesVisible()['users'])){
					$default = $class::filterPivotAccessGetDefault();
					foreach ($tree_id['users'] as $key => $value) {
						$default_list[$key] = $default;
					}
				}
				foreach ($models as $id => $model) {
					$deps = array();
					if(isset($dependencies[$table_name][$id]['_users'])){
						$deps = (array) $dependencies[$table_name][$id]['_users'];
					}
					$default_full = array();
					foreach ($default_list as $users_id => $value) {
						if(isset($tree_access[$table_name][$users_id][$id])){
							if(isset($deps[$users_id])){
								$arr = (array) $deps[$users_id];
								if(isset($arr['access']) && !$arr['access']){
									if(isset($result_bis->$uid->$table_name->$id->_perm)){
										unset($result_bis->$uid->$table_name->$id->_perm->$users_id);
									}
									continue; //Skip recording non accessed users
								}
								$default_full[$users_id] = $arr;
							} else {
								$default_full[$users_id] = $default_list[$users_id];
							}
						}
					}
					$result_bis->$uid->$table_name->$id->_users = (object) $default_full;
				}
			}

			//---OK---
			if($this->history_detail){
				$histories = Users::getHistories($list_id, $list_models, true);
			} else { //For element where extra was not calculated, we do force the calculation to cache it for any other calls, even if we don't need to display it immediatly to the final user
				$histories = Users::getHistories($list_id_no_extra, $list_models, false);
			}
			foreach ($histories as $table_name => $models) {
				foreach ($models as $id => $temp) {
					if(isset($result_bis->$uid->$table_name->$id)){
						if(isset($result_bis->$uid->$table_name->$id->history)){
							$result_bis->$uid->$table_name->$id->history = (object) array_merge((array) $result_bis->$uid->$table_name->$id->history, (array) $temp->history);
						} else {
							$result_bis->$uid->$table_name->$id->history = $temp->history;
						}
					}
				}
			}

			//Save extra
			//\time_checkpoint('before extra');
			foreach ($result_no_extra as $table_name => $models) {
				foreach ($models as $id => $model) {
					$model->extraEncode($result_bis->$uid->$table_name->$id);
				}
			}
			//\time_checkpoint('after extra');

			//For history, we only keep the items that are filled in
			if($this->history_detail){
				foreach ($result_bis->$uid as $table_name => $models) {
					if(!isset($histories->$table_name)){
						unset($result_bis->$uid->$table_name);
					} else {
						foreach ($result_bis->$uid->$table_name as $id => $temp) {
							if(!isset($histories->$table_name->$id)){
								unset($result_bis->$uid->$table_name->$id);
							}
						}
					}
					if(empty((array) $result_bis->$uid)){
						unset($result_bis->$uid);
					}
				}
			}
			
		}

		//It gather the fields we need to workspace only
		if(!is_null($this->partial) && isset($this->partial->$uid)){
			if(isset($this->partial->$uid->{'_history_title'})){
				$result_bis->$uid->{'_history_title'} = new \stdClass;
			}
		}

		if(
			   (isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god'])
			|| (isset($app->lincko->api['x__history']) && $app->lincko->api['x__history'])
		){

			//$result_bis->$uid->_history = new \stdClass;
			foreach ($result_bis->$uid as $table_name => $models) {
				if(strpos($table_name, '_')!==0){ //Skip everything which is not a model list
					foreach ($models as $id => $model) {
						if(!is_object($model)){
							continue;
						}
						$previous_timestamp = 0;
						//$model->_not = false;
						if(isset($model->history)){
							foreach ($model->history as $timestamp => $hists) {
								foreach ($hists as $hist_id => $hist) {
									$hist->it = $table_name.'-'.$id; //item
									$hist->rt = false; //root (chats or projects)
									$hist->not = false;
									//$result_bis->$uid->_history->$hist_id = $hist;
									/*
									if(isset($hist->notid) && $timestamp >= $previous_timestamp){
										$timestamp = $previous_timestamp;
										$not = (bool)(strpos($hist->notid, ';'.$uid.';')===false);
										$model->_not = $not;
										$hist->not = $not;
									}
									*/
									unset($hist->notid);
								}
							}
							//unset($model->history);
						}
					}
				}
			}

			if(
				$this->full_schema
				||
				(
					!is_null($this->partial)
					&& isset($this->partial->$uid)
					&& isset($this->partial->$uid->{'_history_title'})
				)
			)
			{
				if(!isset($result_bis->$uid->{'_history_title'})){
					$result_bis->$uid->{'_history_title'} = new \stdClass;
				}
				foreach($list_models as $key => $value) {
					$model = new $value;
					$table_name = $model->getTable();
					if($this->item_detail){
						$result_bis->$uid->{'_history_title'}->$table_name = $model->getHistoryTitles();
					} else {
						$result_bis->$uid->{'_history_title'}->$table_name = new \stdClass;
					}
				}
			}
		}

		//Delete the part that the application doesn't have access
		if(!isset($app->lincko->api['x_i_am_god']) || !$app->lincko->api['x_i_am_god']){
			foreach ($result_bis->$uid as $table_name => $models) {
				if(!isset($app->lincko->api['x_'.$table_name]) || !$app->lincko->api['x_'.$table_name]){
					unset($result_bis->$uid->$table_name);
				}
			}
			//Delete history if no access
			if(!isset($app->lincko->api['x__history']) || !$app->lincko->api['x__history']){
				unset($result_bis->$uid->_history);
			}
		}

		//Delete the items that are hidden by spaces
		if($block = Spaces::blockItems()){
			foreach ($block as $model) {
				$table_name = $model['parent_type'];
				$id = $model['parent_id'];
				if(isset($result_bis->$uid->$table_name) && isset($result_bis->$uid->$table_name->$id)){
					unset($result_bis->$uid->$table_name->$id);
				}
			}
		}

		//toto => on front it generates more complication than expecting, so don't force to delete temp_id at this moment
		self::setDeleteTempId(false);

		//Delete temp_id if the user is not concerned
		if($this->item_detail){
			foreach ($result_bis->$uid as $table_name => $models) {
				foreach ($result_bis->$uid->$table_name as $id => $temp) {
					if(self::$delete_temp_id){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					} else if(isset($temp->created_by) && $temp->created_by!=$uid){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					} else if(isset($temp->temp_id) && $temp->temp_id==''){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					}
				}
			}
		}

		/*
		//toto => this is a temp solution (for iOS) the time we can refactor communciation process with less data
		//At least need 300 items, cannot be lower (avoid to geenrate too much calls)
		if($this->limit_json>300 && $this->item_detail){
			$i = 0;
			$skip = false;
			$result_limit = new \stdClass;
			$result_limit->$uid = new \stdClass;
			foreach ($result_bis->$uid as $table_name => $models) {
				$result_limit->$uid->$table_name = new \stdClass;
				$must = false;
				if(strpos($table_name, '_')===0){
					$must = true;
				}
				if(!$skip){
					foreach ($result_bis->$uid->$table_name as $id => $temp) {
						if(!$skip || $must){
							$result_limit->$uid->$table_name->$id = $result_bis->$uid->$table_name->$id;
							$i++;
						}
						if(!$must && $i >= $this->limit_json){
							$result_limit->uncomplete = true;
							break;
						}
					}
				}
			}
			return $result_limit;
		}
		*/

		//\libs\Watch::php($result_bis, '$result_bis', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( $db->getQueryLog() ,'QueryLog', __FILE__, __LINE__, false, false, true);
		return $result_bis;

	}

	
	public static function unLockAll(){
		if(function_exists('proc_nice')){proc_nice(20);}
		$app = \Slim\Slim::getInstance();
		$time = (new Users)->freshTimestamp();
		$users = array();

		//Unlock Tasks
		$tasks_query = Tasks::withTrashed()->whereNotNull('locked_by')->where('locked_at', '<', $time);
		$tasks_perm = $tasks_query->get(array('_perm'));
		if(count($tasks_perm)>0){
			$tasks_update = $tasks_query->getQuery()->update(['updated_at' => $time, 'extra' => null, 'locked_by' => null, 'locked_at' => null]);
			usleep(100000); //100ms
			foreach ($tasks_perm as $items) {
				if(!is_null($items->_perm) && $perm = json_decode($items->_perm)){
					foreach ($perm as $uid => $list) {
						if(!isset($users[$uid])){
							$users[$uid] = array();
						}
						$users[$uid]['tasks'] = true;
					}
				}
			}
		}

		//Unlock Notes
		$notes_query = Notes::withTrashed()->whereNotNull('locked_by')->where('locked_at', '<', $time);
		$notes_perm = $notes_query->get(array('_perm'));
		if(count($notes_perm)>0){
			$notes_update = $notes_query->getQuery()->update(['updated_at' => $time, 'extra' => null, 'locked_by' => null, 'locked_at' => null]);
			usleep(100000); //100ms
			foreach ($notes_perm as $items) {
				if(!is_null($items->_perm) && $perm = json_decode($items->_perm)){
					foreach ($perm as $uid => $list) {
						if(!isset($users[$uid])){
							$users[$uid] = array();
						}
						$users[$uid]['notes'] = true;
					}
				}
			}
		}
		
		Updates::informUsers($users);
		if(function_exists('proc_nice')){proc_nice(0);}
		return true;
	}
	

	//$period (string) => 'daily', 'weekly'
	public static function getResume(){ //Default is 24H (daily is 86,400s), weekly is 604,800s.
		$app = \Slim\Slim::getInstance();
		//Capsule::connection($app->lincko->data['database_data'])->enableQueryLog();
		if(function_exists('proc_nice')){proc_nice(20);}
		$db = Capsule::connection($app->lincko->data['database_data']);

		sleep(1); //Just insure we are working on the current day

		$timeend = Carbon::today();
		$now = Carbon::now();
		$current_hour = $now->hour;
		$day = $now->dayOfWeek;
		$timeend->hour = $current_hour; //Current time starting at the beginning of current hour

		$comments_update = false;
		$period_all = array('daily', 'weekly');
		$weekday = date('w');

		$users_resume = array();
		$temp = Users::Where('resume', $current_hour)->get(array('id', 'weekly'));
		//$temp = Users::Where('id', '>', 0)->get(array('id', 'weekly')); //toto (show for test)
		foreach ($temp as $user) {
			$users_resume[$user->id] = $user->weekly;
			//$users_resume[$user->id] = $weekday; //toto (show for test)
		}
		//\libs\Watch::php($users_resume, '$users_resume: '.$current_hour, __FILE__, __LINE__, false, false, true);

		$notif_team_daily = array();
		$notif_team_weekly = array();
		$notif_individual_daily = array();
		$notif_individual_weekly = array();

		foreach ($period_all as $period) {

			//Default is 'daily'
			$timeback = 86400;
			$base = 100;
			if($period=='weekly'){
				$timeback = 604800;
				$base = 700;
			}
			$timenext = $timeend->copy();
			$timenext->second = $timenext->second - $timeback; //Give one more day/week to indicate a close target
			$timestart = $timeend->copy();
			$timestart->second = $timestart->second - $timeback; //When do we start to compare, fro one day/week before
			$timeprevious = $timeend->copy();
			$timeprevious->second = $timeprevious->second - 2*$timeback; //This help to compare to previous day/week
			$timelimit = $timestart->copy();
			if($period=='weekly'){
				$timelimit->second = $timelimit->second - $timeback; //This help to display at least one message of no activity for weekly report
			}

			
			$projects = Projects::Where('personal_private', null)
				->where('updated_at', '>=', $timelimit)
				->where(function ($query) use ($current_hour) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
					$query
					->whereHas('users', function ($query) use ($current_hour) {
						$query
						->where('resume', $current_hour) //toto (hide for test)
						->where('access', 1);
					})
					->orWhere('resume', $current_hour) //toto (hide for test)
					;
				})
				->get(array('id', 'updated_at', '_perm', 'resume', 'weekly'));

			foreach ($projects as $project) {
				$users_most = array();
				$users = new \stdClass;
				$users_perm = new \stdClass;
				if($temp = json_decode($project->_perm)){
					foreach ($temp as $users_id => $value) {
						$users_perm->$users_id = array(0, 0); //Only allow reading
						if(isset($users_resume[$users_id])){
							if($period=='daily' || ($period=='weekly' && $users_resume[$users_id]==$weekday) ){
								$users->$users_id = true;
							}
						}
						
					}
				}

				$msg = false;
				$msg_users = false;
				if($period=='weekly'){
					$msg = 700; //Just this string will help to display "no activity" record
				}
				if($project->updated_at >= $timestart){

					usleep(1000);
					unset($result);
					unset($result_users);
					unset($data);
					unset($data_users);
					$result = array();
					//Tasks
					$result[$base] = 0;			//total tasks (not deleted)
					$result[$base+1] = array();	//Nbr Done in day
					$result[$base+2] = array();	//Total Done since the beginnning
					$result[$base+3] = array(); //Total remain at the end (= total - done)
					$result[$base+4] = array(); //Nbr new task in day
					$result[$base+5] = array(); //total remain overdue
					$result[$base+6] = 0;		//% of Done compare to remaining at the begining of teh day
					$result[$base+7] = 0;		//I don't understand that one Done/Remain
					$result[$base+8] = 0;		//% of Done compare to total tasks
					$result[$base+9] = 0;		//Who did the most Done in that day
					//Notes
					$result[$base+11] = array();
					//Files
					$result[$base+21] = array();

					$result_users = new \stdClass;
					foreach ($users as $users_id => $value) {
						$result_users->{$users_id} = array();
						$result_users->{$users_id}[$base] = 0; //Activity at 1 if any
						$result_users->{$users_id}[$base+1] = array();	//Nbr Done in day
						$result_users->{$users_id}[$base+5] = array();	//total remain overdue
						$result_users->{$users_id}[$base+30] = array();	//Nbr tasks due tomorrow
						$result_users->{$users_id}[$base+31] = array();	//Nbr Done in previous day/week
						$result_users->{$users_id}[$base+32] = 0;	//Nbr Done more than previous day/week
					}

					//Tasks
					$tasks_list = false;
					$total = 0;
					$remain_start = 0;
					$done = 0;
					$done_total = 0;
					$activity = false; //This represents real activity (task completed, new file, etc.), not statistics calculated
					$tasks = Tasks::
						Where('parent_id', $project->id)
						->whereHas("tasksup", function($query) {
							$query->withTrashed(); //this exclude all subtasks
						}, '<', 1)
						->get(array('id', 'created_at', 'approved_at', 'approved_by', 'approved', 'start', 'duration'));

					foreach ($tasks as $task) {
						if($tasks_list){
							$tasks_list .= ','.$task->id;
						} else {
							$tasks_list = $task->id;
						}
					}
					$tasks_in_charge = array();
					if($tasks_list){
						$sql = 'SELECT `users_id`, `tasks_id` FROM `users_x_tasks` WHERE `tasks_id` IN ('.$tasks_list.') AND `access` = 1 AND `in_charge` = 1;';
						if($temp = $db->select( $db->raw($sql))){
							foreach ($temp as $key => $value) {
								if(isset($result_users->{$value->users_id})){
									if(!isset($tasks_in_charge[$value->tasks_id])){ $tasks_in_charge[$value->tasks_id] = array(); }
									$tasks_in_charge[$value->tasks_id][$value->users_id] = true;
								}
							}
						}
					}
					foreach ($tasks as $task) {
						if($task->approved_at >= $timeprevious && $task->approved_at < $timestart){
							//[per user] sum total number of tasks completed in previous day
							if(isset($result_users->{$task->approved_by})){
								array_push($result_users->{$task->approved_by}[$base+31], $task->id);
								$result_users->{$task->approved_by}[$base] = 1;
							}
						}
						$total++;
						$result[$base]++;
						//sum total number of tasks completed in that day
						if($task->approved_at >= $timestart && $task->approved_at < $timeend){
							$activity = true;
							$done_total++;
							$done++;
							$remain_start++;
							if(!isset($users_most[$task->approved_by])){ $users_most[$task->approved_by] = 0; }
							$users_most[$task->approved_by]++;
							array_push($result[$base+1], $task->id);
							//[per user] sum total number of tasks completed in that day
							if(isset($result_users->{$task->approved_by})){
								array_push($result_users->{$task->approved_by}[$base+1], $task->id);
								$result_users->{$task->approved_by}[$base] = 1;
							}
						} else if($task->approved_at!==null && $task->approved_at < $timestart){ //sum of remaining task when the day started
							$done_total++;
						} else {
							$remain_start++;
						}
						//sum the total number of tasks completed in the project since it started
						if($task->approved){
							array_push($result[$base+2], $task->id);
						} else { //sum total number of tasks open (not completed) in the project.
							array_push($result[$base+3], $task->id);
							//[per user] Nbr tasks due tomorrow/this week
							if(isset($tasks_in_charge[$task->id]) && $task->overdue($timenext) > 0){
								foreach ($tasks_in_charge[$task->id] as $uid => $value) {
									array_push($result_users->{$uid}[$base+30], $task->id);
									$result_users->{$uid}[$base] = 1;
								}
							}
						}
						//sum the total number of new tasks created that day
						if($task->created_at >= $timestart && $task->created_at < $timeend){
							$activity = true;
							array_push($result[$base+4], $task->id);
						}
						//calculate the total number of overdue tasks
						if($task->overdue($timeend) > 0){
							array_push($result[$base+5], $task->id);
							//[per user] calculate the total number of overdue tasks
							if(isset($tasks_in_charge[$task->id])){
								foreach ($tasks_in_charge[$task->id] as $uid => $value) {
									array_push($result_users->{$uid}[$base+5], $task->id);
									$result_users->{$uid}[$base] = 1;
								}
							}
						}
					}
					unset($tasks);

					foreach ($result_users as $uid => $result_uid) {
						$result_users->{$uid}[$base+32] = max(0, count($result_uid[$base+1]) - count($result_uid[$base+31]) ); //Insure the mini will be 0 because we don't say less than (more than is for motiviation purpose)
					}

					//calculate % of completed tasks in the project with open tasks remaining that day
					if($remain_start > 0){
						$temp = ceil( 100 * $done / $remain_start );
						$temp = min(100, $temp);
						$result[$base+6] = max(0, $temp);
						if($temp>0){
							$activity = true;
						}
					}

					/*
					//calculate % of all completed tasks in the project with open tasks remaining.
					//Note: I don't understand the meaning
					if(($remain_start-$done) > 0){
						$temp = ceil( 100 * $done / ($remain_start-$done) );
						$temp = min(100, $temp);
						$result[$base+7] = max(0, $temp);
					}
					*/

					//calculate % of all completed tasks in the project.
					if($total > 0){
						$temp = ceil( 100 * $done_total / $total );
						$temp = min(100, $temp);
						$result[$base+9] = max(0, $temp);
					}

					//users_id of the one who completed the most tasks
					if(count($users_most)>0){
						$maxs = array_keys($users_most, max($users_most));
						if(isset($maxs[1]) && $maxs[0] == $maxs[1]){
							$result[$base+8] = 0;
						} else if(isset($maxs[0]) && $maxs[0] > 0){
							$result[$base+8] = $maxs[0];
						}
					}

					//Notes
					$sql = 'SELECT `id` FROM `notes` WHERE `parent_id` = :projects_id AND `created_at` >= :timestart AND `created_at` < :timeend;';
					$temp = $db->select( $db->raw($sql), array(
						'projects_id' => $project->id,
						'timestart' => $timestart,
						'timeend' => $timeend,
					));
					foreach ($temp as $key => $value) {
						array_push($result[$base+11], $value->id);
					}

					//Files
					$sql = 'SELECT `id` FROM `files` WHERE `parent_type` = \'projects\' AND `parent_id` = :projects_id AND `created_at` >= :timestart AND `created_at` < :timeend;';
					$temp = $db->select( $db->raw($sql), array(
						'projects_id' => $project->id,
						'timestart' => $timestart,
						'timeend' => $timeend,
					));
					foreach ($temp as $key => $value) {
						array_push($result[$base+21], $value->id);
					}

					if($activity){
						//Resume for team
						foreach ($result as $key => $value) {
							//Every value on front that are not recorded will be considerate as 0 or empty array
							if($value==0 || count($value)==0){
								if($key != $base){ //expect for base (100, 700) which is used to recognized a record
									continue;
								}
							}
							if(!isset($data)){ $data = new \stdClass; }
							if(!isset($data->{'0'})){ $data->{'0'} = array(); }
							if(is_numeric($value)){
								$data->{'0'}[$key] = (int) $value;
							} else {
								$data->{'0'}[$key] = $value;
							}
						}
						if(isset($data) && is_object($data)){
							$comments_update = true;
							$msg = json_encode($data);
							//\libs\Watch::php($data, '[team] $project '.$project->id, __FILE__, __LINE__, false, false, true);
						}
						
						//Resume for individual
						foreach ($result_users as $uid => $result_uid) {
							foreach ($result_uid as $key => $value) {
								//Every value on front that are not recorded will be considerate as 0 or empty array
								if($value==0 || count($value)==0){
									continue;
								}
								if(!isset($data_users)){ $data_users = new \stdClass; }
								if(!isset($data_users->{$uid})){ $data_users->{$uid} = array(); }
								if(is_numeric($value)){
									$data_users->{$uid}[$key] = (int) $value;
								} else {
									$data_users->{$uid}[$key] = $value;
								}
							}
						}
						if(isset($data_users) && is_object($data_users)){
							$comments_update = true;
							$msg_users = json_encode($data_users);
							//\libs\Watch::php($data_users, '[individual] $project '.$project->id, __FILE__, __LINE__, false, false, true);
						}
					}

				}

				//For team
				if($project->resume!=$current_hour || ($period=='weekly' && $project->weekly!=$weekday) ){
					$msg = false;
				} else if(!empty($msg)){
				//} if(!empty($msg)){ //toto (show for test)
					$comment = new Comments;
					$comment->created_by = 0;
					$comment->updated_by = 0;
					$comment->parent_type = 'projects';
					$comment->parent_id = $project->id;
					$comment->comment = $msg;
					$comment->_perm = json_encode($users_perm);
					$comment->saveRobot();
					//\libs\Watch::php(json_decode($msg), $period.': [team] $project '.$project->id, __FILE__, __LINE__, false, false, true);

					//Team Daily
					//Check out the daily team progress! Your Project Activity summaries have arrived. Go team!
					if($period=='daily'){
						foreach ($users_perm as $uid => $value) {
							$notif_team_daily[''.$uid] = $uid;
						}
					}

					//Team Weekly
					//Your weekly progress update is here! See how the team did last week and what's coming this week.
					if($period=='weekly'){
						foreach ($users_perm as $uid => $value) {
							$notif_team_weekly[''.$uid] = $uid;
						}
					}
				}

				//For Individual
				//if(!empty($msg_users) || ($msg_users==='' && $period=='weekly' && $project->weekly==$weekday) ){ //[buggy]
				if(!empty($msg_users)){
					$comment = new Comments;
					$comment->created_by = 0;
					$comment->updated_by = 0;
					$comment->parent_type = 'projects';
					$comment->parent_id = $project->id;
					$comment->comment = $msg_users;
					$comment->_perm = json_encode($users_perm);
					$comment->saveRobot();
					//\libs\Watch::php(json_decode($msg_users), $period.':[individual] $project '.$project->id, __FILE__, __LINE__, false, false, true);

					//Individual Daily
					//Want to see what you did today and what's coming tomorrow. Your daily update from the LinckoBot has arrived.
					if($period=='daily'){
						foreach ($users_perm as $uid => $value) {
							$notif_individual_daily[''.$uid] = $uid;
						}
					}

					//Individual Weekly
					//What a week! Check out what you did last week and what next week has in store. But don't forget to enjoy the weekend.
					if($period=='weekly'){
						foreach ($users_perm as $uid => $value) {
							$notif_individual_weekly[''.$uid] = $uid;
						}
					}
				}

			}

		}

		//Send notification according to the languages
		//To merge array we need to make sure we are using string as keys
		$notif_users = array_merge($notif_team_daily, $notif_team_weekly, $notif_individual_daily, $notif_individual_weekly);
		$list = Users::withTrashed()->whereIn('id', $notif_users)->get(array('id', 'language', 'username_sha1'));
		$lang_team_daily = array();
		$lang_team_weekly = array();
		$lang_individual_daily = array();
		$lang_individual_weekly = array();
		$notified_users = array(); //Help to send only one message at the same moment
		foreach ($list as $model) {
			//Individual Weekly
			if(!isset($lang_individual_weekly[$model->language])){ $lang_individual_weekly[$model->language] = array(); }
			if(isset($notif_team_daily[''.$model->id]) && !isset($notified_user[$model->id])){
				$notified_user[$model->id] = true;
				$notif_individual_weekly[$model->language][$model->id] = $model->getSha();
			}
			//Individual Daily
			if(!isset($lang_individual_daily[$model->language])){ $lang_individual_daily[$model->language] = array(); }
			if(isset($notif_team_daily[''.$model->id]) && !isset($notified_user[$model->id])){
				$notified_user[$model->id] = true;
				$notif_individual_daily[$model->language][$model->id] = $model->getSha();
			}
			//Team Weekly
			if(!isset($lang_team_weekly[$model->language])){ $lang_team_weekly[$model->language] = array(); }
			if(isset($notif_team_weekly[''.$model->id]) && !isset($notified_user[$model->id])){
				$notified_user[$model->id] = true;
				$lang_team_weekly[$model->language][$model->id] = $model->getSha();
			}
			//Team Daily
			if(!isset($lang_team_daily[$model->language])){ $lang_team_daily[$model->language] = array(); }
			if(isset($notif_team_daily[''.$model->id]) && !isset($notified_user[$model->id])){
				$notified_user[$model->id] = true;
				$lang_team_daily[$model->language][$model->id] = $model->getSha();
			}
		}

		$notif = new Notif;
		$title = 'Lincko';
		
		foreach ($lang_individual_weekly as $language => $alias) {
			//What a week! Check out what you did last week and what next week has in store. But don't forget to enjoy the weekend!
			$content = $app->trans->getBRUT('api', 19, 4, array(), $language);
			$notif->push($title, $content, false, $alias);
		}
		foreach ($lang_individual_daily as $language => $alias) {
			//Want to see what you did today and what's coming tomorrow. Your daily update from the LinckoBot has arrived.
			$content = $app->trans->getBRUT('api', 19, 3, array(), $language);
			$notif->push($title, $content, false, $alias);
		}
		foreach ($lang_team_weekly as $language => $alias) {
			//Your weekly progress update is here! See how the team did last week and what's coming this week.
			$content = $app->trans->getBRUT('api', 19, 2, array(), $language);
			//$notif->push($title, $content, false, $alias); //It generates to many notifications for the user
		}
		foreach ($lang_team_daily as $language => $alias) {
			//Check out the daily team progress! Your Project Activity summaries have arrived. Go team!
			$content = $app->trans->getBRUT('api', 19, 1, array(), $language);
			//$notif->push($title, $content, false, $alias); //It generates to many notifications for the user
		}


		//Force all user to update their schema
		if($comments_update){
			(new Comments)->setForceSchema(true);
		}

		if(function_exists('proc_nice')){proc_nice(0);}
		//\libs\Watch::php( Capsule::connection($app->lincko->data['database_data'])->getQueryLog() ,'QueryLog', __FILE__, __LINE__, false, false, true);
		return true;
	}

}
