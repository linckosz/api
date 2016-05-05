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
		'deleted_at',
		'updated_by',
		'deleted_by',
	);

	// CUSTOMIZATION //

	protected $show_field = 'name';

	protected $search_fields = array(
		'name',
	);

	protected $archive = array(
		'created_at' => 701, //[{un|ucfirst}] created a new role
		'_' => 702,//[{un|ucfirst}] modified a role
		'_access_0' => 796, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a role
		'_access_1' => 797, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a role
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

	public function scopegetItems($query, $list=array(), $get=false){
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			->where('roles.parent_id', $app->lincko->data['workspace_id'])
			->orWhere('shared', 1);
		});
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

	public function delete(){
		if(isset($this->shared) && !empty($this->shared)){
			$this::errorMsg('Cannot delete a shared role');
			$this->checkPermissionAllow(4);
			return false;
		}
		return parent::delete();
	}

	public function setUserPivotValue($users_id, $column, $value=0, $history=true){
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
			$json->render();
			return false;
		}
		if($new){
			$this->parent_id = intval($app->lincko->data['workspace_id']);
		}
		$this->shared = 0;
		$this->perm_grant = 0;
		$this->perm_workspaces = 0;
		$return = parent::save($options);
		self::setForceReset(true);
		return $return;
	}

}
