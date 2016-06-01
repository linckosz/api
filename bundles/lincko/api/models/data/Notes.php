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
		'created_by',
		'updated_by',
		'title',
		'comment',
		'_parent',
		'_files',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
		'comment',
	);

	protected $name_code = 800;

	protected $archive = array(
		'created_at' => 801, //[{un|ucfirst}] created a new note
		'_' => 802,//[{un|ucfirst}] modified a note
		'title' => 803,//[{un|ucfirst}] changed a note title
		'comment' => 804, //[{un|ucfirst}] modified a note content
		'parent_id' => 805, //[{un|ucfirst}] moved a note to the project "[{pj|ucfirst}]"
		'pivot_access_0' => 896, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a note
		'pivot_access_1' => 897, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a note
		'_restore' => 898,//[{un|ucfirst}] restored a note
		'_delete' => 899,//[{un|ucfirst}] deleted a note
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'parent_id' => '\\bundles\\lincko\\api\\models\\data\\Projects',
	);

	protected static $relations_keys = array(
		'users',
		'projects',
	);

	protected static $parent_list = 'projects';

	protected static $allow_single = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'files' => array('access'),
	);

	//Many(Notes) to One(Projects)
	public function projects(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//Many(Notes) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_notes', 'notes_id', 'users_id')->withPivot('access');
	}

	//Many(Tasks) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'notes_x_files', 'notes_id', 'files_id')->withPivot('access');
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
		//It will get all roles with access 1, and all roles which are not in the relation table, but the second has to be in conjonction with projects
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) {
				if(isset($list['projects']) && count($list['projects'])>0){
					$query = $query
					->whereIn('notes.parent_id', $list['projects']);
				}
			})
			->orWhere(function ($query) {
				$query = $query
				->whereHas('projects', function ($query) {
					$query->getItems();
				});
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
