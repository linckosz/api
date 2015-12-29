<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Roles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'roles';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'created_at',
		'created_by',
		'role',
		'companies_id',
		'users_id',
		'_edit',
		'_delete',
	);

	// CUSTOMIZATION //

	protected $show_field = 'role';

	protected $search_fields = array(
		'role',
	);

	protected $archive = array(
		'created_at' => 701, //[{un|ucfirst}] created a new role.
		'_' => 702,//[{un|ucfirst}] modified a role.
		'_access_0' => 796, //[{un|ucfirst}] blocked [{[{cun|ucfirst}]}]'s access to a role.
		'_access_1' => 797, //[{un|ucfirst}] authorized [{[{cun|ucfirst}]}]'s access to a role.
		'_restore' => 798,//[{un|ucfirst}] restored a role.
		'_delete' => 799,//[{un|ucfirst}] deleted a role.
	);

	protected static $foreign_keys = array(
		'companies_id' => '\\bundles\\lincko\\api\\models\\data\\Companies',
		'users_id' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

////////////////////////////////////////////

	//Many(Roles) to One(Users)
	public function users(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Users', 'users_id');
	}

	//Many(Roles) to One(Companies)
	public function companies(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Companies', 'companies_id');
	}


////////////////////////////////////////////
	public static function validName($data){
		$return = preg_match("/^.{1,104}$/u", $data);
		return self::noValidMessage($return, __FUNCTION__);
	}
	
	public static function isValid($form){
		if(!isset($form->name)){ self::noValidMessage(false, 'name'); } //Required
		return
			     isset($form->name) && self::validName($form->name)
			;
	}

////////////////////////////////////////////

	public function scopegetLinked($query){
		return $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			->where('companies_id', $app->lincko->data['company_id'])
			->orWhere('companies_id', null);
		});
	}

	public function getCompany(){
		$app = self::getApp();
		if(!is_null($this->companies_id)){
			return $this->companies_id;
		} else {
			return $app->lincko->data['company_id'];
		}
	}

}