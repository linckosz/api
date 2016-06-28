<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;

class Roles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'roles';
	protected $morphClass = 'roles';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $hidden = array(
		'updated_by',
		'deleted_by',
	);

	// CUSTOMIZATION //

	protected $show_field = 'name';

	protected $search_fields = array(
		'name',
	);

	protected $name_code = 700;

	protected $archive = array(
		'created_at' => 701, //[{un|ucfirst}] created a new role
		'_' => 702,//[{un|ucfirst}] modified a role
		//'pivot_access_0' => 796, /* UNSUSED */ //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a role
		//'pivot_access_1' => 797, /* UNSUSED */ //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a role
		'_restore' => 798,//[{un|ucfirst}] restored a role
		'_delete' => 799,//[{un|ucfirst}] deleted a role
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'parent_id' => '\\bundles\\lincko\\api\\models\\data\\Workspaces',
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
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Give access to all, will be delete later by hierarchy
	public static function filterPivotAccessList(array $list, $suffix='_id'){
		return array();
	}

	//This is used because by default not all IDs are stored in pivot table
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array()){
		$default = array(
			'access' => 1, //Default is accessible
		);
		foreach ($uid_list as $uid) {
			if(!isset($result[$uid])){ $result[$uid] = array(); }
			foreach ($list as $value) {
				if(!isset($result[$uid][$value])){
					$result[$uid][$value] = (array) $default;
				}
			}
		}
		return $result;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			->where('roles.parent_id', $app->lincko->data['workspace_id'])
			->orWhere('shared', 1);
		});
		if(self::$with_trash_global){
			$query = $query->withTrashed();
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

	protected function pivots_save(array $parameters = array()){
		//We don't set roles since it will always be access 1, but we allow deletion
		return false;
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		if(isset($this->shared) && $this->shared==1){
			//We disallow any modification of shared Roles
			$msg = $app->trans->getBRUT('api', 17, 5)."\n".$app->trans->getBRUT('api', 0, 5); //Role update failed. You are not allowed to edit the server data.
			\libs\Watch::php($msg, 'Missing arguments', __FILE__, true);
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

}
