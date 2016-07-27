<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;

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
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	protected function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		//Skip ModelLincko save procedure
		$return = Model::save($options);
		return $return;
	}

	//It tells which users has to be informed of the modification by Global Timestamp check, it help to sve calculation time
	public static function informUsers($users_tables, $time=false){
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
		return true;
	}

	public function checkAccess($show_msg=true){
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){
		return true;
	}

}
