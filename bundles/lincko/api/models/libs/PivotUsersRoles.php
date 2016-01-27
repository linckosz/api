<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;

class PivotUsersRoles extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users_x_roles_x';
	protected $morphClass = 'users_x_roles_x';

	protected $primaryKey = 'id';

	protected $visible = array();
	
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

}
