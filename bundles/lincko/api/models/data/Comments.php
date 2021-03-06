<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\Inform;

class Comments extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'comments';
	protected $morphClass = 'comments';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'created_by',
		'recalled_by',
		'comment',
		'_parent',
		'_files',
		'_perm',
	);

	// CUSTOMIZATION // 

	protected static $prefix_fields = array(
		'comment' => '+comment',
	);

	protected static $hide_extra = array(
		'temp_id',
		'comment',
		'viewed_by',
	);

	protected $name_code = 200;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => array(true, 201), //[{un}] sent a new comment
		'_' => array(true, 202), //[{un}] modified a comment
		'comment' => array(true, 202), //[{un}] modified a comment
		'recalled_by' => array(true, 203), //[{un}] recalled a comment
			'_commented_on_item' => array(false, 210), //[{un}] commented on an item
			'_commented_on_chats' => array(false, 211), //[{un}]  commented on a chat group
		'_commented_on_comments' => array(true, 212), //[{un}] replied on a comment
			'_commented_on_workspaces' => array(false, 213), //[{un}] commented on a workspace
			'_commented_on_projects' => array(false, 214), //[{un}] commented on a project
		'_commented_on_tasks' => array(true, 215), //[{un}] commented on a task
			'_commented_on_users' => array(false, 216), //[{un}] commented on a user profile
			'_commented_on_roles' => array(false, 217), //[{un}] commented on a role
		'_commented_on_files' => array(true, 218), //[{un}] commented on a file
		'_commented_on_notes' => array(true, 219), //[{un}] commented on a note
			'_commented_on_spaces' => array(false, 220), //[{un}] commented on a space
		'_restore' => array(true, 298), //[{un}] restored a comment
		'_delete' => array(true, 299), //[{un}] deleted a comment
	);

	protected static $history_xdiff = array('comment');

	protected static $parent_list = array('comments', 'projects', 'tasks', 'notes', 'files');

	protected $model_integer = array(
		'recalled_by',
	);

	protected static $allow_single = false;
	protected static $allow_role = false;

	protected static $permission_sheet = array(
		2, //[RCU] owner
		2, //[RCU] max allow || super
	);

	protected static $access_accept = false;

	protected static $has_perm = true;
	
