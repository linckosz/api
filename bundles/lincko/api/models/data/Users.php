<?php

namespace bundles\lincko\api\models\data;

use \libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Users extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'username',
		'firstname',
		'lastname',
	);

	protected $contactsLock = false; //By default do not lock the user

	protected $contactsVisibility = false; //By default do not make the user visible
	
////////////////////////////////////////////

	//One(Users) to One(UsersLog)
	//Warning: This does not work because the 2 tables are in 2 different databases
	public function usersLog(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\UsersLog', 'username_sha1');
	}

	//Many(Users) to Many(Chats)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'users_x_chats', 'users_id', 'chats_id');
	}

	//Many(Users) to Many(Companies)
	public function companies(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Companies', 'users_x_companies', 'users_id', 'companies_id');
	}

	//Many(Users) to Many(Users)
	public function usersContacts(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id', 'users_id_contacts')->withPivot('access');;
	}

////////////////////////////////////////////

	public static function validEmail($data){
		return preg_match("/^.{1,191}$/u", $data) && preg_match("/^.{1,100}@.*\..{2,4}$/ui", $data) && preg_match("/^[_a-z0-9-%+]+(\.[_a-z0-9-%+]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", $data);
	}

	//Optional
	public static function validUsername($data){
		if(empty($data)){ return true; }
		return preg_match("/^\S{1,104}$/u", $data);
	}

	//Optional
	public static function validFirstname($data){
		if(empty($data)){ return true; }
		return preg_match("/^.{1,104}$/u", $data);
	}

	//Optional
	public static function validLastname($data){
		if(empty($data)){ return true; }
		return preg_match("/^.{1,104}$/u", $data);
	}

	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->username)){ $optional = self::validUsername($form->username); }
		if($optional && isset($form->firstname)){ $optional = self::validFirstname($form->firstname); }
		if($optional && isset($form->lastname)){ $optional = self::validLastname($form->lastname); }
		return
			   $optional
			&& isset($form->email) && self::validEmail($form->email)
			;
	}

////////////////////////////////////////////

	//We have to rewritte the function "scopegetLinked" from parent class, because it's called statically
	public static function getLinked(){
		return self::theUser();
	}

	//Get all users that are added by the user
	public function getUsersContacts(){
		$usersContacts = parent::getUsersContacts();
		$list = Users::getUser()->usersContacts()->where('users_x_users.access', 1)->get();
		foreach($list as $key => $value) {
			$id = $value->id;
			$usersContacts->$id = $this->getContactsInfo();
		}
		$id = $this->id;
		$usersContacts->$id = new \stdClass;
		$usersContacts->$id->contactsLock = true; //Do not allow to delete the user itself
		$usersContacts->$id->contactsVisibility = false; //No need to make the user visible in the list
		return $usersContacts;
	}

	//We do not need "addMultiDependencies" since getUsersContacts do this job already

////////////////////////////////////////////

	public function scopetheUser($query){
		if(isset(\Slim\Slim::getInstance()->lincko->data['uid'])){
			return $query->whereId(\Slim\Slim::getInstance()->lincko->data['uid']);
		}
		return $query->whereId(-1); //It will force an error since the user -1 does not exists
	}

	public static function getUser(){
		return self::theUser()->first();
	}

}