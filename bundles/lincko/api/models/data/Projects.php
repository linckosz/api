<?php
// Category 5

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Chats;
use Carbon\Carbon;
use \libs\Json;

class Projects extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'projects';
	protected $morphClass = 'projects';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'created_at',
		'updated_at',
		'deleted_at',
		'created_by',
		'title',
		'description',
		'diy',
		'personal_private',
		'resume',
		'_parent',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $prefix_fields = array(
		'title' => '+title',
		'description' => '-description',
	);

	protected static $hide_extra = array(
		'temp_id',
		'title',
		'description',
		'viewed_by',
	);

	protected $name_code = 400;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => 401, //[{un}] created a new project
		'_' => 402,//[{un}] modified a project
		'title' => 403,//[{un}] changed a project name
		'description' => 404, //[{un}] modified a project description
		'resume' => 402,//[{un}] modified a project
		'_tasks_0' => 405,//[{un}] moved the task "[{tt}]" from this project to another one
		'_tasks_1' => 406,//[{un}] moved the task "[{tt}]" to this project
		'diy' => 402,//[{un}] modified a project
		'pivot_users_access_0' => 496, //[{un}] blocked [{cun}]'s access to a project
		'pivot_users_access_1' => 497, //[{un}] authorized [{cun}]'s access to a project
		'_restore' => 498,//[{un}] restored a project
		'_delete' => 499,//[{un}] deleted a project
	);

	protected static $history_xdiff = array('description');

	protected static $parent_list = 'workspaces';

	protected $model_integer = array(
		'fav',
		'personal_private',
		'resume',
	);

	protected static $allow_role = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);

	protected static $has_perm = true;

