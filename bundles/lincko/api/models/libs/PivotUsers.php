<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class PivotUsers extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users_x_';
	protected $morphClass = 'users_x_';

	protected $dates = array();

	protected static $permission_sheet = array(
		0, //[R] owner
		0, //[R] max allow || super
	);
	
////////////////////////////////////////////

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

	public function __construct(array $attributes = array()){
		$app = self::getApp();
		$this->connection = $app->lincko->data['database_data'];
		$model = new Users;
		foreach ($attributes as $key => $value) {
			if($key=='table'){
				if($model->tableExists($this->table.$value)){
					$this->table = $this->morphClass = $this->table.$value; //Pivot relation
				} else if($this->tableExists($this->table.$value.'_x')){
					$this->table = $this->morphClass = $this->table.$value.'_x'; //Morph relation
				}
			}
		}
		parent::__construct(array());
	}

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

	public function getNoticed(){
		if(isset($this->noticed)){
			return $this->noticed;
		}
		return false;
	}

}
