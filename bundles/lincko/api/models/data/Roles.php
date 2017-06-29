<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \libs\Json;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\Inform;

class Roles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'roles';
	protected $morphClass = 'roles';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $hidden = array(
		'updated_by',
		'deleted_by',
		'parent_id',
		'parent_type',
		'extra',
		'accessibility',
	);

	// CUSTOMIZATION //

	protected static $prefix_fields = array(
		'name' => '+name',
	);

	protected static $hide_extra = array(
		'temp_id',
		'name',
	);

	protected $name_code = 700;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => array(true, 701), //[{un}] created a new role
		'_' => array(true, 702), //[{un}] modified a role
			'pivot_users_access_0' => array(false, 796), /* UNSUSED */ //[{un}] blocked [{cun}]'s access to a role
			'pivot_users_access_1' => array(false, 797), /* UNSUSED */ //[{un}] authorized [{cun}]'s access to a role
		'_restore' => array(true, 798), //[{un}] restored a role
		'_delete' => array(true, 799), //[{un}] deleted a role
	);

	protected static $parent_list = 'workspaces';

	protected $model_integer = array(
		'perm_all',
		'perm_workspaces',
		'perm_projects',
		'perm_tasks',
		'perm_notes',
		'perm_files',
		'perm_chats',
		'perm_comments',
		'perm_spaces',
		'perm_namecards',
		'parent_id',
		'roles_id',
		'single',
	);

	protected $model_boolean = array(
		'shared',
		'perm_grant',
	);

	protected static $permission_sheet = array(
		0, //[R] owner
		3, //[RCUD] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = true;

////////////////////////////////////////////

	//Many(Roles) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_roles_x', 'roles_id', 'users_id')->withPivot('access', 'single', 'roles_id', 'parent_id', 'parent_type');
	}

	//Many(Roles) to One(Workspaces)
	public function workspaces(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'parent_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->name) && !self::validChar($form->name, true))
			|| (isset($form->perm_grant) && !self::validBoolean($form->perm_grant, true))
			|| (isset($form->perm_all) && !self::validRCUD($form->perm_all, true))
			|| (isset($form->perm_workspaces) && !self::validRCUD($form->perm_workspaces, true))
			|| (isset($form->perm_projects) && !self::validRCUD($form->perm_projects, true))
			|| (isset($form->perm_tasks) && !self::validRCUD($form->perm_tasks, true))
			|| (isset($form->perm_notes) && !self::validRCUD($form->perm_notes, true))
			|| (isset($form->perm_files) && !self::validRCUD($form->perm_files, true))
			|| (isset($form->perm_chats) && !self::validRCUD($form->perm_chats, true))
			|| (isset($form->perm_comments) && !self::validRCUD($form->perm_comments, true))
			|| (isset($form->perm_spaces) && !self::validRCUD($form->perm_spaces, true))
			|| (isset($form->perm_namecards) && !self::validRCUD($form->perm_namecards, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Give access to all, will be delete later by hierarchy
	public static function filterPivotAccessList(array $list, $all=false){
		return array();
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
				$app = ModelLincko::getApp();
				$query
				->where('roles.parent_id', $app->lincko->data['workspace_id'])
				->orWhere('shared', 1);
			});
			if(self::$with_trash_global){
				$query = $query->withTrashed();
			}
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
		}
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
				if($result[$key]->perm_workspaces<1){
					$result[$key]->perm_workspaces = 1; //We always allow at least workspace creation (if paid)
				}
				if($result[$key]->perm_comments<1){
					$result[$key]->perm_comments = 1; //We always allow at least comments creation
				}
			}
			return $result;
		} else {
			return $query;
		}
	}

	public function delete(){
		if(isset($this->shared) && !empty($this->shared)){
			$this::errorMsg('Cannot delete a shared role');
			$this->checkPermissionAllow(4);
			return false;
		}
		return parent::delete();
	}

	public function pivots_save(array $parameters = array(), $force_access=false){
		//We don't set roles since it will always be access 1, but we allow deletion
		return false;
	}

	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		$new = !isset($this->id);
		if(isset($this->shared) && $this->shared==1){
			//We disallow any modification of shared Roles
			$msg = $app->trans->getBRUT('api', 17, 5)."\n".$app->trans->getBRUT('api', 0, 5); //Role update failed. You are not allowed to edit the server data.
			\libs\Watch::php($msg, 'Missing arguments', __FILE__, __LINE__, true);
			$json = new Json($msg, true, 406);
			$json->render(406);
			return false;
		}
		if($new){
			$this->parent_id = intval($app->lincko->data['workspace_id']);
		}
		$this->shared = 0;
		if(!isset($this->perm_workspaces) || $this->perm_workspaces<1){
			$this->perm_workspaces = 1; //We always allow at least workspace creation (if paid)
		}
		if(!isset($this->perm_comments) || $this->perm_comments<1){
			$this->perm_comments = 1; //We always allow at least comments creation
		}
		$return = parent::save($options);
		self::setForceReset(true);
		return $return;
	}

	public function checkAccess($show_msg=true){
		if(isset($this->shared) && $this->shared){ //Because shared ones do not have _perm setup
			$this->accessibility = (bool) true;
		}
		return parent::checkAccess($show_msg);
	}

}
