<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;

class PivotUsersRoles extends Model {

	protected $connection = 'data';

	protected $table = 'users_x_roles_x';
	protected $morphClass = 'users_x_roles_x';

	public $timestamps = false;

	protected static $parent_list = array('users', 'comments', 'chats', 'workspaces', 'projects', 'tasks', 'notes', 'files');
	
////////////////////////////////////////////

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

////////////////////////////////////////////

	public function __construct(array $attributes = array()){
		$app = ModelLincko::getApp();
		$this->connection = $app->lincko->data['database_data'];
		parent::__construct($attributes);
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//Only delete the access at false
	public static function getRoles($tree_id){
		$result = new \stdClass;
		if(isset($tree_id['users'])){
			$users = $tree_id['users'];
			$result = self::whereIn('users_id', $users)->where('access', 1);
			foreach ($tree_id as $type => $list_id) {
				if(in_array($type, static::$parent_list)){
					$result = $result
					->orWhere(function ($query) use ($type, $list_id) {
						$query
						->where('parent_type', $type)
						->whereIn('parent_id', $list_id);
					});
				}
			}
			$result = $result->get();
		}
		return $result;
	}

	//Only delete the access at false
	public static function getAllRoles($tree_id, $users){
		$result = self::whereIn('users_id', $users)->where('access', 1);
		foreach ($tree_id as $type => $list_id) {
			if(in_array($type, static::$parent_list)){
				$result = $result
				->orWhere(function ($query) use ($type, $list_id) {
					$query
					->where('parent_type', $type)
					->whereIn('parent_id', $list_id);
				});
			}
		}
		$result = $result->get();
		return $result;
	}

	public function scopesameWorkspace($query){
		$app = ModelLincko::getApp();
		return $query->where('users_x_roles_x.parent_type', 'workspaces')->where('users_x_roles_x.parent_id', $app->lincko->data['workspace_id'])->where('access', 1);
	}

	public static function getWorkspaceRoles(){
		$app = ModelLincko::getApp();
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

	//Be careful, a user can lock himself by this command, and noone else can unlock him if none previously with higher role
	public static function setMyRole($item, $roles_id=null, $single=null, $access=1){
		$app = ModelLincko::getApp();
		$role = self::Where('users_id', $app->lincko->data['uid'])->where('parent_type', $item->getTable())->where('parent_id', $item->id)->first();
		if(!$role){
			$role = new self;
			$role->users_id = $app->lincko->data['uid'];
			$role->parent_type = $item->getTable();
			$role->parent_id = $item->id;
		}

		if($access!=1){
			$access = 0;
		}
		if(!is_numeric($roles_id) || $roles_id<1){
			$roles_id = null;
		}
		if(!is_numeric($single) || $single<0 || $single>3){
			$single = null;
		}
		$role->access = $access;
		$role->roles_id = $roles_id;
		$role->single = $single;
		return $role->save();
	}

}
