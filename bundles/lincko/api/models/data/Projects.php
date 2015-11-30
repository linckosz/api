<?php
// Category 5

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \libs\Json;

class Projects extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'projects';

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
	);

	// CUSTOMIZATION //

	protected $show_field = 'title';

	protected $search_fields = array(
		'title',
		'description',
	);

	protected $archive = array(
		'created_at' => 401, //[{un|ucfirst}] created a new project.
		'_' => 402,//[{un|ucfirst}] modified a project.
		'title' => 403,//[{un|ucfirst}] changed a project name.
		'description' => 404, //[{un|ucfirst}] modified a project description.
		'_access_0' => 496, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a project.
		'_access_1' => 497, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a project.
		'_restore' => 498,//[{un|ucfirst}] restored a project.
		'_delete' => 499,//[{un|ucfirst}] deleted a project.
	);

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'companies_id' => '\\bundles\\lincko\\api\\models\\data\\Companies',
	);

	protected static $relations_keys = array(
		'users',
		'companies',
	);

////////////////////////////////////////////

	//Many(Projects) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_projects', 'projects_id', 'users_id')->withPivot('access');
	}

	//Many(Projects) to One(Companies)
	public function companies(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Companies', 'companies_id');
	}

	//One(Projects) to Many(Tasks)
	public function tasks(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'projects_id');
	}

////////////////////////////////////////////
	public static function validTitle($data){
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validDescription($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function isValid($form){
		if(!isset($form->title)){ self::noValidMessage(false, 'title'); } //Required
		return
			     isset($form->title) && self::validTitle($form->title)
			&& (!isset($form->description) || self::validDescription($form->description)) //Optional 
			;
	}

////////////////////////////////////////////

	//Insure that we only record 1 personal_private project for each company
	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		if($this->personal_private===1){
			if(self::where('personal_private', 1)->where('created_by', $app->lincko->data['uid'])->where('companies_id', $this->companies_id)->count() > 1){
				$msg = $msg = $app->trans->getBRUT('api', 5, 1); //Cannot save more than one private project for each company.
				\libs\Watch::php($msg, 'Projects->save()', __FILE__, true);
				$json = new Json($msg, true, 406);
				$json->render();
				return false;
			}
		}
		$return = parent::save($options);
		if($new){
			$this->setUserPivotValue($app->lincko->data['uid'], 'access', 1, false);
		}
		return $return;
	}

	public function scopegetLinked($query){
		$app = self::getApp();
		return $query
		->where('companies_id', $app->lincko->data['company_id']) //Insure to get only the company information
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_by condition in Data.php
			$query
			->where(function ($query) {
				//Get personal project
				$app = self::getApp();
				$query
				->orderBy('created_by', 'asc') //By security, always take the ealiest created private project
				->where('created_by', '=', $app->lincko->data['uid'])
				->where('personal_private', 1)
				->take(1);
			})
			->orWhere(function ($query) {
				//Exclude private project, and be sure to have access to the project (because the user whom created the project does not necessary have access to it)
				$query
				->whereHas('users', function ($query){
					$app = self::getApp();
					$uid = $app->lincko->data['uid'];
					$query->where('users_id', $uid)->where('access', 1);
				})
				->where('personal_private', 0)
				->whereHas('companies', function ($query) {
					$query->getLinked();
				});
			});
		});
	}

	//Get all users that are linked to the project
	public function getUsersContacts(){
		$contacts = parent::getUsersContacts();
		$list = $this->users()->get();
		foreach($list as $key => $value) {
			$id = $value->id;
			$contacts->$id = $this->getContactsInfo();
		}
		return $contacts;
	}

	public function getCompany(){
		return $this->companies_id;
	}

	

}
