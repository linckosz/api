<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;

class PivotUsersRoles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users_x_roles_x';
	protected $morphClass = 'users_x_roles_x';

	protected $primaryKey = 'id';

	protected $visible = array();

	protected static $permission_sheet = array(
		0, //[R] owner
		0, //[R] max allow || super
	);

	protected static $parent_list = array('users', 'comments', 'chats', 'workspaces', 'projects', 'tasks', 'notes', 'files');
	
////////////////////////////////////////////

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array()){
		return true;
	}

	//We do not attach
	public function setUserPivotValue($users_id, $column, $value=0, $history=true){
		return true;
	}

////////////////////////////////////////////

	public function scopesameWorkspace($query){
		$app = self::getApp();
		return $query->where('users_x_roles_x.parent_type', 'workspaces')->where('users_x_roles_x.parent_id', $app->lincko->data['workspace_id'])->where('access', 1);
	}

	public static function getWorkspaceRoles(){
		$app = self::getApp();
		if($workspace = Workspaces::find($app->lincko->data['workspace_id'])){
			$work_users = $workspace->users()->get();
			$users_list = array();
			foreach ($work_users as $work_user) {
				$users_list[] = $work_user->getKey();
			}
			if(!in_array($app->lincko->data['uid'], $users_list)){
				$users_list[] = $app->lincko->data['uid'];
			}
			return self::whereIn('users_x_roles_x.users_id', $users_list)->get();
		}
		return null;
	}

}
