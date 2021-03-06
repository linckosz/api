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

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	//Keep a record of the email retreive from invitation code (column "email")
	protected static $email_code = null;
	
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

	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		if(!isset($this->id)){ //set code for new
			$this->code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
			while( self::withTrashed()->where('code', $this->code)->first() ){
				$this->code = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
			}
			$this->created_by = $app->lincko->data['uid'];
			$this->used = false;
		}
		$return = Model::save($options);
		usleep(rand(30000, 35000)); //30ms
		return $return;
	}

	public function checkAccess($show_msg=true){
		return true;
	}

	public function checkPermissionAllow($level, $msg=false){
		return true;
	}

}
