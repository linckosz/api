<?php
// Category 7

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;

use Illuminate\Database\Capsule\Manager as Capsule;

class Users extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users';
	protected $morphClass = 'users';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'username',
		'firstname',
		'lastname',
		'gender',
	);

	// CUSTOMIZATION //

	protected $search_fields = array(
		'username',
		'firstname',
		'lastname',
	);

	protected $contactsLock = false; //By default do not lock the user

	protected $contactsVisibility = false; //By default do not make the user visible

	protected $archive = array(
		'created_at' => 601,  //[{un|ucfirst}] joined @@title~~.
		'_' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'username' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'firstname' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'lastname' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'gender' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'email' => 602,//[{un|ucfirst}] modified [{hh}] profile.
		'_access_0' => 696, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to [{hh}] profile.
		'_access_1' => 697, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to [{hh}] profile.
		'_restore' => 698,//[{un|ucfirst}] restored [{hh}] profile.
		'_delete' => 699,//[{un|ucfirst}] deleted [{hh}] profile.
	);

	protected static $relations_keys = array();
	
////////////////////////////////////////////

	//One(Users) to One(UsersLog)
	//Warning: This does not work because the 2 tables are in 2 different databases
	public function usersLog(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\UsersLog', 'username_sha1');
	}

	//Many(Users) to Many(Chats)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'users_x_chats', 'users_id', 'chats_id')->withPivot('access');
	}

	//One(Users) to Many(ChatsComments)
	public function chatsComments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\ChatsComments', 'created_by');
	}

	//Many(Users) to Many(Companies)
	public function companies(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Companies', 'users_x_companies', 'users_id', 'companies_id')->withPivot('access');
	}

	//Many(Users) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'users_x_projects', 'users_id', 'projects_id')->withPivot('access');
	}

	//Many(Users) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'users_x_tasks', 'users_id', 'tasks_id')->withPivot('access', 'in_charge', 'approver');
	}

	//Many(Users) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id', 'users_id_link')->withPivot('access');
	}

	//Many(Users) to Many(Users)
	public function usersLinked(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id_link', 'users_id')->withPivot('access');
	}

	//Many(Users) to Many(Companies)
	public function roles(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'users_x_roles_x', 'roles_id', 'companies_id')->withPivot('access');
	}

////////////////////////////////////////////

	public static function validEmail($data){
		$return = preg_match("/^.{1,191}$/u", $data) && preg_match("/^.{1,100}@.*\..{2,4}$/ui", $data) && preg_match("/^[_a-z0-9-%+]+(\.[_a-z0-9-%+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validUsername($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^\S{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validFirstname($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validLastname($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	//Optional
	//empty checks $data if !isset or "", returning true makes the value optional
	public static function validGender($data){
		$return = true;
		if(empty($data)){ return $return = true; }
		$return = preg_match("/^0|1$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}

	public static function isValid($form){
		if(!isset($form->email)){ self::noValidMessage(false, 'email'); } //Required
		return
			     isset($form->email) && self::validEmail($form->email)
			&& (!isset($form->username) || self::validUsername($form->username)) //Optional
			&& (!isset($form->firstname) || self::validFirstname($form->firstname)) //Optional
			&& (!isset($form->lastname) || self::validLastname($form->lastname)) //Optional
			&& (!isset($form->gender) || self::validGender($form->gender)) //Optional
			;
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	public function scopegetLinked($query){
		return $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_by condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			//->with('usersLinked')
			->whereHas('usersLinked', function ($query) {
				$app = self::getApp();
				$query->where('users_id', $app->lincko->data['uid'])->where('access', 1);
			})
			->orWhere('id', $app->lincko->data['uid']);
		});
	}

	public function getContactsLock(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			$this->contactsLock = true; //Do not allow to delete the user itself on client side
		}
		return $this->contactsLock;
	}

	public function getContactsVisibility(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			$this->contactsVisibility = false; //No need to make the user visible in the list on client side
		}
		return $this->contactsVisibility;
	}

	//Get all users that are added as contact by the user
	public function getUsersContacts(){
		$app = self::getApp();
		$contacts = parent::getUsersContacts();
		$id = $this->id;
		$contacts->$id = $this->getContactsInfo();
		$list = $this->users()->get();
		foreach($list as $key => $value) {
			$id = $value->id;
			$contacts->$id = $this->getContactsInfo();
			if($this->id != $app->lincko->data['uid'] && isset($value->pivot) && $value->pivot->access){
				$contacts->$id->contactsVisibility = true;
			}
		}
		return $contacts;
	}

	public function getForceSchema(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			return $this->force_schema;
		}
		return false;
	}

////////////////////////////////////////////

	public function scopetheUser($query){
		$app = self::getApp();
		if(isset($app->lincko->data['uid'])){
			return $query->whereId($app->lincko->data['uid']);
		}
		return $query->whereId(-1); //It will force an error since the user -1 does not exists
	}

	public static function getUser(){
		return self::theUser()->first();
	}

	protected function get_HisHer(){
		$app = self::getApp();
		if($this->gender == 0){
			return $app->trans->getBRUT('api', 7, 1); //his
		} else {
			return $app->trans->getBRUT('api', 7, 2); //her
		}
	}

	public function setHistory($key=null, $new=null, $old=null, array $parameters = array()){
		$parameters['hh'] = $this->get_HisHer();
		parent::setHistory($key, $new, $old, $parameters);
	}

	//Do not show creation event
	public function getHistoryCreation(array $parameters = array()){
		return new \stdClass;
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$return = null;
		$db = Capsule::connection($this->connection);
		$db->beginTransaction();
		try {
			if(isset($this->id)){
				$return = parent::save($options);
			} else {
				$return = parent::save($options);
				$app->lincko->data['uid'] = $this->id;

				$company = new Companies();
				$company->personal_private = $this->id;
				$company->name = $this->username;
				$company->save();
				
				$app->lincko->data['company'] = $company->url;
				$app->lincko->data['company_id'] = intval($company->id);
				
				$project = new Projects();
				$project->title = 'Private';
				$project->companies_id = $company->id;
				$project->personal_private = $this->id;
				$project->save();

				$app->lincko->data['user_log']->save();
			}
			$db->commit();
		} catch(\Exception $e){
			$return = null;
			$db->rollback();
		}
		return $return;
	}

	public function toJson($detail=true, $options = 0){
		$app = self::getApp();
		$temp = parent::toJson($detail, $options);
		$temp = json_decode($temp);
		$temp->contactsLock = $this->getContactsLock();
		$temp->contactsVisibility = $this->getContactsVisibility();
		//Do not show email for all other users
		if($this->id == $app->lincko->data['uid']){
			$temp->email = $this->email;
		} else {
			$temp->email = "";
		}
		$temp = json_encode($temp, $options);
		return $temp;
	}

}