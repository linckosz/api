<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;
use \libs\Email;

class Invitation extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'invitation';
	protected $morphClass = 'invitation';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'code',
	);

	protected $accessibility = true;

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

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
	public function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		$app = self::getApp();
		if(!isset($this->id)){ //set code for new
			$this->code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
			while( self::where('code', '=', $this->code)->first() ){
				$this->code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
			}
			$this->created_by = $app->lincko->data['uid'];
			$this->used = false;
		} else {
			$this->guest = $app->lincko->data['uid'];
			$this->used = true;
		}
		$return = Model::save($options);
		return $return;
	}

	public function checkAccess($show_msg=true){
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){
		return true;
	}

}
