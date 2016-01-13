<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;

class Roles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'roles';
	protected $morphClass = 'roles';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $hidden = array(
		'deleted_at',
		'updated_by',
		'deleted_by',
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
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'updated_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
		'companies_id' => '\\bundles\\lincko\\api\\models\\data\\Companies',
	);

	protected $parent = 'companies';

////////////////////////////////////////////

	//Many(Roles) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_roles_x', 'roles_id', 'users_id')->withPivot('access', 'single', 'relation_id', 'relation_type');
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
		//->with('companies')
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_at condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			->where('companies_id', $app->lincko->data['company_id'])
			->orWhere('shared', 1);
		});
	}

	public function getCompany(){
		$app = self::getApp();
		return $this->companies_id;
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$new = !isset($this->id);
		if($new){
			$this->companies_id = intval($app->lincko->data['company_id']);
		} else {
			$this->companies_id = intval($this->getCompany());
		}
		$this->shared = 0;
		$this->perm_grant = 0;
		$this->perm_companies = 0;
		$return = parent::save($options);
		return $return;
	}

	//We allow all for admin, only view for other
	/*
				View Create Edit Delete
		Owner	X - - -
		Admin	X X X X
		other	X - - -
	*/
	public function checkRole($level){
		$app = self::getApp();
		$level = $this->formatLevel($level);
		if($level<=0){ //Allow only read for all
			return true;
		}
		if($this->getCompanyGrant()>=1){ //Allow for administrator (grant access)
			return true;
		}
		return parent::checkRole(3); //this will only launch error, since $level = 3
	}

}