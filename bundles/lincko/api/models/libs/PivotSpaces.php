<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Spaces;

class PivotSpaces extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'spaces_x';
	protected $morphClass = 'spaces_x';

	protected $dates = array();

	protected static $permission_sheet = array(
		0, //[R] owner
		0, //[R] max allow || super
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

////////////////////////////////////////////

}
