<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\Inform;

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
		'style', //0:normal / 1:feedback / 2:alert
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
		'created_at' => array(true, 101), //[{un}] created a new chat group
		'_' => array(true, 102), //[{un}] modified a chat group
		'title' => array(true, 103), //[{un}] changed a chat group title
		'pivot_users_access_0' => array(true, 196), //[{un}] blocked [{cun}]'s access to a chat group
		'pivot_users_access_1' => array(true, 197), //[{un}] authorized [{cun}]'s access to a chat group
		'_restore' => array(true, 198), //[{un}] restored a chat group
		'_delete' => array(true, 199), //[{un}] deleted a chat group
	);

	protected static $parent_list = array(null, 'workspaces', 'projects');

	protected $model_boolean = array(
		'single',
	);

	protected $model_integer = array(
		'fav',
		'style',
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
			|| (isset($form->style) && !self::validNumeric($form->style, true))
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
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->where(function ($query) use ($list) {
				$query
				->where(function ($query) {
					$app = ModelLincko::getApp();
					if($app->lincko->data['workspace_id']<=0){
						//Shared area => We only see chats that are in shared workspace
						$query
						->where(function ($query) {
							$query
							->where('chats.parent_type', 'workspaces')
							->orWhere('chats.parent_type', null);
						})
						->where('chats.parent_id', 0);
					} else {
						//In workspace => We only see chats that are part of this workspace (a conversation to someone in a workspace will be different in that workspace)
						$query
						->where('chats.parent_type', 'workspaces')
						->where('chats.parent_id', $app->lincko->data['workspace_id']);
					}
				})
				->orWhere(function ($query) use ($list) {
					$app = ModelLincko::getApp();
					$ask = false;
					foreach ($list as $table_name => $list_id) {
						if(!empty($table_name) && in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
							if($table_name=='workspaces'){
								//Make sure that we only allow chats that are part of the workspace
								$workspace_id = $app->lincko->data['workspace_id'];
								$list_id = array(
									$workspace_id => $workspace_id,
								);
							}
							$this->var['parent_type'] = $table_name;
							$this->var['parent_id_array'] = $list_id;
							$query = $query
							->orWhere(function ($query) {
								$query
								->whereNotNull('chats.parent_type')
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
				$app = ModelLincko::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where('access', 1)
				->where('hide', 0);
			});
			if(self::$with_trash_global){
				$query = $query->withTrashed();
			}
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
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
		$app = ModelLincko::getApp();
		$this->checkUser();
		$level = $this->formatLevel($level);
		if($level==1 && is_null($this->parent) && $this->parent_id<=0){
			return true; // Allow creation at root level
		}
		return parent::checkPermissionAllow($level);
	}

	public function pivots_save(array $parameters = array(), $force_access=false){
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

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array('comments'=>true)){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return null;
		}
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}

		//Skip single chats and chats that are not part of a project
		if($this->single!=0 || $this->parent_type!='projects'){
			return null;
		}

		$clone = $this->replicate();
		$clone->forceGiveAccess();

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
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->extra = null;

		//Pivots
		$pivots = new \stdClass;
		$dependencies_visible = $clone::getDependenciesVisible();
		$extra = $this->extraDecode();
		foreach ($dependencies_visible as $dep => $value) {
			if(!in_array($dep, $exclude_pivots) && isset($dependencies_visible[$dep][1])){
				if($extra && (!isset($extra->{'_'.$dep}) || empty($extra->{'_'.$dep}))){
					continue;
				}
				$items = $this->$dep; //Use the relation table
				foreach ($items as $item) {
					$table = $item->getTable();
					if(isset($links[$table][$item->id])){
						if(!isset($pivots->{$dep.'>access'})){ $pivots->{$dep.'>access'} = new \stdClass; }
						$pivots->{$dep.'>access'}->{$links[$table][$item->id]->id} = $item->pivot->access;
						foreach ($dependencies_visible[$dep][1] as $field) {
							if(isset($item->pivot->$field)){
								if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }
								$pivots->{ $dep.'>'.$field}->{$links[$table][$item->id]->id} = $item->pivot->$field;
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
		$links[$this->getTable()][$this->id] = $clone;
		if(static::$permission_sheet[0]){ //Permission of owner
			self::$permission_users[$uid][$clone->getTable()][$clone->id] = static::$permission_sheet[0];
		}

		return $clone; //$link is directly modified as parameter &$link
	}

}
