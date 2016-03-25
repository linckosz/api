<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Companies;

class PivotUsersRoles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users_x_roles_x';
	protected $morphClass = 'users_x_roles_x';

	protected $primaryKey = 'id';

	protected $visible = array();

	protected static $permission_sheet = array(
		0, //[R] owner
		0, //[R] grant
		0, //[R] max allow
	);

	//Authorized by default since it's not part of a company
	protected static $permission_grant = 1;
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

////////////////////////////////////////////

	public function scopegetLinked($query){
		$app = self::getApp();
		return $query->where('users_id', $app->lincko->data['uid'])->where('access', 1);
	}

	public function scopesameCompany($query){
		$app = self::getApp();
		return $query->where('relation_type', 'companies')->where('relation_id', $app->lincko->data['company_id'])->where('access', 1);
	}

	public static function getCompanyRoles(){
		$app = self::getApp();
		$comp_users = Companies::find($app->lincko->data['company_id'])->users()->get();
		$users_list = array();
		foreach ($comp_users as $comp_user) {
			$users_list[] = $comp_user->getKey();
		}
		if(!in_array($app->lincko->data['uid'], $users_list)){
			$users_list[] = $app->lincko->data['uid'];
		}
		return self::whereIn('users_id', $users_list)->get();
	}

}
