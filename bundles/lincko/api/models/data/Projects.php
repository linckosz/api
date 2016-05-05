<?php
// Category 5

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Workspaces;
use \libs\Json;

class Projects extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'projects';
	protected $morphClass = 'projects';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'created_by',
		'title',
		'description',
		'personal_private',
		'_parent',
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
		'description',
	);

	protected $archive = array(
		'created_at' => 401, //[{un|ucfirst}] created a new project
		'_' => 402,//[{un|ucfirst}] modified a project
		'title' => 403,//[{un|ucfirst}] changed a project name
		'description' => 404, //[{un|ucfirst}] modified a project description
		'_access_0' => 496, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a project
		'_access_1' => 497, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a project
		'_restore' => 498,//[{un|ucfirst}] restored a project
		'_delete' => 499,//[{un|ucfirst}] deleted a project
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'parent_id' => '\\bundles\\lincko\\api\\models\\data\\Workspaces',
	);

	protected static $relations_keys = array(
		'users',
		'workspaces',
	);

	protected static $parent_list = 'workspaces';

	protected $model_integer = array(
		'personal_private',
	);

	protected static $allow_role = true;

	protected static $permission_sheet = array(
		3, //[RCUD] owner
		3, //[RCUD] max allow || super
	);

////////////////////////////////////////////

	//Many(Projects) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_projects', 'projects_id', 'users_id')->withPivot('access');
	}

	//Many(Projects) to One(Workspaces)
	public function workspaces(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'parent_id');
	}

	//One(Projects) to Many(Tasks)
	public function tasks(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'parent_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->parent_id) && !self::validNumeric($form->parent_id, true))
			|| (isset($form->title) && !self::validTitle($form->title, true))
			|| (isset($form->description) && !self::validText($form->description, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

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
		$app = self::getApp();
		$new = !isset($this->id);
		if($this->personal_private == $app->lincko->data['uid']){
			$this->parent_id = 0;
		} else if($new){
			$this->personal_private = null;
			$this->parent_id = intval($app->lincko->data['workspace_id']);
		} else {
			$this->personal_private = null;
		}
		$return = parent::save($options);
		return $return;
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = self::getApp();
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$query
			->where(function ($query) {
				//Get personal project
				$app = self::getApp();
				$query
				->orderBy('created_by', 'asc') //By security, always take the ealiest created private project
				->where('personal_private', $app->lincko->data['uid'])
				->take(1);
			})
			->orWhere(function ($query) {
				//Exclude private project, and be sure to have access to the project (because the user whom created the project does not necessary have access to it)
				$app = self::getApp();
				$query
				->whereHas('users', function ($query){
					$app = self::getApp();
					$query
					->where('users_id', $app->lincko->data['uid'])
					->where('access', 1);
				})
				->where('projects.parent_id', $app->lincko->data['workspace_id']) //Insure to get only the company information
				->where('personal_private', null);
			});
		});
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
		//Personal_privat
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
				\libs\Watch::php($msg, 'Projects->save()', __FILE__, true);
				return parent::checkPermissionAllow(4, $msg); //this will only launch error, since $level = 4
			}
			return false;
		}
		return parent::checkPermissionAllow($level);
	}

	public function getHistoryCreation(array $parameters = array()){
		$app = self::getApp();
		if($this->personal_private==$app->lincko->data['uid']){
			//Do not record the private project creation since it's created by the framework while user signing
			return new \stdClass;
		} else {
			return parent::getHistoryCreation($parameters);
		}
	}

	public static function setPersonal(){
		$app = self::getApp();
		if(self::where('personal_private', $app->lincko->data['uid'])->take(1)->count() <= 0){
			$project = new self();
			$project->title = 'Private';
			$project->personal_private = $app->lincko->data['uid'];
			$project->parent_id = 0;
			if($project->save()){
				return $project;
			}
		}
		return false;
	}

}
