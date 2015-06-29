<?php

namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Users extends Model {

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

	//One(Users) to Many(ChatsComments)
	public function chatsComments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\ChatsComments', 'users_id');
	}

	//Many(Users) to Many(Compagnies)
	public function compagnies(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Compagnies', 'users_x_compagnies', 'users_id', 'compagnies_id');
	}

	//Morph => Many(Users) to Many(Projects)
	public function projects(){
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'link', '_x_projects');
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

	public static function getUser(){
		$app = \Slim\Slim::getInstance();
		if(isset($app->lincko->data['uid'])){
			return self::find($app->lincko->data['uid']);
		}
		return false;
	}

}