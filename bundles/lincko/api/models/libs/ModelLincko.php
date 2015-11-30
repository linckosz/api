<?php
// Category api-4
// Category data-1

namespace bundles\lincko\api\models\libs;

use \libs\Json;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\data\Users;

abstract class ModelLincko extends Model {

	protected static $app = null;

	use SoftDeletingTrait;
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

	protected $contactsLock = false; //If true, do not allow to delete the user from the contact list

	protected $contactsVisibility = false; //If true, it will appear in user contact list

	//NOTE: All variables in this array must exist in the database, otherwise an error will be generated during SLQ request.
	protected static $foreign_keys = array(); //Define a list of foreign keys, it help to give a warning (missing arguments) to the user instead of an error message. Keys are columns name, Values are Models' link.

	protected static $relations_keys_checked = false; //At false it will help to construct the list only once

	//NOTE: Must exist in child "data"
	protected static $relations_keys = array(); //This is a list of parent Models, it helps the front server to know which elements to update without the need of updating all elements and overkilling the CPU usage. This should be accurate using Models' name. We do not have to add foreign keys since it will be added automaticaly by getRelations().

	//It should be a array of [key1:val1, key2:val2, etc]
	//It helps to recover some iformation on client side
	protected $historyParameters = array();

	//List of relations we want to make available on client side
	protected $dependencies_visible = array();

	//Note: In relation functions, cannot not use underscore "_", something like "tasks_users()" will not work.

	//For any Many to Many that we want to make dependencies visible
	//Add an underscore "_"  as prefix to avoid any conflict ($this->_tasks vs $this->tasks)
	public function addDependencies(){
		foreach ($this->dependencies_visible as $dependency) {
			unset($result);
			$result = new \stdClass;
			$data = $this->$dependency()->where('access', 1)->get();
			if(!is_null($data)){
				foreach ($data as $key => $value) {
					$result->{$value->id} = $value->pivot;
				}
			}
			$this->{'_'.$dependency} = $result;
		}
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
	//It needs to redefine the related function user() too
	public function scopegetLinked($query){
		return $query->whereHas('users', function ($query) {
			$query->theUser();
		});
	}

	//No need to abstract it, but need to redefined for the Models that use it
	public function users(){
		return false;
	}

	public function getCompany(){
		return '_';
	}

	public function setForceSchema(){
		$list_users = array();
		$users = $this->getUsersContacts();
		foreach ($users as $users_id => $user) {
			$list_users[] = $users_id;
		}
		if(!empty($list_users)){
			// getQuery() helps to not update Timestamps updated_at
			Users::whereIn('id', $list_users)->getQuery()->update(['force_schema' => '1']);
		}
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
			foreach($models as $model_name => $model) {
				$count = 0;
				while(count($model::$relations_keys) !== $count){
					foreach($model::$relations_keys as $key => $value) {
						$model::$relations_keys = array_unique(array_merge($model::$relations_keys, $models[$value]::$relations_keys));
					}
					$count = count($model::$relations_keys);
				}
			}
			
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

	//detail help to get history detail of an item, we do not allow it at the normal use avoiding over quota memory
	public function getHistory($history_detail=false){
		$history = $this->getHistoryCreation($history_detail);
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
						$history->$created_at->{$value->id}->par = json_decode($value->parameters);
					}
				}
			}
		}
		return $history;
	}

	protected function getHistoryCreation($history_detail=false, array $parameters = array()){
		$history = new \stdClass;
		$created_at = (new \DateTime($this->created_at))->getTimestamp();
		$history->$created_at = new \stdClass;
		$history->$created_at->{'0'} = new \stdClass;
		//Because some models doesn't have creacted_by column (like the companies)
		$created_by = null;
		if(isset($this->created_by)){
			$created_by = $this->created_by;
			$parameters['un'] = Users::find($created_by)->username;
		}
		$history->$created_at->{'0'}->by = $created_by;
		$history->$created_at->{'0'}->att = 'created_at';
		if($history_detail){
			$history->$created_at->{'0'}->old = null;
			$history->$created_at->{'0'}->new = null;
		}
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
		$parameters['un'] = Users::getUser()->username;
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

	//When save, it helps to keep track of history
	public function save(array $options = array()){
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

	public function toJson($detail=false, $options = 0){
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
	protected function getUserPivotValue($user_id, $column){
		$column = strtolower($column);
		if($users = $this->users()){
			if($user = $users->find($user_id)){
				if(isset($user->$column)){
					return $user->$column;
				}
			}
		}
		return false;
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
	//public function setUserPivotValue($user_id, $column, $value=0, $save=true){
	protected function setUserPivotValue($user_id, $column, $value=0, $save=true){
		$app = self::getApp();
		$column = strtolower($column);
		$return = false;
		$users = $this->users();
		if($users === false || !method_exists($users,'updateExistingPivot') || !method_exists($users,'attach')){ return false; } //If the pivot doesn't exist, we exit
		if($value!==1){ $value = 0; }
		$value_old = $this->getUserPivotValue($user_id, $column);
		if($value_old !== false){
			if($value_old != $value){
				//Modify a line
				$users->updateExistingPivot($user_id, array($column => $value));
				if($save){
					$archive_title = $this->getPivotArchiveTitle($column, $value);
					$this->setHistory($archive_title, $value, $value_old, array('cun' => Users::find($user_id)->username));
					$this->touch();
				}
				$return = true;
			}
		} else {
			//Create a new line
			$users->attach($user_id, array($column => $value));
			if($save){
				$archive_title = $this->getPivotArchiveTitle($column, $value);
				$this->setHistory($archive_title, $value, $value_old, array('cun' => Users::find($user_id)->username));
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