<?php

namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Compagnies extends Model {

	protected $connection = 'data';

	protected $table = 'compagnies';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'updated_at',
		'name',
		'domain',
	);

////////////////////////////////////////////

	//Many(Compagnies) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_compagnies', 'users_id', 'compagnies_id');
	}

	//Morph => Many(Compagnies) to Many(Projects)
	public function projects(){
		return $this->morphToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'link', '_x_projects');
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
	
	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->domain)){ $optional = self::validDomain($form->domain); }
		return
			   $optional
			&& isset($form->name) && self::validName($form->name)
			;
	}

////////////////////////////////////////////

}