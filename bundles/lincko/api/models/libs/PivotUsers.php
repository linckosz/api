<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class PivotUsers extends Model {

	protected $connection = 'data';

	protected $table = 'users_x_';
	protected $morphClass = 'users_x_';

	public $timestamps = false;
	
////////////////////////////////////////////

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

	public function __construct(array $attributes = array()){
		$app = ModelLincko::getApp();
		$this->connection = $app->lincko->data['database_data'];
		$model = new Users;
		foreach ($attributes as $key => $value) {
			if($key=='table'){
				$this->setTable($value);
			}
		}
		parent::__construct(array());
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not attach
	public function setTable($table){
		$model = new Users;
		if($model->tableExists($table)){
			$this->table = $this->morphClass = $this->table.$table; //Pivot relation
		} else if($model->tableExists($table.'_x')){
			$this->table = $this->morphClass = $this->table.$table.'_x'; //Morph relation
		}
		return $this->table;
	}

	//We do not attach
	public function modelSave($table){
		$this->setTable($table);
		return Model::save();
	}

////////////////////////////////////////////

}
