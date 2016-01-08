<?php
// Category api-4
// Category data-1

namespace bundles\lincko\api\models\libs;

use \Exception;
use \libs\Json;
use \libs\STR;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Capsule\Manager as Capsule;

use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Roles;

abstract class ModelLincko extends Model {

	protected static $app = null;

	use SoftDeletes;
	protected $dates = ['deleted_at'];

	protected $guarded = array('*');

////////////////////////////////////////////

	//(used in "toJson()") This is the field to show in the content of history (it will add a prefix "+", which is used for search tool too)
	protected $show_field = false;

	//(used in "toJson()") All field to include in search engine, it will add a prefix "-"
	protected $search_fields = array();

	//Key: Column title to record
	//Value: Title of record
	protected $archive = array(
		'created_at' => 1,  //[{un|ucfirst}] created a new item.
		'_' => 2,//[{un|ucfirst}] modified an item.
		'_access_0' => 96, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to an item.
		'_access_1' => 97, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to an item.
		'_restore' => 98,//[{un|ucfirst}] restored an item.
		'_delete' => 99,//[{un|ucfirst}] deleted an item.
	);

	//When call toJson, convert fields to timestamp format if the field exists only
	protected static $class_timestamp = array(
		'created_at',
		'updated_at',
		'deleted_at',
	);
	protected $model_timestamp = array();

	protected $contactsLock = false; //If true, do not allow to delete the user from the contact list

	protected $contactsVisibility = false; //If true, it will appear in user contact list

	//NOTE: All variables in this array must exist in the database, otherwise an error will be generated during SLQ request.
	protected static $foreign_keys = array(); //Define a list of foreign keys, it helps to give a warning (missing arguments) to the user instead of an error message. Keys are columns name, Values are Models' link.

	protected static $relations_keys_checked = false; //At false it will help to construct the list only once

	//NOTE: Must exist in child "data"
	protected static $relations_keys = array(); //This is a list of parent Models, it helps the front server to know which elements to update without the need of updating all elements and overkilling the CPU usage. This should be accurate using Models' name. We do not have to add foreign keys since it will be added automaticaly by getRelations().

	//It should be a array of [key1:val1, key2:val2, etc]
	//It helps to recover some iformation on client side
	protected $historyParameters = array();

	//List of relations we want to make available on client side
	protected $dependencies_visible = array();

	//List the fields that will be shown on client side
	protected $dependencies_fields = array();

	//Return true if the user is allowed to access(read) the model. We use an attribute to avoid too many mysql request in Data.php
	protected $accessibility = null;

	//Tell which parent role to check if the model doesn't have one, for example Tasks will check Projects if Tasks doesn't have role permission.
	protected $parent = null;
	protected $parent_id = null;
	//This enable or disable the ability to give a permission to a single element with it's children.
	protected $allow_single = false;

	//Note: In relation functions, cannot not use underscore "_", something like "tasks_users()" will not work.

	//No need to abstract it, but need to redefined for the Models that use it
	public function users(){
		return false;
	}

