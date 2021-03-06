<?php
// Category 6

namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\Inform;

class Settings extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'settings';
	protected $morphClass = 'settings';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'setup',
		'onboarding',
	);

	// CUSTOMIZATION //

	protected static $hide_extra = array(
		'temp_id',
		'setup',
		'onboarding',
	);

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
			|| (isset($form->onboarding) && !self::validText($form->onboarding, true))
		){
			return false;
		}
		return true;
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
		return false;
	}

	//We do not attach
	public function pivots_save(array $parameters = array(), $force_access=false){
		return true;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->where('id', $app->lincko->data['uid']);
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
		}
		$query = $query->withTrashed(); //Because no deleted_at
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
		$app = ModelLincko::getApp();
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

	public static function getMySettings(){
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		$settings = Settings::find($uid);
		if(!$settings){
			$settings = new Settings;
			$settings->id = $uid;
		}
		return $settings;
	}

}
