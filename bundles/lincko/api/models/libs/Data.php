<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Roles;
use \bundles\lincko\api\models\libs\PivotUsersRoles;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected static $models = NULL;
	protected $lastvisit = false; //Format 'Y-m-d H:i:s'
	protected $lastvisit_timestamp = false;
	protected $partial = NULL;

	protected $item_detail = true;
	protected $history_detail = false;
	protected $action = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function reinit(){
		$this->lastvisit = false;
		$this->partial = NULL;
		$this->item_detail = true;
		$this->history_detail = false;
	}

	public function dataUpdateConfirmation($msg, $status=200){
		$app = $this->app;
		if($this->setLastVisit()){
			$lastvisit = time()-1;
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 9), //You got the latest updates.
					'partial' => $this->getLatest(),
					'lastvisit' => $lastvisit,
				),
				$msg
			);
		} else {
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 13), //Server OK
					'partial' => $this->getNewest(),
				),
				$msg
			);
		}
		$app->render($status, array('msg' => $msg,));
		return true;
	}

	protected function setLastVisit($timestamp='false'){
		if(is_integer($timestamp)){
			if($timestamp>0){
				return $this->lastvisit = (new \DateTime('@'.$timestamp))->format('Y-m-d H:i:s');
			}
			return $this->lastvisit = false;
		} else if(isset($this->data->data->lastvisit)){
			if(is_integer($this->data->data->lastvisit) && $this->data->data->lastvisit>0){
				return $this->lastvisit = (new \DateTime('@'.$this->data->data->lastvisit))->format('Y-m-d H:i:s');
			}
			return $this->lastvisit = false;
		}
		return $this->lastvisit = (new \DateTime())->format('Y-m-d H:i:s');
	}

	public function getTimestamp(){
		if($this->lastvisit_timestamp){
			return $this->lastvisit_timestamp;
		} else if($this->lastvisit){
			return $this->lastvisit_timestamp = (new \DateTime($this->lastvisit))->getTimestamp();
		} else if($this->setLastVisit()){
			return $this->lastvisit_timestamp = (new \DateTime($this->lastvisit))->getTimestamp();
		} else {
			return 0;
		}
	}

	public function getTimeobject(){
		if($this->lastvisit){
			return new \DateTime($this->lastvisit);
		} else if($this->setLastVisit()){
			return new \DateTime($this->lastvisit);
		} else {
			return false;
		}
	}

	protected function setPartial($force_partial=false){
		if($force_partial){
			return $this->partial = $force_partial;
		} else if(is_object($this->partial)){
			return $this->partial;
		} else if(isset($this->data->data->partial)){
			if(is_object($this->data->data->partial)){
				return $this->partial = $this->data->data->partial;
			}
		}
		//This will help missing and history to not scan the whole database if we do not provide a partial parameter
		return $this->partial = new \stdClass;
	}

	public static function getModels(){
		if(is_null(self::$models)){
			$sql = 'SHOW TABLES;';
			$db = Capsule::connection('data');
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

	public static function getTrees($list_tp=false){
		if(!$list_tp || !is_array($list_tp)){
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

		// Get all ID with parent dependencies
		$loop = true;
		$tree_tp = $tree_scan;
		$tree_id = array();
		$result = new \stdClass;
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
							}
						}
						$tree_id[$key] = array();
						$result_bis = false;
						$nested = true;
						while($nested){ //$nested is used for element that are linked to each others
							$nested = false;
							$class::enableTrashGlocal(true);
							$result_bis = $class::getItems($list, true);
							$class::enableTrashGlocal(false);
							if(isset($result->$key)){
								$result->$key = $result->$key->merge($result_bis);
							} else {
								$result->$key = $result_bis;
							}
							$list = array();
							$list[$key] = array();
							foreach ($result_bis as $value_bis) {
								if(!isset($tree_id[$key][$value_bis->id])){ //Insure to not record twice the same ID to not enter inside an infinite loop
									$list[$key][$value_bis->id] = $value_bis->id;
								}
								$tree_id[$key][$value_bis->id] = $value_bis->id;
							}
							unset($result_bis);
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
		return array($tree_scan, $tree_desc, $tree_id, $result);
	}

	public static function getAccesses($tree_id){
		$app = \Slim\Slim::getInstance();
		$tree_access = array();
		if(isset($tree_id['users'])){
			$list_models = self::getModels();
			foreach ($tree_id as $table => $list) {
				if(isset($list_models[$table])){
					$class = $list_models[$table];
					$tree_access[$table] = $class::filterPivotAccessList($list); //Getting real Pivot value
				}
			}
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
			if($app->lincko->data['workspace_id']==0){
				if(!isset($tree_access['workspaces'])){ $tree_access['workspaces'] = array(); }
				foreach ($users as $users_id) {
					if(!isset($tree_access['workspaces'][$users_id])){ $tree_access['workspaces'][$users_id] = array(); }
					$tree_access['workspaces'][$users_id][0] = array(
						'access' => 1, //Give access to all user
						'super' => 0, //Prohibit super permission to shared workspace
					);
				}
			}
		}
		return $tree_access;
	}

	public function getLatest($timestamp=false){
		$this->action = 'latest';
		$this->reinit();
		$this->setLastVisit($timestamp);
		$this->partial = NULL;
		return $this->getList();
	}

	public function getNewest(){
		$this->action = 'newest';
		$this->reinit();
		$app = $this->app;
		$this->lastvisit = (new \DateTime('@'.$app->lincko->data['lastvisit']))->format('Y-m-d H:i:s');
		$this->partial = NULL;
		return $this->getList();
	}

	public function getSchema(){
		$this->action = 'schema';
		$this->reinit();
		$this->lastvisit = false;
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
		//Capsule::connection('data')->enableQueryLog();
		$uid = $app->lincko->data['uid'];
		$workid = $app->lincko->data['workspace_id'];
		$list_models = self::getModels();
		$full_schema = false;
		//If the lastvisit is not set, and we do not work with partial database, we force to get all details
		if(!$this->lastvisit && is_null($this->partial) && !$this->history_detail){
			$full_schema = true;
		}

		$tp = $this::getTrees();
		$tree_scan = $tp[0];
		$tree_desc = $tp[1];
		$tree_id = $tp[2];
		$result = $tp[3];
		unset($tp);

		$users = array();
		foreach ($result as $models) {
			foreach ($models as $model) {
				$users = array_merge($users, $model->setContacts());
			}
		}

		$visible = array();
		if(isset($tree_id['users'])){
			$visible = $tree_id['users'];
			$users = array_merge($tree_id['users'], $users);
		}
		
		foreach ($users as $users_id) {
			$tree_id['users'][$users_id] = $users_id;
		}

		$tree_access = $this::getAccesses($tree_id); //Check if at least other users have access
		//Get the list of all users that have access
		foreach ($tree_access as $type => $type_list) {
			foreach ($type_list as $users_id => $value) {
				$tree_id['users'][$users_id] = $users_id;
			}
		}

		//Insure we get all users information (it can be a heavy operation over the time, need to careful)
		$result->users = Users::getUsersContacts($tree_id, $visible);

		if($this->item_detail){

			$tree_super = array(); //Permission allowed for the super user (Priority 1 / fixed), defined at workspace workspace only => Need to scan the tree to assigned children
			$tree_owner = array(); //Permission allowed for the owner (Priority 2 / fixed)
			$tree_single = array(); //Permission allowed for the user at single element level (Priority 3 / cutomized)
			$tree_role = array(); //Permission allowed for the user according the herited Roles(Priority 4 / cutomized) => Need to scan the tree to assigned children

			$tree_roles_id = array();
			$roles = array();
			if(isset($result->roles)){
				foreach ($result->roles as $value) {
					$roles[$value->id] = $value;
				}
			}

			//Tell if the user has super access to the workspace
			$work_super = array();
			foreach ($tree_id['users'] as $users_id) {
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

			$pivot = PivotUsersRoles::getRoles($tree_id);
			foreach ($pivot as $value) {
				$table_name = $value->parent_type;
				$id = $value->parent_id;
				$users_id = $value->users_id;
				if(
					   isset($tree_id[$table_name])
					&& isset($tree_id[$table_name][$id])
				){
					$class = false;
					if(isset($list_models[$table_name])){
						$class = $list_models[$table_name];
					}
					//Single (ok)
					if($class && $value->single){
						if($class::getRoleAllow()[0]){ //Single (RCUD)
							if(!isset($tree_single[$table_name])){ $tree_single[$table_name] = array(); }
							if(!isset($tree_single[$table_name][$users_id])){ $tree_single[$table_name][$users_id] = array(); }
							$tree_single[$table_name][$users_id][$id] = (int) $value->single;
						}
					}
					//Role (will affect children)
					if($class && $value->roles_id && isset($tree_id['roles']) && isset($tree_id['roles'][$value->roles_id])){ //The last condition insure that the Role was not deleted
						if($class::getRoleAllow()[1]){ //Role (Role ID)
							if(!isset($tree_roles_id[$table_name])){ $tree_roles_id[$table_name] = array(); }
							if(!isset($tree_roles_id[$table_name][$users_id])){ $tree_roles_id[$table_name][$users_id] = array(); }
							$tree_roles_id[$table_name][$users_id][$id] = (int) $value->roles_id;
						}
					}
				}
			}
			
		}
		
		//By default, give Administrator role to all users inside shared workspace
		if($app->lincko->data['workspace_id']==0){
			if(!isset($tree_roles_id['workspaces'])){
				$tree_roles_id['workspaces'] = array();
			}
			foreach ($tree_id['users'] as $users_id) {
				if(!isset($tree_roles_id['workspaces'][(int)$users_id])){
					$tree_roles_id['workspaces'][(int)$users_id] = array();
				}
				$tree_roles_id['workspaces'][(int)$users_id][0] = 1;
			}
		}

		$result_bis = new \stdClass;
		$result_bis->$uid = new \stdClass;
		$lastvisit_obj = $this->getTimeobject();
		if($lastvisit_obj && $this->getTimestamp()>0){
			foreach ($result as $key => $models) {
				foreach ($models as $key_bis => $model) {
					if(
						   $full_schema //For Schema
						|| $model->updated_at >= $lastvisit_obj //For Latest
						|| (!is_null($this->partial) && isset($this->partial->$uid) && isset($this->partial->$uid->$key) && isset($this->partial->$uid->$key->{$model->id})) //For Missing
					){
						if(!isset($result_bis->$uid->$key)){
							$result_bis->$uid->$key = new \stdClass;
						}
						$result_bis->$uid->$key->{$model->id} = $model;
					}
				}
			}
		} else {
			foreach ($result as $key => $models) {
				$result_bis->$uid->$key = new \stdClass;
				foreach ($models as $key_bis => $model) {
					$result_bis->$uid->$key->{$model->id} = $model;
				}
			}
		}

		unset($result);
		
		//Delete the useless part if partial
		if(!is_null($this->partial) && isset($this->partial->$uid)){
			foreach ($result_bis->$uid as $key => $models) {
				if(!isset($this->partial->$uid->$key)){
					unset($result_bis->$uid->$key);
					unset($tree_id[$key]);
					continue;
				} else {
					foreach ($models as $key_bis => $model) {
						if(!isset($this->partial->$uid->$key->key_bis)){
							unset($result_bis->$uid->$key->key_bis);
							unset($tree_id[$key][$key_bis]);
							continue;
						}
					}
				}
			}
		}

		if($this->item_detail){
			//Onwer (ok) , it needs to works with model, not array convertion
			foreach ($result_bis->$uid as $table_name => $models) {
				if(isset($list_models[$table_name])){
					$class = $list_models[$table_name];
					foreach ($models as $key => $model) {
						if(!isset($tree_owner[$table_name])){ $tree_owner[$table_name] = array(); }
						foreach ($tree_id['users'] as $users_id) {
							if(!isset($tree_owner[$table_name][$users_id])){ $tree_owner[$table_name][$users_id] = array(); }
							$tree_owner[$table_name][$users_id][$model->id] = $model->getPermissionOwner($users_id);
						}
					}
				}
			}
		}

		$list_id = array();
		foreach ($result_bis->$uid as $table_name => $models) {
			$list_id[$table_name] = array();
			foreach ($models as $id => $model) {
				$list_id[$table_name][] = $id;
				unset($temp);
				$temp = new \stdClass;
				$temp = json_decode($model->toJson());
				unset($temp->{'id'}); //Delete ID property since it becomes the key of the table
				//Get only creation history to avoid mysql overload
				$temp->history = $model->getHistoryCreation();
				$temp->_parent = $model->setParentAttributes();
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

			$root_uid = array();
			//Build the tree per user
			foreach ($tree_id['users'] as $users_id) {
				$root_uid[(int)$users_id] = array( 0 => json_decode(json_encode($root_0)) ); //No Super applied (0) at the root level
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
							//For guess users, delete the part of tree that are inaccessible
							if($users_id != $uid){
								if(
									   !isset($tree_access[$table_name])
									|| !isset($tree_access[$table_name][$users_id])
								){
									continue;
								}
							}
							$super_perm = 0; //[R]
							$class = false;
							if(isset($list_models[$table_name])){
								$class = $list_models[$table_name];
							}
							if($super && $class){
								$super_perm = $class::getPermissionSheet()[1];
							}
							if(!isset($tree_super[$table_name])){ $tree_super[$table_name] = array(); }
							if(!isset($tree_super[$table_name][$users_id])){ $tree_super[$table_name][$users_id] = array(); }
							if(!isset($tree_access_users[$table_name])){ $tree_access_users[$table_name] = array(); }
							if(!isset($tree_access_users[$table_name][$users_id])){ $tree_access_users[$table_name][$users_id] = array(); }
							foreach ($models as $id => $model) {
								//For guess users, delete the part of tree that are inaccessible
								if($users_id != $uid){
									if(
										   !isset($tree_access[$table_name][$users_id][$id])
										|| !isset($tree_access[$table_name][$users_id][$id]['access'])
										|| !$tree_access[$table_name][$users_id][$id]['access']
									){
										continue;
									} else {
										$tree_access_users[$table_name][$users_id][$id] = true;
									}
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
				$arr = $root_uid[$users_id];
				$arr_tp = $arr;
				$i = 1000; //Avoid infinite loop (1000 nested level, which should never happened)
				while(!empty($arr)){
					$arr_tp = array();
					foreach ($arr as $role => $list) {
						foreach ($list as $table_name => $models) {
							//For guess users, delete the part of tree that are inaccessible
							if($users_id != $uid){
								if(
									   !isset($tree_access[$table_name])
									|| !isset($tree_access[$table_name][$users_id])
								){
									continue;
								}
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
							if(!isset($tree_role[$table_name])){ $tree_role[$table_name] = array(); }
							if(!isset($tree_role[$table_name][$users_id])){ $tree_role[$table_name][$users_id] = array(); }
							foreach ($models as $id => $model) {
								//For guess users, delete the part of tree that are inaccessible
								if($users_id != $uid){
									if(
										   !isset($tree_access[$table_name][$users_id][$id])
										|| !isset($tree_access[$table_name][$users_id][$id]['access'])
										|| !$tree_access[$table_name][$users_id][$id]['access']
									){
										continue;
									}
								}
								$role_perm_elem = $role_perm;
								$role_tp = $role;
								if($allow_role && isset($tree_roles_id[$table_name]) && isset($tree_roles_id[$table_name][$users_id]) && isset($tree_roles_id[$table_name][$users_id][$id])){
									$role_tp = $tree_roles_id[$table_name][$users_id][$id];
								} else {
									if(!isset($tree_roles_id[$table_name])){ $tree_roles_id[$table_name] = array(); }
									if(!isset($tree_roles_id[$table_name][$users_id])){ $tree_roles_id[$table_name][$users_id] = array(); }
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

			foreach ($result_bis->$uid as $table_name => $models) {
				foreach ($result_bis->$uid->$table_name as $id => $temp) {
					//Delete temp_id if the user is not concerned
					if(isset($temp->created_by) && $temp->created_by!=$uid){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					}
					if(isset($temp->temp_id) && $temp->temp_id==''){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					}

					$result_bis->$uid->$table_name->$id->_perm = new \stdClass;
					//Set permission per user
					foreach ($tree_id['users'] as $users_id) {
						//Check access first
						if(
							   !isset($tree_access_users[$table_name])
							|| !isset($tree_access_users[$table_name][$users_id])
							|| !isset($tree_access_users[$table_name][$users_id][$id])
						){
							continue;
						}
						$perm_owner = 0; //tree_owner
						if(isset($tree_owner[$table_name]) && isset($tree_owner[$table_name][$users_id]) && isset($tree_owner[$table_name][$users_id][$id])){ $perm_owner = $tree_owner[$table_name][$users_id][$id]; }
						$perm_super = 0; //tree_super
						if(isset($tree_super[$table_name]) && isset($tree_super[$table_name][$users_id]) && isset($tree_super[$table_name][$users_id][$id])){ $perm_super = $tree_super[$table_name][$users_id][$id]; }
						$perm_single = 0; //tree_single (priority on single over Role)
						$perm_role = 0; //tree_role
						if(isset($tree_single[$table_name]) && isset($tree_single[$table_name][$users_id]) && isset($tree_single[$table_name][$users_id][$id])){
							$perm_single = $tree_single[$table_name][$users_id][$id];
						} else if(isset($tree_role[$table_name]) && isset($tree_role[$table_name][$users_id]) && isset($tree_role[$table_name][$users_id][$id])){
							$perm_role = $tree_role[$table_name][$users_id][$id];
							if(!isset($result_bis->$uid->roles) || !isset($result_bis->$uid->roles->$perm_role)){
								$perm_role = 0; //If the role is not register we set to viewer
							}
						}
						$role_id = 0; //tree_role
						if(isset($tree_roles_id[$table_name]) && isset($tree_roles_id[$table_name][$users_id]) && isset($tree_roles_id[$table_name][$users_id][$id])){ $role_id = $tree_roles_id[$table_name][$users_id][$id]; }

						$result_bis->$uid->$table_name->$id->_perm->$users_id = array(
							(int)max($perm_owner, $perm_super, $perm_single, $perm_role),
							(int)$role_id,
						);
					}
				}
			}

			//Get dependency (all ManyToMany that have other fields than access)
			$dependencies = Users::getDependencies($list_id, $list_models);
			foreach ($dependencies as $table_name => $models) {
				foreach ($models as $id => $temp) {
					if(isset($result_bis->$uid->$table_name->$id)){
						$result_bis->$uid->$table_name->$id = (object) array_merge((array) $result_bis->$uid->$table_name->$id, (array) $temp);
					}
				}
			}
			$list_models = self::getModels();
			foreach ($tree_access as $table_name => $users) {
				if(isset($result_bis->$uid->$table_name) && isset($list_models[$table_name])){
					$class = $list_models[$table_name];
					if(isset($class::getDependenciesVisible()['users'])){
						$dependencies_visible_users = $class::getDependenciesVisible()['users'];
						foreach ($users as $users_id => $models) {
							foreach ($models as $id => $pivot_array) {
								if(isset($result_bis->$uid->$table_name->$id)){
									if(!isset($result_bis->$uid->$table_name->$id->_users)){ $result_bis->$uid->$table_name->$id->_users = new \stdClass; }
									if(!isset($result_bis->$uid->$table_name->$id->_users->$users_id)){
										$temp = new \stdClass;
										$error = false;
										foreach ($dependencies_visible_users as $key) {
											if(!isset($pivot_array[$key])){
												$error = true;
												break;
											} else {
												$temp->$key = $pivot_array[$key];
											}
										}
										if($error){
											continue;
										}
										$result_bis->$uid->$table_name->$id->_users->$users_id = $temp;
									}
								}
							}
						}
					}
				}
			}
				


			$histories = Users::getHistories($list_id, $list_models, $this->history_detail);
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

		//Get the relations list
		if($full_schema){
			$result_bis->$uid->{'_tree'} = $root_0;
			$result_bis->$uid->{'_history_title'} = new \stdClass;
		}

		//It gather the fields we need to workspace only
		if(!is_null($this->partial) && isset($this->partial->$uid)){
			if($this->item_detail && isset($this->partial->$uid->{'_tree'})){
				$result_bis->$uid->{'_tree'} = $root_0;
			}
			if(isset($this->partial->$uid->{'_history_title'})){
				$result_bis->$uid->{'_history_title'} = new \stdClass;
			}
		}

		if(
			$full_schema
			||
			(
				!is_null($this->partial)
				&& isset($this->partial->$uid)
				&& isset($this->partial->$uid->{'_history_title'})
				&& isset($this->partial->$uid->{'_history_title'}->$table_name)
			)
		)
		{
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
		
		//\libs\Watch::php($result_bis, '$result_bis', __FILE__, false, false, true);
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() ,'QueryLog', __FILE__, false, false, true);
		return $result_bis;

	}

}
