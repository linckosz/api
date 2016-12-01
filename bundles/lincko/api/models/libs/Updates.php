<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use Illuminate\Database\Capsule\Manager as Capsule;

class Updates extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'updates';
	protected $morphClass = 'updates';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array();

	protected $accessibility = true;

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	protected static $db = false;
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//Because deleted_at does not exist
	public static function find($id, $columns = ['*']){
		return parent::withTrashed()->find($id, $columns);
	}

	protected static function getDB(){
		if(!self::$db){
			$app = self::getApp();
			self::$db = Capsule::connection($app->lincko->data['database_data']);
		}
		return self::$db;
	}

	protected static function quote($text){
		$db = self::getDB();
		return ''.$db->getPdo()->quote($text);
	}

	protected static function quote_field($text){
		$db = self::getDB();
		return ''.$db->getPdo()->quote($text);
	}

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	public function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		//Skip ModelLincko save procedure
		$return = Model::save($options);
		return $return;
	}

	//It tells which users has to be informed of the modification by Global Timestamp check, it help to save calculation time
	public static function informUsers($users_tables, $time=false){
		
		if(!$time){
			$time = date("Y-m-d H:i:s");
		} else {
			$time = $time->format('Y-m-d H:i:s');
		}

		$temp = array();
		foreach ($users_tables as $users_id => $list) {
			foreach ($list as $table_name => $value) {
				if(!isset($temp[$table_name])){ $temp[$table_name] = array(); }
				$temp[$table_name][$users_id] = $users_id;
			}
		}
			
		//This method is less secure because it uses directly MYSQL commands, but can save one code line (get)
		$db = static::getDB();
		foreach ($temp as $table_name => $users) {
			$values = '';
			foreach ($users as $users_id) {
				if(!empty($values)){
					$values .= ', ';
				}
				//These value are the minimal request for a row to be valid
				$values .= '('.intval($users_id).', \''.$time.'\')';
			}
			$sql = 'INSERT INTO `updates` (`id`, `'.$table_name.'`) VALUES '.$values.' ON DUPLICATE KEY UPDATE `'.$table_name.'`=\''.$time.'\';';
			$db->insert( $db->raw($sql));
		}
		

		/*
		//Old method but too heavy because it calls each user
		if(!$time){
			$time = (new self)->freshTimestamp();
		}
		$columns = array_flip(self::getColumns());
		foreach ($users_tables as $users_id => $list) {
			$updates = Updates::find($users_id);
			if(!$updates){
				$updates = new Updates;
				$updates->id = $users_id;
			}
			foreach ($list as $table_name => $value) {
				if(isset($columns[$table_name])){
					$updates->$table_name = $time;
				}
			}
			$updates->save();
		}
		*/
		return true;
	}

	public function checkAccess($show_msg=true){
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){
		return true;
	}

}