	//Many(Roles) to Many Poly (Users)
	public function roles(){
		$app = self::getApp();
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'relation', 'users_x_roles_x', 'relation_id', 'roles_id')->where('users_id', $app->lincko->data['uid'])->withPivot('access', 'single', 'relation_id', 'relation_type')->take(1);
	}

	//Many(Roles) to Many Poly (Users)
	public function rolesUsers(){
		$app = self::getApp();
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'relation', 'users_x_roles_x', 'relation_id', 'users_id')->withPivot('access', 'single', 'roles_id', 'relation_id', 'relation_type')->take(1);
	}

	public function __construct(array $attributes = array()){
		parent::__construct($attributes);
		$db = Capsule::connection($this->connection);
		$db->enableQueryLog();
	}

	public static function getTableStatic(){
		return (new static())->getTable();
	}

	public static function getApp(){
		if(is_null(self::$app)){
			self::$app = \Slim\Slim::getInstance();
		}
		return self::$app;
	}

	public static function isValid($format){
		return true;
	}

	public static function noValidMessage($return, $function){
		if(!$return){
			$app = self::getApp();
			$app->lincko->data['fields_not_valid'][] = preg_replace('/^valid/ui', '', $function, 1);
		}
		return $return;
	}

	//This function helps to get all instance related to the user itself only
	//It needs to redefine the related function users() too
	//IMPORTANT: getLinked must check if the user has access to it, a good example is Tasks model which include all tasks with access 1 and tasks that belongs to projects with access authorized.
	public function scopegetLinked($query){
		return $query
		//->with('users')
		->whereHas('users', function ($query) {
			$query->theUser();
		});
	}

	public static function getItems($timestamp=false, $id=null){
		$request = self::getLinked();
		if($timestamp!==false){
			$request = $request->where('updated_at', '>=', $timestamp);
		}
		if(!is_null($id)){
			$request = $request->whereIn('id', $id);
		}
		$list = $request->get();
		foreach($list as $key => $value) {
			$list[$key]->accessibility = true; //Because getLinked() only return all with Access allowed
		}
		return $list;
	}

	public function getCompany(){
		return '_';
	}

	public function getParent(){
		if($this->parent && $this->parent_id && $class = $this->getClass($this->parent)){
			return $class::find($this->parent_id);
		}
		if($this->parent && method_exists(get_class($this), $this->parent)){
			if($model = $this->{$this->parent}()->first()){
				$this->parent_id = $model->id;
				return $model;
			}
		}
		$this->parent = false;
		$this->parent_id = false;
		return false;
	}

	public function getParentID(){
		if($this->parent_id){
			return $this->parent_id;
		}
		if($this->parent && isset($this->{$this->parent.'_id'})){
			$this->parent_id = $this->{$this->parent.'_id'};
			return $this->parent_id;
		}
		if($model = $this->getParent()){
			return $this->parent_id;
		}
		$this->parent = false;
		$this->parent_id = false;
		return false;
	}

	public static function getClass($class){
		$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC($class);
		if(class_exists($tp)){
			return $tp;
		}
		return false;
	}

	public function setForceSchema(){
		$list_users = array();
		$users = $this->getUsersContacts();
		foreach ($users as $users_id => $user) {
			$list_users[] = $users_id;
		}
		if(!empty($list_users)){
			// getQuery() helps to not update Timestamps updated_at and get ride off checkAccess
			Users::whereIn('id', $list_users)->where('force_schema', 0)->getQuery()->update(['force_schema' => '1']);
		}
		return true;
	}

	public static function setForceReset(){
		// getQuery() helps to not update Timestamps updated_at and get ride off checkAccess
		Users::getQuery()->update(['force_schema' => '2']);
		return true;
	}

	public function getForceSchema(){
		return false;
	}

	protected static function buildRelations(){
		if(self::$relations_keys_checked === false){
			$models = Data::getModels();
				
			//First we fillin the relation list properly adding foreign keys (parents) for each model
			foreach($models as $model_name => $model) {
				foreach($model::$foreign_keys as $key => $value) {
					$table_name = $value::getTableStatic();
					if(!in_array($table_name, $model::$relations_keys)){
						$model::$relations_keys[] = $table_name;
					}
				}
			}

			//UP: Adding parents level
			$parents = array();
			foreach($models as $model_name => $model) {
				foreach($model::$relations_keys as $key => $value) {
					$parents = array_unique(array_merge($model::$relations_keys, $models[$value]::$relations_keys));
				}
			}
			$model::$relations_keys = array_unique(array_merge($model::$relations_keys, $parents));
			
			//DOWN: Adding children level
			foreach($models as $model_name => $model) {
				foreach($model::$relations_keys as $key => $value) {
					if(!in_array($model_name, $models[$value]::$relations_keys)){
						$models[$value]::$relations_keys[] = $model_name;
					}
				}
			}

			//Reindex all lists, because some list migth not have incremental index
			foreach($models as $model_name => $model) {
				$model::$relations_keys = array_merge($model::$relations_keys);
			}
			
			self::$relations_keys_checked = true;
		}
	}

	public function getRelations(){
		if(self::$relations_keys_checked === false){
			self::buildRelations();
		}
		return $this::$relations_keys;
	}

	//For any Many to Many that we want to make dependencies visible
	//Add an underscore "_"  as prefix to avoid any conflict ($this->_tasks vs $this->tasks)
	public static function getDependencies(array $id_list){
		$dependencies = new \stdClass;
		$model = new static();
		foreach ($model->dependencies_visible as $dependency => $dependencies_fields) {
			if(method_exists(get_class($model), $dependency)) {
				$data = null;
				try { //In case access in not available for the model
					$data = self::whereIn('id', $id_list)->with($dependency)->whereHas($dependency, function ($query){
						$query->where('access', 1);
					})->get(['id']);
				} catch (Exception $obj_exception) {
					//Do nothing to continue
					return $dependencies;
				}
				if(!is_null($data) && !empty($data->toArray())){
					foreach ($data as $dep) {
						foreach ($dep->$dependency as $key => $value) {
							if($value->pivot->access){
								if(!isset($dependencies->{$dep->id})){ $dependencies->{$dep->id} = new \stdClass; }
								if(!isset($dependencies->{$dep->id}->{'_'.$dependency})){ $dependencies->{$dep->id}->{'_'.$dependency} = new \stdClass; }
								if(!isset($dependencies->{$dep->id}->{'_'.$dependency}->{$value->id})){ $dependencies->{$dep->id}->{'_'.$dependency}->{$value->id} = new \stdClass; }
								foreach ($dependencies_fields as $field) {
									if(isset($value->pivot->{$field})){
										$dependencies->{$dep->id}->{'_'.$dependency}->{$value->id}->{$field} = $value->pivot->{$field};
									}
								}
							}
						}
					}
				}
			}
		}
		return $dependencies;
	}

	public static function getUsersContactsID(array $id_list){
		$model = new static();
		$list = array();
		if($data = $model->whereIn('id', $id_list)->with('users')){
			$data = $data->get();
			foreach ($data as $item) {
				//Get users list from items themselves
				if(isset($item->created_by)){
					if(!isset($list[(integer) $item->created_by])){
							$list[(integer) $item->created_by] = $model->getContactsInfo();
					}
				}
				if(isset($item->updated_by)){
					if(!isset($list[(integer) $item->updated_by])){
							$list[(integer) $item->updated_by] = $model->getContactsInfo();
					}
				}
				//Get users list from items access relationship
				if(isset($item->users) && is_object($item->user)){
					if(isset($item->users->id) && !isset($list[(integer) $item->users->id])){
						$list[(integer) $item->users->id] = $model->getContactsInfo();
					}
					foreach ($item->users as $value) {
						if(isset($value->id) && !isset($list[(integer) $value->id])){
							$list[(integer) $value->id] = $model->getContactsInfo();
						}
					}
				}
				
			}
		}
		return $list;
	}

	//This method helps to avoid too many mysql by storing first all history info of a list of items
	public static function getHistories(array $id_list, $history_detail=false){
		$model = new static();
		$history = new \stdClass;
		if(count($model->archive)>0){
			$table_name = $model->getTable();
			$records = History::whereType($table_name)->whereIn('type_id', $id_list)->get();
			foreach ($records as $key => $value) {
				if(array_key_exists($value->attribute, $model->archive)){
					$prefix = $model->getPrefix($value->attribute);
					$created_at = (new \DateTime($value->created_at))->getTimestamp();
					if(!isset($history->{$value->type_id})){ $history->{$value->type_id} = new \stdClass; }
					if(!isset($history->{$value->type_id}->history)){ $history->{$value->type_id}->history = new \stdClass; }
					if(!isset($history->{$value->type_id}->history->$created_at)){ $history->{$value->type_id}->history->$created_at = new \stdClass; }
					if(!isset($history->{$value->type_id}->history->$created_at->{$value->id})){ $history->{$value->type_id}->history->$created_at->{$value->id} = new \stdClass; }
					$history->{$value->type_id}->history->$created_at->{$value->id}->by = $value->created_by;
					$history->{$value->type_id}->history->$created_at->{$value->id}->att = $prefix.$value->attribute;
					if($history_detail){
						$history->{$value->type_id}->history->$created_at->{$value->id}->old = $value->old;
						$history->{$value->type_id}->history->$created_at->{$value->id}->new = $value->new;
					}
					if(!empty($value->parameters)){
						$history->{$value->type_id}->history->$created_at->{$value->id}->par = json_decode($value->parameters);
					}
				}
			}
		}
		return $history;
		
	}

	//detail help to get history detail of an item, we do not allow it at the normal use avoiding over quota memory
	public function getHistory($history_detail=false){
		$history = new \stdClass;
		$parameters = array();
		if(count($this->archive)>0 && isset($this->id)){
			$records = History::whereType($this->getTable())->whereTypeId($this->id)->get();
			foreach ($records as $key => $value) {
				if(array_key_exists($value->attribute, $this->archive)){
					$prefix = $this->getPrefix($value->attribute);
					$created_at = (new \DateTime($value->created_at))->getTimestamp();
					if(!isset($history->$created_at)){ $history->$created_at = new \stdClass; }
					if(!isset($history->$created_at->{$value->id})){ $history->$created_at->{$value->id} = new \stdClass; }
					$history->$created_at->{$value->id}->by = $value->created_by;
					$history->$created_at->{$value->id}->att = $prefix.$value->attribute;
					if($history_detail){
						$history->$created_at->{$value->id}->old = $value->old;
						$history->$created_at->{$value->id}->new = $value->new;
					}
					if(!empty($value->parameters)){
						$parameters = $history->$created_at->{$value->id}->par = json_decode($value->parameters);
					}
				}
			}
		}
		$history = (object) array_merge((array) $history, (array) $this->getHistoryCreation());
		return $history;
	}

	public function getHistoryCreation(array $parameters = array()){
		$history = new \stdClass;
		$created_at = (new \DateTime($this->created_at))->getTimestamp();
		$history->$created_at = new \stdClass;
		$history->$created_at->{'0'} = new \stdClass;
		//Because some models doesn't have creacted_by column (like the companies)
		$created_by = null;
		if(isset($this->created_by)){
			$created_by = $this->created_by;
		}
		$history->$created_at->{'0'}->by = $created_by;
		$history->$created_at->{'0'}->att = 'created_at';
		$history->$created_at->{'0'}->old = null;
		$history->$created_at->{'0'}->new = null;
		if(!empty($parameters)){
			$history->$created_at->{'0'}->par = (object) $parameters;
		}
		return $history;
	}

	/*
		$parameters should be a array of [key1:val1, key2:val2, etc]
	*/
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array()){
		$app = self::getApp();
		$history = new History;
		$history->created_by = $app->lincko->data['uid'];
		$history->type_id = $this->id;
		$history->type = $this->getTable();
		if(!array_key_exists($key, $this->archive)){
			$key = '_';
		}
		$history->attribute = $key;
		if(!is_null($old)){ $history->old = $old; }
		if(!is_null($new)){ $history->new = $new; }
		if(!empty($parameters)){
			$history->parameters = json_encode($parameters, JSON_FORCE_OBJECT);
		}
		$history->save();
	}

	public function getHistoryTitles(){
		$app = self::getApp();
		$connectionName = $this->getConnectionName();
		foreach ($this->archive as $key => $value) {
			$this->archive[$key] = $app->trans->getBRUT($connectionName, 1, $value);
		}
		$titles = new \stdClass;
		foreach ($this->archive as $key => $value) {
			$titles->$key = $value;
		}
		foreach ($titles as $key => $value) {
			$prefix = $this->getPrefix($key);
			if(!empty($prefix)){
				$temp_field = $titles->$key;
				unset($titles->$key);
				$titles->{$prefix.$key} = $temp_field;
			}
		}
		return $titles;
	}

	//Return a list object of users linked to the model in direct relation, It add the value regardless if it's locked or not.
	public function getUsersContacts(){
		$contacts = new \stdClass;
		if(isset($this->created_by)){
			$contacts->{$this->created_by} = $this->getContactsInfo();
		}
		if(isset($this->updated_by)){
			$contacts->{$this->updated_by} = $this->getContactsInfo();
		}
		$list = $this->users()->get();
		foreach($list as $key => $value) {
			$id = $value->id;
			$contacts->$id = $this->getContactsInfo();
		}
		return $contacts;
	}

	public function getContactsLock(){
		return $this->contactsLock;
	}

	public function getContactsVisibility(){
		return $this->contactsVisibility;
	}

	public function getContactsInfo(){
		$info = new \stdClass;
		$info->contactsLock = $this->getContactsLock();
		$info->contactsVisibility = $this->getContactsVisibility();
		return $info;
	}

	//This function helps to delete the indicator as new for an item, it means we already saw it once
	public function viewed(){
		$app = self::getApp();
		if(array_key_exists('viewed_by', $this->attributes)){
			$viewed_by = ';'.$app->lincko->data['uid'].';';
			if(strpos($this->viewed_by, $viewed_by) === false){
				$this->viewed_by .= $viewed_by;
				$this->updateTimestamps();
				parent::save();
			}
		}
	}

	//It checks if the user has access to it
	public function checkAccess(){
		$app = self::getApp();
		if(!is_bool($this->accessibility)){
			$this->accessibility = (bool) false; //By default, for security reason, we do not allow the access
			if(isset($this->id)){
				if($this->getLinked()->count() > 0){
					$this->accessibility = (bool) true;
				}
			} else {
				$this->accessibility = (bool) true; //Set to true for any created item
			}
		}
		if($this->accessibility){
			return true;
		} else {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			\libs\Watch::php(parent::toJson(), $msg, __FILE__, true);
			$json = new Json($msg, true, 406);
			$json->render();
			return false;
		}
	}

	protected function formatLevel($level){
		if(is_string($level)){
			if(strtolower($level) == 'edit'){ $level = 1; } //edit
			else if(strtolower($level) == 'delete'){ $level = 2; } //delete
			else if(strtolower($level) == 'error'){ $level = 3; } //force error
			else { $level = 0; } //read
		} else if(is_integer($level)){
			if($level <0 && $level > 3){ $level = 3; }
		} else {
			$level = 3;
		}
		return $level;
	}

	//It checks if the user has access to edit it
	public function checkRole($level){
		$this->checkAccess();
		$app = self::getApp();
		$level = $this->formatLevel($level);

		$model = $this;
		$suffix = $model->getTable();
		$role = false;
		$allow = 0; //Only allow reading

		if($level >=1 && $level <= 2){
			$table = array();
			$loop = true;
			while($loop){
				if(!in_array($model->getTable(), $table) && !$role = $model->roles()->first()){
					$table[] = $model->getTable();
					if($model = $model->getParent()){
						continue;
					}
				}
				$loop = false;
				break;
			}
			
			if($role){
				if($role->perm_grant){ //Grant permission
					$allow = 2;
				} else if(isset($role->{'perm_'.$suffix})){ //Per model
					$allow = $role->{'perm_'.$suffix};
				} else { //General
					$allow = $role->perm_all;
				}
			}
		}
		
		if($level <= $allow){
			return true;
		} else {
			$msg = $app->trans->getBRUT('api', 0, 5); //You are not allow to edit the server data.
			\libs\Watch::php(parent::toJson(), $suffix.' : '.$msg, __FILE__, true);
			$json = new Json($msg, true, 406);
			$json->render();
			return false;
		}
		return false;
	}

	//When save, it helps to keep track of history
	public function save(array $options = array()){
		$this->checkRole('edit');
		$app = self::getApp();
		$dirty = $this->getDirty();
		$original = $this->getOriginal();
		$new = !isset($this->id);
		if(array_key_exists('viewed_by', $this->attributes)){
			$this->viewed_by = ';'.$app->lincko->data['uid'].';';
		}
		//Only check foreign keys for new items
		if($new){
			$missing_arguments = array();
			foreach($this::$foreign_keys as $key => $value) {
				if(!isset($this->$key)){
					if($key=='created_by' || $key=='updated_by'){
						$this->$key = $app->lincko->data['uid'];
					} else {
						$missing_arguments[] = $key;
					}
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

		$return = parent::save($options);
		//We do not record any setup for new model, but only change for existing model
		if(!$new){
			foreach($dirty as $key => $value) {
				//We exclude "created_at" if not we will always record it on history table, that might fulfill it too quickly
				if($key != 'created_at' && array_key_exists($key, $this->archive)){
					$old = null;
					$new = null;
					if(isset($original[$key])){ $old = $original[$key]; }
					if(isset($dirty[$key])){ $new = $dirty[$key]; }
					$this->setHistory($key, $old, $new);
				}
			}
		} else if(isset($app->lincko->data['uid'])){
			//For any new model, we force to enable the access to the user
			//But we disable the history record since it's during creation of new model only
			$this->setUserPivotValue($app->lincko->data['uid'], 'access', 1, false);
		}
		return $return;
		
	}
	
	public function delete(){
		$this->checkRole('delete');
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
	}

	public function restore(){
		$this->checkRole('delete');
		if(isset($this->deleted_at) && isset($this->attributes) && array_key_exists('deleted_at', $this->attributes)){
			if(array_key_exists('deleted_by', $this->attributes)){
				$this->deleted_at = null;
				$this->setHistory('_restore');
				$this->save();
				$this->setForceSchema();
			}
			parent::restore();
		}
	}

	public function toJson($detail=true, $options = 0){
		$this->checkAccess(); //To avoid too many mysql connection, we can set the protected attribute "accessibility" to true if getLinked is used using getItems()
		$app = self::getApp();
		if($detail){
			$temp = json_decode(parent::toJson($options));
			foreach ($temp as $key => $value) {
				$prefix = $this->getPrefix($key);
				if(!empty($prefix)){
					$temp_field = $temp->$key;
					unset($temp->$key);
					$temp->{$prefix.$key} = $temp_field;
				}
			}
			$temp = json_encode($temp, $options);
		} else {
			$temp = parent::toJson($options);
		}
		//Convert DateTime to Tiestamp for JS use, it avoid location hour issue.
		$temp = json_decode($temp);
		foreach(self::$class_timestamp as $value) {
			if(isset($temp->$value)){  $temp->$value = (new \DateTime($temp->$value))->getTimestamp(); }
		}
		foreach($this->model_timestamp as $value) {
			if(isset($temp->$value)){  $temp->$value = (new \DateTime($temp->$value))->getTimestamp(); }
		}
		//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
		$temp->new = 0;
		if(isset($this->viewed_by)){
			if(strpos($this->viewed_by, ';'.$app->lincko->data['uid'].';') === false){
				$temp->new = 1;
			}
		}
		$temp = json_encode($temp, $options);
		return $temp;
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

	//By preference, keep it protected
	//public function getUserPivotValue($users_id, $column){
	protected function getUserPivotValue($users_id, $column){
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

	protected function getPivotArchiveTitle($column, $value){
		if(array_key_exists('_'.$column.'_'.$value, $this->archive)){
			return '_'.$column.'_'.$value;
		} else if(array_key_exists('_'.$column, $this->archive)){
			return '_'.$column;
		}
		return '_'; //Neutral comment
	}

	//By preference, keep it protected, public is only for test
	public function setUserPivotValue($users_id, $column, $value=0, $history=true){ //[toto]
	//protected function setUserPivotValue($users_id, $column, $value=0, $history=true){
		$app = self::getApp();
		$column = strtolower($column);
		$return = false;
		$users = $this->users();
		if($users === false || !method_exists($users,'updateExistingPivot') || !method_exists($users,'attach')){ return false; } //If the pivot doesn't exist, we exit
		$pivot = $this->getUserPivotValue($users_id, $column);
		$value_old = $pivot[1];
		if($pivot[0]){
			if($value_old != $value){
				//Modify a line
				$users->updateExistingPivot($users_id, array($column => $value));
				if($history){
					$archive_title = $this->getPivotArchiveTitle($column, $value);
					$this->setHistory($archive_title, $value, $value_old, array('cun' => Users::find($users_id)->username));
					$this->touch();
				}
				$return = true;
			}
		} else {
			//Create a new line
			$users->attach($users_id, array($column => $value));
			if($history){
				$archive_title = $this->getPivotArchiveTitle($column, $value);
				$this->setHistory($archive_title, $value, $value_old, array('cun' => Users::find($users_id)->username));
				$this->touch();
			}
			$return = true;
		}
		if($return){
			//Force all linked users to recheck their Schema
			$this->setForceSchema();
		}
		//Do not change anything, it's the same
		return $return;
	}


	//By preference, keep it protected
	public function getRolePivotValue($users_id){
	//protected function getRolePivotValue($users_id){
		if($roles = $this->rolesUsers()){
			if($role = $roles->wherePivot('users_id', $users_id)->first()){
				return array(true, $role->pivot->roles_id, $role->pivot->single);
			}
		}
		return array(false, null, null);
	}

	//By preference, keep it protected, public is only for test
	public function setRolePivotValue($users_id, $roles_id=3, $single=null, $history=true){ //[toto]
		$app = self::getApp();
		$return = false;
		$user = $this->rolesUsers();
		if($user === false || !method_exists($user,'updateExistingPivot') || !method_exists($user,'attach')){ return false; } //If the pivot doesn't exist, we exit
		$pivot = $this->getRolePivotValue($users_id);
		$roles_id_old = $pivot[1];
		$single_old = $pivot[2];
		if(!$this->allow_single){
			$single=null;
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
		} else {
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
			//Force all linked users to recheck their Schema
			$this->setForceSchema();
		}
		//Do not change anything, it's the same
		return $return;
	}

}