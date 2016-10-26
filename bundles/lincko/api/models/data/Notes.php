<?php
// Category 6

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;

class Notes extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'notes';
	protected $morphClass = 'notes';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'updated_at',
		'deleted_at',
		'created_by',
		'updated_by',
		'fav',
		'title',
		'comment',
		'_parent',
		'_files',
		'_tasks',
		'_spaces',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $save_user_access = false;

	protected static $prefix_fields = array(
		'title' => '+title',
		'comment' => '-comment',
	);

	protected static $hide_extra = array(
		'temp_id',
		'title',
		'comment',
		'viewed_by',
	);

	protected $name_code = 800;

	protected static $archive = array(
		'created_at' => 801, //[{un}] created a new note
		'_' => 802,//[{un}] modified a note
		'title' => 803,//[{un}] changed a note title
		'comment' => 804, //[{un}] modified a note content
		'parent_id' => 805, //[{un}] moved a note to the project "[{pj|ucfirst}]"
		'pivot_access_0' => 896, //[{un}] blocked [{[{cun}]}]'s access to a note
		'pivot_access_1' => 897, //[{un}] authorized [{[{cun}]}]'s access to a note
		'_restore' => 898,//[{un}] restored a note
		'_delete' => 899,//[{un}] deleted a note
	);

	protected static $parent_list = 'projects';

	protected $model_integer = array(
		'fav',
	);

	protected static $allow_single = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = true;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_notes', array('fav')),
		'files' => array('notes_x_files', array('fav')),
		'tasks' => array('tasks_x_notes', array('fav')),
		'spaces' => array('spaces_x', array('created_at')),
	);

	//Many(Notes) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//Many(Notes) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_notes', 'notes_id', 'users_id')->withPivot('access', 'fav');
	}

	//Many(Notes) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'notes_x_files', 'notes_id', 'files_id')->withPivot('access', 'fav');
	}

	//Many(Notes) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'tasks_x_notes', 'notes_id', 'tasks_id')->withPivot('access', 'fav');
	}

	//Many(Notes) to Many(Spaces)
	public function spaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'notes')->withPivot('access', 'fav', 'created_at', 'exit_at');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
			|| (isset($form->comment) && !self::validText($form->comment, true))
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
			$pivot_array['parent_type'] = 'notes';
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

	public function scopegetItems($query, $list=array(), $get=false){
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) use ($list) {
				if(isset($list['projects']) && count($list['projects'])>0){
					$query = $query
					->whereIn('notes.parent_id', $list['projects']);
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

}
