<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\Updates;
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
	protected $lastvisit_timestamp = 0;
	protected $lastvisit_object = false;
	protected $partial = NULL;

	protected $full_schema = false;
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

	public function dataUpdateConfirmation($msg, $status=200, $show=false, $lastvisit=0){
		$app = $this->app;
		if($this->setLastVisit() && $lastvisit>0){
			$lastvisit = time();
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
					'msg' => $app->trans->getBRUT('api', 8888, 9), //You got the latest updates.
					'partial' => $this->getLatest($lastvisit),
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
								}
							}
							$tree_id[$key] = array();
							$result_bis = false;
							$nested = true;
							while($nested){ //$nested is used for element that are linked to each others
								$nested = false;
								$class::enableTrashGlobal(true);
								$result_bis = $class::getItems($list, true); //toto => try (new function, to work with stored database instead of always calculating it)
								$class::enableTrashGlobal(false);
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
		$app = \Slim\Slim::getInstance();
		$tree_access = array();
		if(isset($tree_id['users'])){
			$list_models = self::getModels();
			foreach ($tree_id as $table => $list) {
				if(isset($list_models[$table])){
					$class = $list_models[$table];
					$tree_access[$table] = $class::filterPivotAccessList($list, false, true); //Getting real Pivot value
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
		//Capsule::connection('data')->enableQueryLog();
		$uid = $app->lincko->data['uid'];
		$workid = $app->lincko->data['workspace_id'];
		$list_models = self::getModels();

		//---OK---
		if($this->action == 'latest'){
			$updates = array();
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

		//toto => we can try to store in database (Updates) list of IDs with timetamp
		//toto => MEDIUM CPU hunger
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
			$users = array_merge($tree_id['users'], $users);
		}
		
		//---OK---
		foreach ($users as $users_id) {
			if(isset($tree_id['users'])){
				$tree_id['users'][$users_id] = $users_id;
			}
		}

		//---OK---
		$users = array();
		$users['users'] = $tree_id['users'];
		$users_access = $this::getAccesses($users); //Check if at least other users have access (since we narrow to users only, the calulation is ligth)

		//---OK---
		$tree_access = array();
		foreach ($users_access as $type => $type_list) {
			foreach ($type_list as $users_id => $models) {
				foreach ($models as $id => $value) {
					$tree_access[$type][$users_id][$id] = true;
				}
			}
		}

		//---OK---
		foreach ($result as $type => $models) {
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


		//---OK---
		//Get the list of all users that have access
		foreach ($tree_access as $type => $type_list) {
			foreach ($type_list as $users_id => $value) {
				if(isset($tree_id['users'])){
					$tree_id['users'][$users_id] = $users_id;
				}
			}
		}
		$all_users = $tree_id['users'];

		//---OK---
		$result->users = Users::getUsersContacts($tree_id['users'], $visible);

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

		//toto => HIGH CPU hunger, but cannot be optimized more
		$list_id = array();
		foreach ($result_bis->$uid as $table_name => $models) {
			$list_id[$table_name] = array();
			foreach ($models as $id => $model) {
				$list_id[$table_name][$id] = $id;
				unset($temp);
				$temp = new \stdClass;
				if($this->item_detail){
					$model->accessibility = true;
					//$temp = json_decode($model->toJson());
					$temp = $model->toVisible();
					unset($temp->{'id'}); //Delete ID property since it becomes the key of the table
					//Get only creation history to avoid mysql overload
					$temp->history = $model->getHistoryCreation();
				} else {
					//need delete information for schema
					$temp->deleted_at = $model->deleted_at;
				}
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

			//Delete temp_id if the user is not concerned
			foreach ($result_bis->$uid as $table_name => $models) {
				foreach ($result_bis->$uid->$table_name as $id => $temp) {
					if(isset($temp->created_by) && $temp->created_by!=$uid){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					}
					if(isset($temp->temp_id) && $temp->temp_id==''){
						unset($result_bis->$uid->$table_name->$id->temp_id);
					}
				}
			}

			//Get dependency (all ManyToMany that have other fields than access)
			$dependencies = Users::getDependencies($list_id, $list_models);

			foreach ($result_bis->$uid as $table_name => $models) {
				if(!isset($dependencies[$table_name])){
					continue;
				}
				$class = $list_models[$table_name];
				$default = false;
				$default_list = array();
				if(isset($class::getDependenciesVisible()['users'])){
					$default = $class::filterPivotAccessGetDefault();
					foreach ($all_users as $key => $value) {
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
								$default_full[$users_id] = (array) $deps[$users_id];
							} else {
								$default_full[$users_id] = $default_list[$users_id];
							}
						}
					}
					$result_bis->$uid->$table_name->$id->_users = (object) $default_full;
				}
			}

			//toto => HIGH CPU hunger
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
		if($this->full_schema){
			$result_bis->$uid->{'_history_title'} = new \stdClass;
		}

		//It gather the fields we need to workspace only
		if(!is_null($this->partial) && isset($this->partial->$uid)){
			if(isset($this->partial->$uid->{'_history_title'})){
				$result_bis->$uid->{'_history_title'} = new \stdClass;
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

		//Delete the part that the application doesn't have access
		if(!isset($app->lincko->api['x_i_am_god']) || !$app->lincko->api['x_i_am_god']){
			foreach ($result_bis->$uid as $table_name => $models) {
				if(!isset($app->lincko->api['x_'.$table_name]) || !$app->lincko->api['x_'.$table_name]){
					unset($result_bis->$uid->$table_name);
				}
			}
		}

		//\libs\Watch::php($result_bis, '$result_bis', __FILE__, false, false, true);
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() ,'QueryLog', __FILE__, false, false, true);
		return $result_bis;

	}

}
