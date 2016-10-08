<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Capsule\Manager as Capsule;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\Data;

class Models extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'models';
	protected $morphClass = 'models';

	public $timestamps = false;

	protected $visible = array();

	protected $accessibility = true; //Always allow History creation

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	protected static $db = false;
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	public function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		return false;
	}

	public function checkAccess($show_msg=true){
		return false;
	}

	public function checkPermissionAllow($level, $msg=false){
		return false;
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
		$db = Models::getDB();
		return ''.$db->getPdo()->quote($text);
	}

	public static function plus($type, $id, $users_id = array()){
		$app = self::getApp();
		if(count($users_id)>0 && array_key_exists($type, Data::getModels())){
			$id = intval($id); //make sure $id is an integer
			$type = self::quote($type);
			$values = '';
			foreach ($users_id as $uid) {
				if(!empty($values)){
					$values .= ', ';
				}
				$values .= "($uid, $type, ';$id;')";
			}
			$sql = "INSERT INTO `models` (`users_id`, `type`, `list`) VALUES $values ON DUPLICATE KEY UPDATE `list`=IF(`list` NOT LIKE '%;$id;%', CONCAT(`list`, ';$id;'), `list`);";
			//\libs\Watch::php( $sql, '$plus', __FILE__, false, false, true);
			$db = static::getDB();
			$result = $db->insert( $db->raw($sql));
			return $result;
		}
		return false;
	}

	public static function less($type, $id, $users_id = array()){
		$app = self::getApp();
		if(count($users_id)>0 && array_key_exists($type, Data::getModels())){
			$id = intval($id); //make sure $id is an integer
			$type = self::quote($type);
			$values = '';
			foreach ($users_id as $uid) {
				if(!empty($values)){
					$values .= ', ';
				}
				$values .= "($uid, $type, ';$id;')";
			}
			$sql = "INSERT INTO `models` (`users_id`, `type`, `list`) VALUES $values ON DUPLICATE KEY UPDATE `list`=IF(`list` LIKE '%;$id;%', REPLACE(`list`, ';$id;', ''), `list`);";
			//\libs\Watch::php( $sql, '$less', __FILE__, false, false, true);
			$db = static::getDB();
			$result = $db->insert( $db->raw($sql));
			return $result;
		}
		return false;
	}

}