<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Companies extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'companies';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'name',
		'domain',
		'url',
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

////////////////////////////////////////////

	//Many(Companies) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_companies', 'companies_id', 'users_id')->withPivot('access');
	}

	//One(Companies) to Many(Projects)
	public function projects(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'companies_id');
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

	public function scopegetLinked($query){
		return $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_by condition in Data.php
			$query
			->whereHas('users', function ($query) {
				$app = self::getApp();
				$uid = $app->lincko->data['uid'];
				$query->where('users_id', $uid)->where('access', 1);
			})
			->orWhere('id', 0); //Need to include the public folder (company 0) by default
		});
	}

	//Get all users that are linked to the company
	//We have to exclude Company 0 because it's a shared one by default
	public function getUsersContacts(){
		$contacts = parent::getUsersContacts();
		$list = $this->users()->where('companies_id', '<>', 0)->get(); //Exclude the shared company "0" which should never actually appear, but it's an additional security. If not all users will appear on client side (security issue), and thousands of data will take a while to download.
		foreach($list as $key => $value) {
			$id = $value->id;
			$contacts->$id = $this->getContactsInfo();
		}
		return $contacts;
	}

	//We keep "_" because we want to store companies information in teh same folder on client side (easier for JS), not separatly
	public function getCompany(){
		return '_';
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