<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;
use \bundles\lincko\api\models\libs\ModelLincko;

class Tree extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'tree';
	protected $morphClass = 'tree';

	public $timestamps = false;

	protected $visible = array();

	protected $dates = array();

	protected $accessibility = true;

	protected static $unlocked = false;

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	protected static $db = false;

	//When save, these are field that must depend on item itself
	protected static $lock_keys = array(
		'users_id',
		'item_type',
		'item_id',
		'parent_type',
		'parent_id',
		'updated_at',
		'deleted_at',
	);

	//We exclude comments to avoid to big table
	protected static $accept_fields = array(
		'workspaces' => 1,
		'users' => 2,
		'chats' => 3,
		'projects' => 4,
		'tasks' => 5,
		'notes' => 6,
		'files' => 7,
	);
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//Because deleted_at does not exist
	public static function find($id, $columns = ['*']){
		return parent::withTrashed()->find($id, $columns);
	}

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	public function pivots_save(array $parameters = array()){
		return true;
	}

	//Disable the save method, must go through save_tree()
	public function save(array $options = array()){
		return false;
	}

	public function checkAccess($show_msg=true){
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){
		return true;
	}

////////////////////////////////////////////

	protected static function getDB(){
		if(!self::$db){
			$app = self::getApp();
			self::$db = Capsule::connection($app->lincko->data['database_data']);
		}
		return self::$db;
	}

	protected static function quote($text){
		$db = Tree::getDB();
		return ''.$db->getPdo()->quote($text);
	}

	protected static function quote_field($text){
		$db = Tree::getDB();
		return ''.$db->getPdo()->quote($text);
	}

	public static function TreeUpdateOrCreate(ModelLincko $item, array $list_uid=array(), $include_all_users=false, array $fields=array()){
		if(!self::$unlocked){
			if(!isset($item->id)){ //Item must exist
				return false;
			} else if(!$item->checkPermissionAllow('edit')){ //Must have access and being able to edit
				return false;
			}
		}

		$item_type = self::quote($item->getTable());
		$item_id = intval($item->id);
		$parent = $item->setParentAttributes();
		$parent_type = self::quote($parent[0]);
		$parent_id = intval($parent[1]);
		$updated_at = self::quote($item->updated_at);
		$deleted_at = 'null';
		if(!is_null($item->deleted_at)){
			$deleted_at = self::quote($item->deleted_at);
		}

		$list_users = array();
		foreach ($list_uid as $value) {
			$users_id = intval($value);
			$list_users[$users_id] = $users_id;
		}

		$list_users_db = array();
		$users_diff = array();
		$only_update = false;
		if($include_all_users){
			//Scan Tree to obtain all existing users_id
			if($users = self::withTrashed()->where('item_type', $item->getTable())->where('item_id', $item_id)->get(['users_id'])){
				foreach ($users as $value) {
					$users_id = intval($value->users_id);
					$list_users_db[$users_id] = $users_id;
				}
			}
			//Make sure we include at list all user in _perm attribute
			$users_perm = json_decode($item->getPerm(), true);
			if(is_array($users_perm)){
				foreach ($users_perm as $key => $value) {
					$users_id = intval($key);
					$list_users[$users_id] = $users_id;
				}
			}
			$users_diff = array_diff($list_users, $list_users_db);
			if(empty($users_diff)){ //There is no more users than already exists in teh database
				$only_update = true;
			}
			//insure we include all users from database
			foreach ($list_users_db as $value) {
				$list_users[$value] = $value;
			}
		}
		//\libs\Watch::php( $list_users, '$list_users', __FILE__, __LINE__, false, false, true);
		//We do nothing if no user id entered
		if(count($list_users)<=0){
			return false;
		}

		$db = static::getDB();

		//For updated field, make sure we only update some, not all (NOTE: can lose tree consistency)
		$insert_fields = '';
		$insert_values = '';
		$updates = '`updated_at`='.$updated_at;
		foreach ($fields as $column => $value) {
			if(!in_array($column, self::$lock_keys)){
				$insert_fields .= ', `'.addslashes($column).'`';
				$insert_values .= ', '.self::quote($value);
				$updates .= ', `'.addslashes($column).'`='.self::quote($value);
			}
		}

		if($only_update){
			$sql = 'UPDATE `tree` SET '.$updates.' WHERE `item_type`='.$item_type.' AND `item_id`='.$item_id.';';
		} else {
			$values = '';
			foreach ($list_users as $users_id) {
				if(!empty($values)){
					$values .= ', ';
				}
				//These value are the minimal request for a row to be valid
				$values .= '('.intval($users_id).', '.$item_type.', '.$item_id.', '.$parent_type.', '.$parent_id.', '.$updated_at.', '.$deleted_at.''.$insert_values.')';
			}

			$sql = 'INSERT INTO `tree` (`users_id`, `item_type`, `item_id`, `parent_type`, `parent_id`, `updated_at`, `deleted_at`'.$insert_fields.') VALUES '.$values.' ON DUPLICATE KEY UPDATE '.$updates.';';
		}
		//\libs\Watch::php( $sql, '$sql', __FILE__, __LINE__, false, false, true);
		$result = $db->insert( $db->raw($sql));
		usleep(30000); //30ms
		return $result;
	}

	//Only available for development purpose, dangerous
	public static function unlock($unlock=false, $pwd=''){
		if($unlock && $pwd=='eI782Ph0sp'){
			self::$unlocked = true;
		} else {
			self::$unlocked = false;
		}
	}

}
