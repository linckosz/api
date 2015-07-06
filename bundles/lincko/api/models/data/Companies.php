<?php

namespace bundles\lincko\api\models\data;

use \libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

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

////////////////////////////////////////////

	//Many(Companies) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_companies', 'companies_id', 'users_id');
	}

	//One(Companies) to Many(Projects)
	public function projects(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'companies_id');
	}

////////////////////////////////////////////

	public static function validName($data){
		return preg_match("/^.{1,104}$/u", $data);
	}

	//Optional
	public static function validDomain($data){
		if(empty($data)){ return true; }
		return preg_match("/^.{1,191}$/u", $data) && preg_match("/^[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,4})$/ui", $data);
	}

	//Optional
	public static function validURL($data){
		if(empty($data)){ return true; }
		return preg_match("/^[a-zA-Z0-9]{3,104}$/u", $data);
	}
	
	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->domain)){ $optional = self::validDomain($form->domain); }
		if($optional && isset($form->url)){ $optional = self::validURL($form->url); }
		return
			   $optional
			&& isset($form->name) && self::validName($form->name)
			;
	}

////////////////////////////////////////////

	//We have to include Company 0 because it's a shared one by default
	public function scopegetLinked($query){
		return $query->orwhereHas('users', function ($query) {
			$query->theUser();
		});
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