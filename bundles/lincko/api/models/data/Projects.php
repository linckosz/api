<?php

namespace bundles\lincko\api\models\data;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\SoftDeletingTrait;

class Projects extends Model {

	protected $connection = 'data';

	protected $table = 'projects';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'updated_at',
		'title',
		'description',
	);
	
////////////////////////////////////////////

	//Morph => Many(Projects) to Many(Users)
	public function users(){
		return $this->morphedByMany('\\bundles\\lincko\\api\\models\\data\\Users', 'link', '_x_projects');
	}

	//Morph => Many(Projects) to Many(Compagnies)
	public function compagnies(){
		return $this->morphedByMany('\\bundles\\lincko\\api\\models\\data\\Compagnies', 'link', '_x_projects');
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

}