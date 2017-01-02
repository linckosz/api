<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
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
		'created_by',
		'updated_at',
		'deleted_at',
		'title',
		'single',
		'_parent',
		'_spaces',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $prefix_fields = array(
		'title' => '+title',
	);

	protected static $hide_extra = array(
		'temp_id',
		'title',
		'viewed_by',
	);

	protected $name_code = 100;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => 101, //[{un}] created a new chat group
		'_' => 102,//[{un}] modified a chat group
		'title' => 103,//[{un}] changed a chat group title
		'pivot_users_access_0' => 196, //[{un}] blocked [{cun}]'s access to a chat group
		'pivot_users_access_1' => 197, //[{un}] authorized [{cun}]'s access to a chat group
		'_restore' => 198,//[{un}] restored a chat group
		'_delete' => 199,//[{un}] deleted a chat group
	);

	protected static $parent_list = array(null, 'workspaces', 'projects');

	protected $model_boolean = array(
		'single',
	);

	protected $model_integer = array(
		'fav',
	);

	protected static $allow_role = true;

	protected static $permission_sheet = array(
		2, //[RCU] owner
		3, //[RCUD] max allow || super
	);

	protected static $has_perm = true;

////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_chats', array('fav', 'silence', 'hide', 'noticed')),
		'spaces' => array('spaces_x', array('created_at')),
	);

	//Many(Chats) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_chats', 'chats_id', 'users_id')->withPivot('access', 'fav', 'silence', 'hide', 'noticed');
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
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'spaces_x', 'parent_id', 'spaces_id')->where('spaces_x.parent_type', 'chats')->withPivot('access', 'fav', 'created_at', 'exit_at');
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
			->where('access', 1)
			->where('hide', 0);
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

	public function pivots_save(array $parameters = array()){
		//For all chats that depends of a projects, we automatically give them same access as projects
		$parent = $this->getParent();
		if($parent && $parent->getTable()=='projects'){
			$users_inform = array();
			$pivot = new \stdClass;
			$pivot->{'users>access'} = new \stdClass;
			if($perm_project = json_decode($parent->getPerm())){
				if($perm_chat = json_decode($this->getPerm())){
					foreach ($perm_chat as $uid => $value) {
						$users_inform[$uid] = array('chats' => 1);
						unset($perm_project->$uid); //No need to save any user already registered
					}
				}
				$save = false;
				foreach ($perm_project as $uid => $value) {
					$users_inform[$uid] = array('chats' => 1);
					$save = true;
					$pivot->{'users>access'}->{$uid} = true;
				}
				
				if($save){
					$this->pivots_format($pivot, true);
				}
			}
		}
		return parent::pivots_save($parameters);
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array()){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return array(null, $links);
		}
		$app = self::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}

		//Skip single chats and chats that are not part of a project
		if($this->single!=0 || $this->parent_type!='projects'){
			return array(null, $links);
		}

		$clone = $this->replicate();

		$clone->created_by = $uid;
		if(!is_null($clone->deleted_by)){ $clone->deleted_by = $uid; }
		foreach ($attributes as $key => $value) {
			$clone->$key = $value;
		}
		//Initialization of attributes
		$clone->temp_id = '';
		if(!is_null($clone->deleted_at)){
			$clone->deleted_at = Carbon::createFromFormat('Y-m-d H:i:s', $clone->deleted_at)->addSeconds($offset);
		}
		$clone->noticed_by = '';
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->extra = null;

		//Pivots
		$pivots = new \stdClass;
		$dependencies_visible = $clone::getDependenciesVisible();
		$extra = $this->extraDecode();
		foreach ($dependencies_visible as $dep => $value) {
			if(!isset($exclude_links[$dep]) && isset($dependencies_visible[$dep][1])){
				if($extra && (!isset($extra->{'_'.$dep}) || empty($extra->{'_'.$dep}))){
					continue;
				}
				$items = $this->$dep; //Use the relation table
				foreach ($items as $item) {
					$table = $item->getTable();
					if(isset($links[$table][$item->id])){
						if(!isset($pivots->{$dep.'>access'})){ $pivots->{$dep.'>access'} = new \stdClass; }
						$pivots->{$dep.'>access'}->{$links[$table][$item->id]} = true;
						foreach ($dependencies_visible[$dep][1] as $field) {
							if(isset($item->pivot->$field)){
								if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }
								$pivots->{ $dep.'>'.$field}->{$links[$table][$item->id]} = $item->pivot->$field;
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

		$clone->saveHistory(false);
		$clone->save();
		$links[$this->getTable()][$this->id] = $clone->id;

		return $clone; //$link is directly modified as parameter &$link
	}

}