////////////////////////////////////////////

	protected static $dependencies_visible = array(
		'users' => array('users_x_projects', array('fav', 'silence', 'noticed')),
	);

	//Many(Projects) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_projects', 'projects_id', 'users_id')->withPivot('access', 'fav', 'silence', 'noticed');
	}

	//One(Projects) to Many(Comments)
	public function comments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'projects', 'id', 'parent_id');
	}

	//Many(Projects) to One(Workspaces)
	public function workspaces(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'parent_id');
	}

	//One(Projects) to Many(Tasks)
	public function tasks(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'parent_id');
	}

	//One(Projects) to Many(Notes)
	public function notes(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'parent_id');
	}

	//One(Projects) to Many(Files)
	public function files(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Files', 'parent_id')->where('files.parent_type', 'projects');
	}

	//One(Projects) to Many(Files)
	public function Chats(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'parent_id')->where('chats.parent_type', 'projects');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
			|| (isset($form->description) && !self::validText($form->description, true))
			|| (isset($form->diy) && !self::validDIY($form->diy, true))
			|| (isset($form->resume) && !self::validNumeric($form->resume, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Only add access at true
	public function delete(){
		if(isset($this->personal_private) && !empty($this->personal_private)){
			$this::errorMsg('Cannot delete a private project');
			$this->checkPermissionAllow(4);
			return false;
		}
		return parent::delete();
	}

	//Insure that we only record 1 personal_private project for each user
	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		$new = !isset($this->id);
		if($this->personal_private == $app->lincko->data['uid']){
			$this->parent_id = 0;
		} else if($new){
			$this->personal_private = null;
			$this->parent_id = intval($app->lincko->data['workspace_id']);
			if(!isset($this->resume)){
				$this->resume = 0 + Users::getUser()->timeoffset;
				//By default start it at midnight
				if($this->resume < 0){
					$this->resume = 24 + $this->resume;
				}
				if($this->resume >= 24){
					$this->resume = fmod($this->resume, 24);
				}
			}
		} else {
			$this->personal_private = null;
		}
		$return = parent::save($options);
		return $return;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) {
				//Get personal project
				$app = ModelLincko::getApp();
				$query
				->orderBy('created_by', 'asc') //By security, always take the ealiest created private project
				->where('personal_private', $app->lincko->data['uid'])
				->take(1);
			})
			->orWhere(function ($query) {
				//Exclude private project, and be sure to have access to the project (because the user whom created the project does not necessary have access to it)
				$app = ModelLincko::getApp();
				$query
				->whereHas('users', function ($query){
					$app = ModelLincko::getApp();
					$query
					->where('users_id', $app->lincko->data['uid'])
					->where('access', 1);
				})
				->where('projects.parent_id', $app->lincko->data['workspace_id']) //Insure to get only the company information
				->where('personal_private', null);
			});
		});
		if(self::$with_trash_global){
			$query = $query->withTrashed();
		}
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
				if($result[$key]->personal_private == $app->lincko->data['uid']){
					$result[$key]->parent_id = $app->lincko->data['workspace_id'];
				}
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
		//Personal_private
		if(intval($this->personal_private)>0){
			if($level==0 && $this->personal_private==$app->lincko->data['uid']){ //Read
				return true;
			}
			if($level==1){ //Creation
				//Only if no project attached for the user itself
				if(!isset($this->id) && $this->personal_private==$app->lincko->data['uid'] && self::where('personal_private', $app->lincko->data['uid'])->take(1)->count() <= 0){
					return true;
				}
				$msg = $app->trans->getBRUT('api', 5, 1); //Cannot save more than one private project per user.
				\libs\Watch::php($msg, 'Projects->save()', __FILE__, __LINE__, true);
				return parent::checkPermissionAllow(4, $msg); //this will only launch error, since $level = 4
			}
			if($level==2 && isset($this->id) && $this->personal_private==$app->lincko->data['uid']){ //Edit
				$allow = true;
				$dirty = $this->getDirty();
				//Reject editability of some attributes
				if(
					   isset($dirty['personal_private'])
					|| isset($dirty['parent_type'])
					|| isset($dirty['parent_id'])
					|| isset($dirty['_perm'])
				){
					$allow = false;
				}
				if($allow){
					foreach ($this->pivots_var as $type => $type_list) {
						if($type=='users'){
							foreach ($type_list as $users_id => $list) {
								if($users_id != $app->lincko->data['uid']){
									unset($this->pivots_var->$type->$users_id);
								} else {
									foreach ($list as $pivot => $value) {
										if($pivot=='access'){
											if(is_array($value)){
												unset($this->pivots_var->$type->$users_id[$pivot]);
											} else if(is_object($value)){
												unset($this->pivots_var->$type->$users_id->$pivot);
											}
										}
									}
								}
							}
						}
					}
					return true;
				}
			}
			return false;
		}
		return parent::checkPermissionAllow($level);
	}

	public function getHistoryCreation($history_detail=false, array $parameters = array(), $items=false){
		$app = ModelLincko::getApp();
		if($this->personal_private==$app->lincko->data['uid']){
			//Do not record the private project creation since it's created by the framework while user signing
			return new \stdClass;
		} else {
			return parent::getHistoryCreation($history_detail, $parameters);
		}
	}

	public static function setPersonal(){
		$app = ModelLincko::getApp();
		if(self::where('personal_private', $app->lincko->data['uid'])->take(1)->count() <= 0){
			$project = new self();
			$project->title = 'Personal Space';
			$project->personal_private = $app->lincko->data['uid'];
			$project->parent_id = 0;
			if($project->save()){
				$project->setPerm();
				return $project;
			}
		}
		return false;
	}

	public static function getPersonal(){
		$app = ModelLincko::getApp();
		return self::where('personal_private', $app->lincko->data['uid'])->first();
	}

	public function pivots_format($form, $history_save=true){
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		$save = parent::pivots_format($form, $history_save);
		//Disallow any access to personal project from outside users
		if($this->personal_private == $uid && isset($this->pivots_var->users)){
			foreach ($this->pivots_var->users as $users_id => $column_list) {
				if($users_id!=$uid){
					foreach ($column_list as $column => $value) {
						if($column=='access'){
							unset($this->pivots_var->users->$users_id->access);
						}
					}
				}
			}
		}
		return $save;	
	}

	public function setPerm(){
		$users = $this->users()
			->where('users_x_projects.access', 1)
			->get();
		$users_inform = array();
		$pivot = new \stdClass;
		$pivot->{'users>access'} = new \stdClass;
		foreach ($users as $value) {
			//Automatically include all users in all chats
			$pivot->{'users>access'}->{$value->pivot->users_id} = true;
			$users_inform[$value->pivot->users_id] = array('chats' => 1);
		}
		//We don't need to care about previous access settings since the one outisde a projects won't appear on chat users list
		$chats = Chats::Where('parent_type', 'projects')->where('parent_id', $this->id)->get();
		foreach ($chats as $model) {
			$model->pivots_format($pivot);
			$model->forceSaving();
			$model->pivots_save();
			$model->touchUpdateAt($users_inform, true);
		}
		return parent::setPerm();
	}

	public function clone($offset=false, $attributes=array(), &$links=array(), $exclude_pivots=array('users'), $exclude_links=array()){
		//Skip if it already exists
		if(isset($links[$this->getTable()][$this->id])){
			return array(null, $links);
		}
		$app = ModelLincko::getApp();
		$uid = $app->lincko->data['uid'];
		if($offset===false){
			$offset = $this->created_at->diffInSeconds();
		}
		$clone = $this->replicate();
		$clone->forceGiveAccess();

		foreach ($attributes as $key => $value) {
			$clone->$key = $value;
		}
		
		$clone->created_by = $uid;
		if(!is_null($clone->deleted_by)){ $clone->deleted_by = $uid; }
		//Initialization of attributes
		$clone->temp_id = '';
		if(!is_null($clone->deleted_at)){
			$clone->deleted_at = Carbon::createFromFormat('Y-m-d H:i:s', $clone->deleted_at)->addSeconds($offset);
		}
		$clone->personal_private = null;
		$clone->viewed_by = '';
		$clone->_perm = '';
		$clone->title = trim($this->title).' ['.$app->trans->getBRUT('api', 5, 3).']'; //copy
		$clone->resume = 0;
		$clone->extra = null;

		/*
		//Increment new project "A title [1]" => "A title [2]"
		$title = trim($this->title);
		if(preg_match("/^.+\[(\d+)\]$/ui", $title, $matches)){
			$i = intval($matches[1])+1;
			$clone->title = preg_replace("/^(.*)(\[\d+\])$/ui", '${1}['.$i.']', $title);
		} else {
			$clone->title = $title.' [1]';
		}
		*/

		$clone->saveHistory(false);
		$clone->save();
		$links[$this->getTable()][$this->id] = $clone;
		if(static::$permission_sheet[0]){ //Permission of owner
			self::$permission_users[$uid][$clone->getTable()][$clone->id] = static::$permission_sheet[0];
		}

		$text = $this->description;
		$parent_id = $this->parent_id;
		if(preg_match_all("/src=\".+?\/file\/(\d+)\/(.+?)\/(thumbnail|link|download)\/(\d+)\/(.+?)\?.*?\"/ui", $text, $matches,  PREG_SET_ORDER)){
			foreach ($matches as $match) {
				$ori = $match[0];
				$w = $match[1];
				$shaold = $match[2];
				$type = $match[3];
				$fileid = $match[4];
				$filename = $match[5];
				usleep(10000);
				$new = false;
				if($file = Files::withTrashed()->find($fileid)){
					$shanew = base64_encode(Datassl::encrypt_smp($file->link));
					$new = str_replace("/file/$w/$shaold/$type/$fileid/", "/file/$w/$shanew/$type/$fileid/", $ori);
				} else if($file = Files::withTrashed()->where('parent_type', 'projects')->where('parent_id', $this->parent_id)->where('name', $filename)->first()){ //Correct errors
					//If no puid we try to grab it from another similar file
					$puid = $file->puid;
					if(!$puid && $file_puid = Files::withTrashed()->where('link', $file->link)->whereNotNull('puid')->first()){
						$puid = $file_puid->puid;
					}
					if($puid){
						$fileidbis = $file->id;
						$shanew = base64_encode(Datassl::encrypt_smp($file->link));
						$new = str_replace("/file/$w/$shaold/$type/$fileid/", "/file/$w/$shanew/$type/$fileidbis/", $ori);
					}
				}
				if($new){
					//Use https by default
					$new = str_replace("http://", "https://", $new);
					$new = str_replace(":8080/", ":8443/", $new);
					$text = str_replace($ori, $new, $text);
				} else {
					continue; //Do not convert if any issue
				}
			}
			$text = STR::HTMLwithReturnLine($text);
			$time = $this->freshTimestamp();
			$clone::withTrashed()->where('id', $clone->id)->getQuery()->update(['description' => $text, 'updated_at' => $time, 'extra' => null]);
		}


		//Clone spaces (no dependencies)
		if(!isset($exclude_links['spaces'])){
			$attributes = array(
				'parent_type' => 'projects',
				'parent_id' => $clone->id,
			);
			if($spaces = $this->spaces){
				foreach ($spaces as $space) {
					$space->clone($offset, $attributes, $links);
				}
			}
		}

		//Clone chats (spaces)
		if(!isset($exclude_links['chats'])){
			$attributes = array(
				'parent_type' => 'projects',
				'parent_id' => $clone->id,
			);
			if($chats = $this->chats){
				foreach ($chats as $chat) {
					$chat->clone($offset, $attributes, $links);
				}
			}
		}

		//Clone files (spaces)
		if(!isset($exclude_links['files'])){
			$attributes = array(
				'parent_type' => 'projects',
				'parent_id' => $clone->id,
			);
			if($files = $this->files){
				foreach ($files as $file) {
					$file->clone($offset, $attributes, $links);
				}
			}
		}

		//Clone notes (spaces, files)
		if(!isset($exclude_links['notes'])){
			$attributes = array(
				'parent_id' => $clone->id,
			);
			if($notes = $this->notes){
				foreach ($notes as $note) {
					$note->clone($offset, $attributes, $links);
				}
			}
		}

		//Clone tasks (spaces, files)
		if(!isset($exclude_links['tasks'])){
			$attributes = array(
				'parent_id' => $clone->id,
			);
			if($tasks = $this->tasks){
				foreach ($tasks as $task) {
					$task->clone($offset, $attributes, $links);
				}
			}
		}
		
		//Clone comments (projects)
		if(!isset($exclude_links['comments'])){
			$attributes = array(
				'parent_type' => 'projects',
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