////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'files' => array('comments_x_files', array('access')),
	);

	//Many(comments) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'created_by');
	}

	//Many(comments) to Many(Workspaces)
	public function workspaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Files)
	public function files(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Files)
	public function Depfiles(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Files', 'comments_x_files', 'comments_id', 'files_id')->withPivot('access');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_type) && !self::validType($form->parent_type, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->comment) && !self::validTextNotEmpty($form->comment, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Give access to all, will be delete later by hierarchy
	public static function filterPivotAccessList(array $list, $all=false){
		return array();
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
				$query
				//Insure to only get comments that the user is concerned
				->where(function ($query) {
					$app = ModelLincko::getApp();
					$query
					->where('comments.parent_type', 'users')
					->where('comments.created_by', $app->lincko->data['uid']);
				})
				->orWhere(function ($query) {
					$app = ModelLincko::getApp();
					$query
					->where('comments.parent_type', 'users')
					->where('comments.parent_id', $app->lincko->data['uid']);
				})
				//Get any other comments but exclude users' ones 
				->orWhere(function ($query) use ($list) {
					$ask = false;
					foreach ($list as $table_name => $list_id) {
						if($table_name != 'users' && in_array($table_name, $this::$parent_list) && $this::getClass($table_name)){
							$this->var['parent_type'] = $table_name;
							$this->var['parent_id_array'] = $list_id;
							$query = $query
							->orWhere(function ($query) {
								$query
								->where('comments.parent_type', $this->var['parent_type'])
								->whereIn('comments.parent_id', $this->var['parent_id_array']);
							});
							$ask = true;
						}
					}
					if(!$ask){
						$query = $query
						->whereId(-1); //Make sure we reject it to not display the whole list if $list doesn't include any category
					}
				});
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
		if(!$this->checkAccess()){
			return false;
		}
		$level = $this->formatLevel($level);
		if(!isset($this->id) && $level==1){ //Allow creation
			return true;
		}
		//Recall (2 minutes on updated_at)
		if(isset($this->updated_at)){
			$time_recall = time()-(int)(new \DateTime($this->updated_at))->getTimestamp();
			if($level==2 && $this->created_by==$app->lincko->data['uid'] && $time_recall<120){
				return true;
			}
		}
		return false;
	}

	public function toJson($detail=true, $options = 256){ //256: JSON_UNESCAPED_UNICODE
		if(!empty($this->recalled_by)){
			$this->comment = ' ';
		}
		$temp = parent::toJson($detail, $options);
		return $temp;
	}

	public function toVisible(){
		if(!empty($this->recalled_by)){
			$this->comment = ' ';
		}
		$model = parent::toVisible();
		return $model;
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array()){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return null;
		}
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
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
		$clone->recalled_by = '';
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
								if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }if(!isset($pivots->{$dep.'>'.$field})){ $pivots->{$dep.'>'.$field} = new \stdClass; }
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

		//Clone comments (no dependencies)
		if(!in_array('comments', $exclude_links)){
			$attributes = array(
				'parent_type' => 'comments',
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

	public function saveRobot(array $options = array()){
		$result = Model::save($options);
		usleep(rand(30000, 35000)); //30ms
		return $result;
	}

	public function getHistoryCreationCode(&$items=false){
		$app = ModelLincko::getApp();

		//This helps to avoid to have "Bob commented on a message", but instead "Bob commented on a Tasks"
		$parent_type = $this->parent_type;
		$parent_id = $this->parent_id;
		$model = $this;
		if($parent_type=='comments'){
			$loop = true;
			while($loop){
				if($items && isset($items->$parent_type) && isset($items->$parent_type->$parent_id) && isset($items->$parent_type->$parent_id->_parent) && !is_null($items->$parent_type->$parent_id->_parent[0])){
					$parent = $items->$parent_type->$parent_id->_parent;
					$parent_type = $parent[0];
					$parent_id = $parent[1];
					if($parent_type!='comments'){	
						break;
					}
					continue;
				} else if($model = $model->getParent()){
					$parent_type = $model->getTable();
					$parent_id = $model->id;
					if($parent_type!='comments'){	
						break;
					}
					continue;
				}
				$loop = false;
			}
		}

		if(isset(static::$archive['_commented_on_'.$parent_type]) && static::$archive['_commented_on_'.$parent_type][0]){
			return (int) static::$archive['_commented_on_'.$parent_type][1];
		}

		return (int) static::$archive['created_at'][1];
	}

	public function getHistoryCreation($history_detail=false, array $parameters = array(), &$items=false){
		$app = ModelLincko::getApp();
		$history = parent::getHistoryCreation($history_detail, $parameters);

		//This helps to avoid to have "Bob commented on a message", but instead "Bob commented on a Tasks"
		$parent_type = $this->parent_type;
		$parent_id = $this->parent_id;
		$model = $this;
		if($parent_type=='comments'){
			$loop = true;
			while($loop){
				if($items && isset($items->$parent_type) && isset($items->$parent_type->$parent_id) && isset($items->$parent_type->$parent_id->_parent) && !is_null($items->$parent_type->$parent_id->_parent[0])){
					$parent = $items->$parent_type->$parent_id->_parent;
					$parent_type = $parent[0];
					$parent_id = $parent[1];
					if($parent_type!='comments'){	
						break;
					}
					continue;
				} else if($model = $model->getParent()){
					$parent_type = $model->getTable();
					$parent_id = $model->id;
					if($parent_type!='comments'){	
						break;
					}
					continue;
				}
				$loop = false;
			}
		}

		if(isset(self::$archive['_commented_on_'.$parent_type]) && self::$archive['_commented_on_'.$parent_type][0]){
			foreach ($history as $zero) {
				$zero->cod = (int) self::$archive['_commented_on_'.$parent_type][1];
				$zero->par_type = $parent_type;
				$zero->par_id = $parent_id;
			}
		}

		return $history;
	}

	public function pushNotif($new=false, $history=false){
		$app = ModelLincko::getApp();

		if(!$new || !empty($this->recalled_by)){
			return false;
		}
		if($this->created_by==0){ //Resumes are sent by cronjob
			return false;
		}

		$keep = false;
		$type = false;
		$title = 'Lincko';
		$comment_on = false;
		$sub_item = false;
		$parent = $this;
		while($parent = $parent->getParent()){
			$type = $parent->getTable();
			if($type=='projects'){
				$keep = true;
				if(!$comment_on){
					$comment_on = $type;
					$title = $parent->title;
				}
				break;
			} else if($type=='tasks' && !$comment_on){
				$sub_item = $parent;
				$comment_on = $type;
				$title = $parent->title;
			}
		}

		if($type!='projects' || !$keep || !$comment_on){
			return false;
		}

		$users = false;
		$users_accept = array();
		$pivot = new PivotUsers(array($type));
		if($this->tableExists($pivot->getTable())){
			$users = $pivot
			->where($type.'_id', $parent->id)
			->where('access', 1)
			->where('silence', 0)
			->get(array('users_id'));
			foreach ($users as $value) {
				$users_accept[$value->users_id] = $value->users_id;
			}
		}

		if(isset($history->pivot_type) && $history->pivot_type=='users'){
			$users_accept[$history->pivot_id] = $history->pivot_id;
		}
		if(isset($history->parent_type) && $history->parent_type=='users'){
			$users_accept[$history->parent_id] = $history->parent_id;
		}

		if($users && $sub_item && $comment_on=='tasks'){
			$type = 'tasks';
			$pivot = new PivotUsers(array($type));
			if($this->tableExists($pivot->getTable())){
				//Redefine users list with a narrow filter
				$users = $pivot
				->where($type.'_id', $sub_item->id)
				->whereIn('users_id', $users_accept)
				->where('access', 1)
				->where(function ($query){
					$query
					->where('in_charge', 1)
					->orWhere('approver', 1);
				})
				->get(array('users_id'));
			}
		}

		if($users){
			if($this->created_by==0){
				$sender = $app->trans->getBRUT('api', 0, 11); //LinckoBot
			} else {
				$sender = Users::find($this->created_by)->getUsername();
			}
			$content = $sender.': '.$this->comment;
			$alias = array();
			foreach ($users as $value) {
				if($value->users_id != $this->created_by && $value->users_id != $app->lincko->data['uid']){
					$user = Users::find($value->users_id);
					$alias[$value->users_id] = $user->getSha();
				}
			}
			unset($alias[$app->lincko->data['uid']]); //Exclude the user itself
			if(!empty($alias)){
				$inform = new Inform($title, $content, false, $alias, $this, array(), array('email')); //Exclude email
				$inform->send();
			}
		}
		return true;
	}

}
