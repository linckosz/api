<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

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

	protected $show_field = 'comment';

	protected $search_fields = array(
		'comment',
	);

	protected $name_code = 200;

	protected $archive = array(
		'created_at' => 201, //[{un|ucfirst}] sent a new message
		'_' => 202,//[{un|ucfirst}] modified a message
		'comment' => 202,//[{un|ucfirst}] modified a message
		'recalled_by' => 203,//[{un|ucfirst}] recalled a message
		'_restore' => 298,//[{un|ucfirst}] restored a message
		'_delete' => 299,//[{un|ucfirst}] deleted a message
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $parent_list = array('users', 'comments', 'chats', 'workspaces', 'projects', 'tasks', 'notes', 'files');

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

	//Many(comments) to Many(Comments)
	public function comments(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'comments', 'id', 'parent_id');
	}

	//Many(comments) to Many(Chats)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'comments', 'id', 'parent_id');
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
	public static function filterPivotAccessList(array $list, $suffix=false, $all=false){
		return array();
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function scopegetItems($query, $list=array(), $get=false){
		$app = self::getApp();
		$query = $query
		->where(function ($query) use ($list) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			//Insure to only get comments that the user is concerned
			->where(function ($query) {
				$app = self::getApp();
				$query
				->where('comments.parent_type', 'users')
				->where('comments.created_by', $app->lincko->data['uid']);
			})
			->orWhere(function ($query) {
				$app = self::getApp();
				$query
				->where('comments.parent_type', 'users')
				->where('comments.parent_id', $app->lincko->data['uid']);
			})
			//Get any other comments but exclude users' ones 
			->orWhere(function ($query) use ($list) {
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
					}
				}
			});
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

	public function toJson($detail=true, $options = 0){
		if(!empty($this->recalled_by)){
			$this->comment = '...';
			$temp = parent::toJson($detail, $options);
			$temp = json_decode($temp);
			$temp->new = 0;
			$temp = json_encode($temp, $options);
		} else {
			$temp = parent::toJson($detail, $options);
		}
		return $temp;
	}

	public function toVisible(){
		if(!empty($this->recalled_by)){
			$this->comment = '...';
			$model = parent::toVisible();
			$model->new = false;
		} else {
			$model = parent::toVisible();
		}
		return $model;
	}
	
}
