<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotUsers;

class Chats extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'chats';
	protected $morphClass = 'chats';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'title',
		'_parent',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
	);

	protected $name_code = 100;

	protected $archive = array(
		'created_at' => 101, //[{un|ucfirst}] created a new chat group
		'_' => 102,//[{un|ucfirst}] modified a chat group
		'title' => 103,//[{un|ucfirst}] changed a chat group title
		'pivot_access_0' => 196, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a chat group
		'pivot_access_1' => 197, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a chat group
		'_restore' => 198,//[{un|ucfirst}] restored a chat group
		'_delete' => 199,//[{un|ucfirst}] deleted a chat group
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $relations_keys = array(
		'users'
	);

	protected static $parent_list = array(null, 'workspaces', 'projects');

	protected static $permission_sheet = array(
		2, //[RCU] owner
		3, //[RCUD] max allow || super
	);

////////////////////////////////////////////

	//Many(Chats) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_chats', 'chats_id', 'users_id')->withPivot('access');
	}

	//Many(Chats) to Many(Workspaces)
	public function workspaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'chats', 'id', 'parent_id');
	}

	//Many(Chats) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'chats', 'id', 'parent_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_type) && !self::validType($form->parent_type, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Only add access at true
	public static function filterPivotAccessList(array $uid_list, array $list, array $default=array()){
		$result = array();
		$table = (new self)->getTable();
		$attributes = array( 'table' => $table, );
		$pivot = new PivotUsers($attributes);
		if($pivot->tableExists($pivot->getTable())){
			$pivot = $pivot->whereIn('users_id', $uid_list)->whereIn($table.'_id', $list)->withTrashed()->get();
			foreach ($pivot as $key => $value) {
				if($value->access){
					$uid = (integer) $value->users_id;
					$id = (integer) $value->{$table.'_id'};
					if(!isset($result[$uid])){ $result[$uid] = array(); }
					//$result[$uid][$id] = new \stdClass;
					$result[$uid][$id] = (array) $value->attributes;
				}
			}
		}
		return $result;
	}

	public function delete(){
		//We cannot delete a chat out of the scope of a workspace
		if($this->getParent()){
			return parent::delete();
		}
		return false;
	}

	public function restore(){
		//We cannot delete a chat out of the scope of a workspace
		if($this->getParent()){
			return parent::restore();
		}
		return false;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$query = $query
		->where(function ($query) use ($list) {
			$query
			->where(function ($query) {
				$app = self::getApp();
				$query
				->where('chats.parent_type', '')
				->orWhere('chats.parent_type', null);
			})
			->orWhere(function ($query) use ($list) {
				foreach ($list as $table_name => $list_id) {
					if(!empty($table_name) && in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
						$this->var['parent_type'] = $table_name;
						$this->var['parent_id_array'] = $list_id;
						$query = $query
						->orWhere(function ($query) {
							$query
							->where('chats.parent_type', $this->var['parent_type'])
							->whereIn('chats.parent_id', $this->var['parent_id_array']);
						});
					}
				}
			});
		})
		->whereHas('users', function ($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 1);
		});
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

}
