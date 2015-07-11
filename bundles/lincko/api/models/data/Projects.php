<?php

namespace bundles\lincko\api\models\data;

use \libs\ModelLincko;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

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
		'-title',
		'-description',
		'personal_private',
	);

	// CUSTOMIZATION //
	protected $archive = array(
		'-title',
		'-description',
	);
	
////////////////////////////////////////////

	//Many(Projects) to One(Companies)
	public function companies(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Companies', 'companies_id');
	}

	//One(Projects) to Many(Tasks)
	public function tasks(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'projects_id');
	}

	//Many(Tasks) to Many(Tasks)
	public function userAccess(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'projects_x_users_access', 'projects_id', 'users_id')->withPivot('access');
	}


////////////////////////////////////////////
	public static function validTitle($title){
		return preg_match("/^\S{1,104}$/u", $title);
	}

	//Optional
	public static function validDescription($data){
		if(empty($data)){ return true; }
		return true;
	}

	public static function isValid($form){
		$optional = true;
		if($optional && isset($form->description)){ $optional = self::validDescription($form->description); }
		return
			   $optional
			&& isset($form->title) && self::validTitle($form->title)
			;
	}

////////////////////////////////////////////

	//The projects linked to the company 0 are the 
	public function scopegetLinked($query){
		return $query
		->where(function ($query) {
			//Get personnal project
			$query
			->where('created_by', '=', \Slim\Slim::getInstance()->lincko->data['uid'])
			->where('personal_private', 1);
		})
		->orWhere(function ($query) {
			//Exclude private project
			$query
			->whereHas('userAccess', function ($query){
				$query->where('access', 1);
			})
			->where('personal_private', 0)
			->whereHas('companies', function ($query) {
				$query->getLinked();
			});
		});
	}

	public function getCompany(){
		return $this->companies_id;
	}

}
