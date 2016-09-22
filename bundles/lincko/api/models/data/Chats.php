<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Chats extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'chats';
	protected $morphClass = 'chats';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'updated_at',
		'deleted_at',
		'title',
		'single',
		'_parent',
		'_spaces',
		'_perm',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
	);

	protected $name_code = 100;

	protected $archive = array(
		'created_at' => 101, //[{un}] created a new chat group
		'_' => 102,//[{un}] modified a chat group
		'title' => 103,//[{un}] changed a chat group title
		'pivot_access_0' => 196, //[{un}] blocked [{[{cun}]}]'s access to a chat group
		'pivot_access_1' => 197, //[{un}] authorized [{[{cun}]}]'s access to a chat group
		'_restore' => 198,//[{un}] restored a chat group
		'_delete' => 199,//[{un}] deleted a chat group
	);

	protected static $parent_list = array(null, 'workspaces', 'projects');

	protected $model_boolean = array(
		'single',
	);

	protected static $permission_sheet = array(
		2, //[RCU] owner
		3, //[RCUD] max allow || super
	);

	protected static $has_perm = true;

////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'spaces' => array('spaces_x', array('created_at')),
	);

	//Many(Chats) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_chats', 'chats_id', 'users_id')->withPivot('access');
	}

	//Many(Chats) to Many(Workspaces)
	public function workspaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'chats', 'id', 'parent_id');
	}

	//Many(Chats) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'chats', 'id', 'parent_id');
	}

	//Many(Chats) to Many(Spaces)
	public function spaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'chats')->withPivot('access', 'created_at', 'exit_at');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_type) && !self::validType($form->parent_type, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
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
		if($type=='spaces'){
			$pivot_array['parent_type'] = 'chats';
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

	public function delete(){
		//We cannot delete a chat out of the scope of a workspace
		if($this->getParent()){
			return parent::delete();
		}
		return false;
	}

	public function restore(){
		//We cannot delete a chat out of the scope of a workspace
		if($this->getParent()){
			return parent::restore();
		}
		return false;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$query = $query
		->where(function ($query) use ($list) {
			$query
			->where(function ($query) {
				$app = self::getApp();
				$query
				->where('chats.parent_type', '')
				->orWhere('chats.parent_type', null);
			})
			->orWhere(function ($query) use ($list) {
				$ask = false;
				foreach ($list as $table_name => $list_id) {
					if(!empty($table_name) && in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
						$this->var['parent_type'] = $table_name;
						$this->var['parent_id_array'] = $list_id;
						$query = $query
						->orWhere(function ($query) {
							$query
							->where('chats.parent_type', $this->var['parent_type'])
							->whereIn('chats.parent_id', $this->var['parent_id_array']);
						});
						$ask = true;
					}
				}
				if(!$ask){
					$query = $query
					->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include any category
				}
			});
		})
		->whereHas('users', function ($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 1);
		});
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

	public function checkPermissionAllow($level, $msg=false){
		$app = self::getApp();
		$this->checkUser();
		$level = $this->formatLevel($level);
		if($level==1 && is_null($this->parent) && $this->parent_id<=0){
			return true; // Allow creation at root level
		}
		return parent::checkPermissionAllow($level);
	}

}
