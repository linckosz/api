<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Companies extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'companies';
	protected $morphClass = 'companies';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'name',
		'domain',
		'url',
		'personal_private',
	);

	// CUSTOMIZATION //

	protected $contactsLock = true; //Do not allow to delete users from contact list

	protected $contactsVisibility = true; //Make all user linked to the company visible by the user into the contact list

	protected $archive = array(
		'created_at' => 301, //[{un|ucfirst}] created a new workspace.
		'_' => 302,//[{un|ucfirst}] modified the workspace.
		'name' => 303,//[{un|ucfirst}] changed the workspace name.
		'domain' => 304,//[{un|ucfirst}] changed the workspace domain link.
		'_access_0' => 396, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to the workspace.
		'_access_1' => 397, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to the workspace.
		'_restore' => 398,//[{un|ucfirst}] restored the workspace.
		'_delete' => 399,//[{un|ucfirst}] deleted the workspace.
	);

	protected static $relations_keys = array(
		'users',
	);

	protected static $allow_role = true;

////////////////////////////////////////////

	//Many(Companies) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_companies', 'companies_id', 'users_id')->withPivot('access');
	}

	//One(Companies) to Many(Projects)
	public function projects(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'companies_id');
	}

	//Many(Roles) to Many Poly (Users)
	public function roles(){
		$app = self::getApp();
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'companies_id');
	}

////////////////////////////////////////////

	public static function validName($data){
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validDomain($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^.{1,191}$/u", $data) && preg_match("/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validURL($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^[a-zA-Z0-9]{3,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}
	
	public static function isValid($form){
		if(!isset($form->name)){ self::noValidMessage(false, 'name'); } //Required
		return
			     isset($form->name) && self::validName($form->name)
			&& (!isset($form->domain) || self::validDomain($form->domain)) //Optional
			&& (!isset($form->url) || self::validURL($form->url)) //Optional
			;
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	//Insure that we only record 1 personal_private project for each company
	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		if($this->personal_private==$app->lincko->data['uid']){
			if(self::where('personal_private', $app->lincko->data['uid'])->count() > 1){
				$msg = $msg = $app->trans->getBRUT('api', 5, 2); //Cannot save more than one private workspace per user.
				\libs\Watch::php($msg, 'Companies->save()', __FILE__, true);
				$json = new Json($msg, true, 406);
				$json->render();
				return false;
			}
		}
		$return = parent::save($options);
		if($new){
			//Set the role to administrator for the Company creator
			$this->setRolePivotValue($app->lincko->data['uid'], 1, null, false);
		}
		return $return;
	}

	public function getCompanyGrant(){
		if(!isset($this->id) || $this->new_model){ //We considerate grant access by default for new company
			return 1;
		} else if($role = $this->perm()->first()){
			return $role->perm_grant;
		}
		return 0;
	}

	public function scopegetLinked($query){
		return $query
		//->with('users')
		->whereHas('users', function ($query) {
			$app = self::getApp();
			$query
			->where('users_id', $app->lincko->data['uid'])
			->where('access', 1);
		})
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			->where('personal_private', null)
			->orWhere('personal_private', $app->lincko->data['uid']);
		});
	}

	//We allow creation only
	/*
				View Create Edit Delete
		Owner	X X - -
		Admin	X - X -
		other	X - - -
	*/
	public function checkRole($level){
		$app = self::getApp();
		$level = $this->formatLevel($level);
		if($level<=0){ //Allow only read for all
			return true;
		}
		if((!isset($this->id) || $this->getCompanyGrant()>=1) && $level==1){ //Allow creation
			return true;
		}
		return parent::checkRole(3); //this will only launch error, since $level = 3
	}

	//We keep "_" because we want to store companies information in the same folder on client side (easier for JS), not separatly
	public function getCompany(){
		return $this->id;
	}

	//Do not show creation event
	public function getHistoryCreation(array $parameters = array()){
		return new \stdClass;
	}

////////////////////////////////////////////

	public static function formatURL($data){
		$data = strtolower($data);
		$data = preg_replace("/[^a-z0-9]/ui", '', $data);
		$temp = $data = trim($data);
		$i = 0;
		while(!self::validURL($temp) && self::whereUrl($temp)->count()>0 && $i<10){
			$temp = $temp.rand(1,9);
			if(strlen($temp)>16){
				$temp = $data;
			}
			$i++;
		}
		return $temp;
	}

}