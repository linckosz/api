<?php
// Category 5

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Users;

class Namecards extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'namecards';
	protected $morphClass = 'namecards';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'updated_at',
		'username',
		'email',
		'firstname',
		'lastname',
		'address',
		'phone',
		'business',
		'additional',
		'linkedin',
		'workspaces_id',
		'search',
	);

	// CUSTOMIZATION //

	protected static $prefix_fields = array(
		'username' => '-username',
		'firstname' => '-firstname',
		'lastname' => '-lastname',
		'email' => '-email',
		'address' => '-address',
		'phone' => '-phone',
		'business' => '-business',
		'additional' => '-additional',
	);

	protected static $hide_extra = array(
		'temp_id',
		'username',
		'email',
		'firstname',
		'lastname',
		'address',
		'phone',
		'business',
		'additional',
		'linkedin',
		'workspaces_id',
		'search',
	);

	protected $name_code = 1100;

	protected $save_history = true;

	protected static $archive = array(
			'created_at' => array(false, 1101), //[{un}] modified his profile
		'_' => array(true, 1102), //[{un}] modified his profile
			'username' => array(false, 1102), //[{un}] modified his profile
			'email' => array(false, 1102), //[{un}] modified his profile
			'firstname' => array(false, 1102), //[{un}] modified his profile
			'lastname' => array(false, 1102), //[{un}] modified his profile
			'address' => array(false, 1102), //[{un}] modified his profile
			'phone' => array(false, 1102), //[{un}] modified his profile
			'business' => array(false, 1102), //[{un}] modified his profile
			'additional' => array(false, 1102), //[{un}] modified his profile
			'linkedin' => array(false, 1102), //[{un}] modified his profile
		'_restore' => array(true, 1198), //[{un}] restored his profile
		'_delete' => array(true, 1199), //[{un}] deleted his profile
	);

	protected static $history_xdiff = array('business', 'additional');

	protected static $parent_list = 'users';

	protected static $allow_role = true;

	protected static $permission_sheet = array(
		0, //[RC] owner
		2, //[RCUD] max allow || super
	);

	protected static $has_perm = false;

	protected $model_integer = array(
		'workspaces_id',
	);

////////////////////////////////////////////

	//Many(Namecards) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'parent_id');
	}

	//Many(Namecards) to One(Workspaces)
	public function workspaces(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'workspaces_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->username) && !self::validChar($form->username, true) && !self::validTextNotEmpty($form->username, true))
			|| (isset($form->email) && !self::validEmail($form->email, true))
			|| (isset($form->firstname) && !self::validChar($form->firstname, true))
			|| (isset($form->lastname) && !self::validChar($form->lastname, true))
			|| (isset($form->phone) && !self::validChar($form->phone, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//Insure that we only record 1 personal_private project for each user
	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		if(!isset($this->id)){
			$this->workspaces_id = intval($app->lincko->data['workspace_id']);
		}
		if($app->lincko->data['workspace_id']==0){
			//For shared workspace, cannot update some fields
			if(isset($this->username) && !is_null($this->username)){
				return $this::errorMsg('Update namecard username of shared workspace');
			}
			if(isset($this->firstname) && !is_null($this->firstname)){
				return $this::errorMsg('Update namecard firstname of shared workspace');
			}
			if(isset($this->lastname) && !is_null($this->lastname)){
				return $this::errorMsg('Update namecard lastname of shared workspace');
			}
			if($this->parent_id==$app->lincko->data['uid']){
				$this->forceGiveAccess(2); //Allow editing
			}
		} else if(self::getWorkspaceSuper($app->lincko->data['uid'])){
			$this->forceGiveAccess(2); //Allow editing
		}
		$return = parent::save($options);
		return $return;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		$query = $query
		->whereIn('workspaces_id', [0, $app->lincko->data['workspace_id']])
		->whereIn('namecards.parent_id', $list['users']); //This user list includes only users from contact list or same workspace
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

	//It checks if the user has access to it
	public function checkAccess($show_msg=true){
		$app = ModelLincko::getApp();
		$this->checkUser();
		if(!is_bool($this->accessibility)){
			if(Users::getModel($this->parent_id)){
				$this->accessibility = true;
			}
		}
		return parent::checkAccess($show_msg);
	}

	public function getParentAccess(){
		$app = ModelLincko::getApp();
		if(Users::getModel($this->parent_id)){
			return true;
		}
		return parent::getParentAccess();
	}

}
