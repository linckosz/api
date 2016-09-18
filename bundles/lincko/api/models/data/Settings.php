<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Settings extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'settings';
	protected $morphClass = 'settings';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'setup',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected static $permission_sheet = array(
		2, //[RCUD] owner
		2, //[RCUD] max allow || super
	);

	protected static $has_perm = false;
	
////////////////////////////////////////////

	//Many(comments) to One(Users)
	public function users(){
		return $this->hasOne('\\bundles\\lincko\\api\\models\\data\\Users', 'id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->setup) && !self::validText($form->setup, true))
		){
			return false;
		}
		return true;
	}

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

	public function scopegetItems($query, $list=array(), $get=false){
		$app = self::getApp();
		$query = $query
		->where('id', $app->lincko->data['uid']);
		if(self::$with_trash_global){
			$query = $query->withTrashed();
		}
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

	public function checkAccess($show_msg=true){
		$app = self::getApp();
		if(!isset($this->id) || $this->id == $app->lincko->data['uid']){
			return true;
		}
		return false;
	}

	public function checkPermissionAllow($level, $msg=false){
		$level = $this->formatLevel($level);
		if(!isset($this->id) && $level==1){ //Allow create for new
			return true;
		} else if(isset($this->id) && $level==2){ //Allow edit for existing
			return true;
		}
		return true;
	}

}
