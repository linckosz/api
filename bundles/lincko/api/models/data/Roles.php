<?php


namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Companies;

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

	protected $show_field = 'name';

	protected $search_fields = array(
		'name',
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

	protected static $permission_sheet = array(
		0, //[R] owner
		3, //[RCUD] grant
		0, //[R] max allow
	);

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

	public function checkAccess(){
		if(!Companies::find($this->getCompany())->checkAccess()){
			$this->accessibility = (bool) false;
		}
		return parent::checkAccess();
	}

	public function getCompany(){
		$app = self::getApp();
		if(is_null($this->companies_id)){ //This is for the shared roles
			$compid = $app->lincko->data['company_id'];
		} else {
			$compid = $this->companies_id;
		}
		return $compid;
		
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
		self::setForceReset(true);
		return $return;
	}

}