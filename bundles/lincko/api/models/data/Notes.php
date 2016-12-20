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
		'locked_by',
		'locked_fp',
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
		'locked_by',
		'locked_fp',
	);

	protected $name_code = 800;

	protected static $archive = array(
		'created_at' => 801, //[{un}] created a new note
		'_' => 802,//[{un}] modified a note
		'title' => 803,//[{un}] changed a note title
		'comment' => 804, //[{un}] modified a note content
		'parent_id' => 805, //[{un}] moved a note to the project "[{pj|ucfirst}]"
		'pivot_access_0' => 896, //[{un}] blocked [{cun}]'s access to a note
		'pivot_access_1' => 897, //[{un}] authorized [{cun}]'s access to a note
		'_restore' => 898,//[{un}] restored a note
		'_delete' => 899,//[{un}] deleted a note
	);

	protected static $parent_list = 'projects';

	protected $model_integer = array(
		'fav',
		'locked_by',
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

	//One(Notes) to Many(Comments)
	public function comments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'notes', 'id', 'parent_id');
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

	public function toJson($detail=true, $options = 0){
		$this->locked_by = $this->checkLock()[0];
		return parent::toJson($detail, $options);
	}

	public function toVisible(){
		$this->locked_by = $this->checkLock()[0];
		return parent::toVisible();
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array()){
		//Skip if it already exists
		if(isset($link[$this->getTable()][$this->id])){
			return array(null, $links);
		}
		$app = self::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}
		$clone = $this->replicate();

		foreach ($attributes as $key => $value) {
			$clone->$key = $value;
		}
		
		//Initialization of attributes
		$clone->temp_id = '';
		if(!is_null($clone->deleted_at)){
			$clone->deleted_at = Carbon::createFromFormat('Y-m-d H:i:s', $clone->deleted_at)->addSeconds($offset);
		}
		$clone->created_by = $uid;
		if(!is_null($clone->deleted_by)){ $clone->deleted_by = $uid; }
		$clone->noticed_by = '';
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->locked_by = null;
		$clone->locked_at = null;
		$clone->extra = null;

		//Pivots
		$pivots = new \stdClass;
		$dependencies_visible = $clone::getDependenciesVisible();
		foreach ($dependencies_visible as $dep => $value) {
			if(isset($exclude_links[$dep]) && isset($dependencies_visible[$dep][1])){
				$items = $clone->$dep;
				foreach ($items as $item) {
					$table = $item->getTable();
					if(isset($links[$table][$item->id])){
						if(!isset($pivots->{$dep.'>access'})){ $pivots->{$dep.'>access'} = new \stdClass; }
						$pivots->{$dep.'>access'}->{$links[$table]} = true;
						foreach ($dependencies_visible[$dep][1] as $field) {
							if(isset($item->pivot->$field)){
								$pivots->{ $dep.'>'.$field}->{$links[$table]} = $item->pivot->$field;
								//If it's a Carbon object, we add the offset
								if($offset!=0){
									if(preg_match("/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d/ui", $item->pivot->$field)){
										try {
											$item->pivot->$field = Carbon::createFromFormat('Y-m-d H:i:s', $item->pivot->$field)->addSeconds($offset);
										} catch (\Exception $e) {}
									}
								}
							}
						}
					}
				}
			}
		}
		$clone->pivots_format($pivots, false);

		$clone->save();
		$link[$this->getTable()][$this->id] = [$clone->id];

		//Modify any link (toto => update this part the day the new tag spec is ready)
		$text = $clone->comment;
		if(preg_match_all("/<img.*?\/([=\d\w]+?)\/(thumbnail|link|download)\/(\d+)\/.*?>/ui", $text, $matches)){
			foreach ($matches[0] as $key => $value) {
				$sha = $matches[1][$key];
				$type = $matches[2][$key];
				$id = $matches[3][$key];
				if(isset($links['files'][$id])){
					$sha_new = $sha;
					$id_new = $links['files'][$id];
				} else {
					$sha_new = '0'; //broken link
					$id_new = '0'; //broken link
				}
				$text = str_replace("/$sha/$type/$id/", "/$sha_new/$type/$id_new/", $text);
			}
			$clone->comment = $text;
			$clone->brutSave();
			$clone->touchUpdateAt();
		}

		//Clone comments (no dependencies)
		if(!isset($exclude_links['comments'])){
			$attributes = array(
				'parent_type' => 'notes',
				'parent_id' => $clone->id,
			);
			if($comments = $this->comments){
				foreach ($comments as $comment) {
					$comment->clone($offset, $attributes, $links);
				}
			}
		}

		return $clone; //$link is directly modified as parameter &$link
	}

}
