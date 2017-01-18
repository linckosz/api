<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use Illuminate\Database\Capsule\Manager as Capsule;

class Updates extends Model {

	protected $connection = 'data';

	protected $table = 'updates';
	protected $morphClass = 'updates';

	public $timestamps = false;

	protected static $db = false;
	
////////////////////////////////////////////

	public function __construct(array $attributes = array()){
		$app = ModelLincko::getApp();
		$this->connection = $app->lincko->data['database_data'];
		parent::__construct($attributes);
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	protected static function getDB(){
		if(!self::$db){
			$app = ModelLincko::getApp();
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

	public function save(array $options = array()){
		$return = Model::save($options);
		usleep(30000); //30ms
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
			usleep(30000); //30ms
		}

		return true;
	}

}
