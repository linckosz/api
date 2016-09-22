<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotSpaces;
use \bundles\lincko\api\models\data\Projects;

class Spaces extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'spaces';
	protected $morphClass = 'spaces';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'deleted_at',
		'name',
		'color',
		'icon',
		'tasks',
		'notes',
		'files',
		'chats',
		'_users',
		'_tasks',
		'_notes',
		'_files',
		'_chats',
		'_parent',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected $show_field = 'name';

	protected $search_fields = array(
		'name',
	);

	protected $name_code = 1000;

	protected $archive = array(
		'created_at' => 1001, //[{un}] created a new space
		'_' => 1002,//[{un}] modified a space
		'title' => 1003,//[{un}] changed a space name
		'parent_id' => 1005, //[{un}] moved a space to the project "[{pj|ucfirst}]"
		'pivot_access_0' => 1096, //[{un}] blocked [{[{cun}]}]'s access to a space
		'pivot_access_1' => 1097, //[{un}] authorized [{[{cun}]}]'s access to a space
		'_restore' => 1098,//[{un}] restored a space
		'_delete' => 1099,//[{un}] deleted a space
	);

	protected static $parent_list = 'projects';

	protected $model_boolean = array(
		'tasks',
		'notes',
		'files',
		'chats',
		'hide',
	);

	protected static $allow_single = false;
	protected static $allow_role = false;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		1, //[RCUD] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = false;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_spaces', array('hide')),
		'tasks' => array('spaces_x', array('created_at', 'exit_at')),
		'notes' => array('spaces_x', array('created_at', 'exit_at')),
		'files' => array('spaces_x', array('created_at', 'exit_at')),
		'chats' => array('spaces_x', array('created_at', 'exit_at')),
	);

	//Many(Spaces) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//Many(Spaces) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_spaces', 'spaces_id', 'users_id')->withPivot('access', 'hide');
	}

	//Many(Spaces) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'spaces_x', 'spaces_id', 'parent_id')->where('spaces_x.parent_type', 'tasks')->withPivot('access', 'created_at', 'exit_at');
	}

	//Many(Spaces) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'spaces_x', 'spaces_id', 'parent_id')->where('spaces_x.parent_type', 'notes')->withPivot('access', 'created_at', 'exit_at');
	}

	//Many(Spaces) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'spaces_x', 'spaces_id', 'parent_id')->where('spaces_x.parent_type', 'files')->withPivot('access', 'created_at', 'exit_at');
	}

	//Many(Spaces) to Many(Chats)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'spaces_x', 'spaces_id', 'parent_id')->where('spaces_x.parent_type', 'chats')->withPivot('access', 'created_at', 'exit_at');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->name) && !self::validChar($form->name, true))
			|| (isset($form->tasks) && !self::validBoolean($form->tasks, true))
			|| (isset($form->notes) && !self::validBoolean($form->notes, true))
			|| (isset($form->files) && !self::validBoolean($form->files, true))
			|| (isset($form->chats) && !self::validBoolean($form->chats, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	protected function setPivotExtra($type, $column, $value){
		$pivot_array = array(
			$column => $value,
		);
		if($type=='tasks' || $type=='notes' || $type=='files' || $type=='chats'){
			$pivot_array['parent_type'] = $type;
			$pivot_array['created_at'] = $this->freshTimestamp();
			if($column=='access'){
				if($value){
					$pivot_array['exit_at'] = null;
				} else {
					$pivot_array['exit_at'] = $pivot_array['created_at'];
				}
			}
		}
		return $pivot_array;
	}

	//This is used because by default not all IDs are stored in pivot table
	public static function filterPivotAccessListDefault(array $list, array $uid_list, array $result=array(), $default = array('access' => 1)){
		$default = array(
			'access' => 1,
			'hide' => 0,
		);
		return parent::filterPivotAccessListDefault($list, $uid_list, $result, $default);
	}

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) use ($list) {
				if(isset($list['projects']) && count($list['projects'])>0){
					$query = $query
					->whereIn('spaces.parent_id', $list['projects']);
				} else {
					$query = $query
					->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include 'projects'
				}
			});
		})
		->whereHas("users", function($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 0);
		}, '<', 1);
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

	public static function blockItems(){
		$pivot = array();
		if($user = Users::getUser()){
			$spaces = $user->spaces()->where('access', 1)->where('hide', 1)->get(['id']);
			$list_id = array();
			foreach ($spaces as $model) {
				$list_id[$model->id] = $model->id;
			}
			if(count($list_id)>0){
				$pivot = PivotSpaces::whereIn('spaces_id', $list_id)->where('access', 1)->get(['parent_type', 'parent_id'])->toArray();
			}
			//Include the space itself
			foreach ($list_id as $spaces_id) {
				$pivot[] = array(
					'parent_type' => 'spaces',
					'parent_id' => $spaces_id,
				);
			}
		}
		return $pivot;
	}

}
