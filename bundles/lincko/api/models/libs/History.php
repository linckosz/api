<?php


namespace bundles\lincko\api\models\libs;

use \bundles\lincko\api\models\libs\ModelLincko;

class History extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'history';
	protected $morphClass = 'history';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array();

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

	public function getCompanyGrant(){
		return 1;
	}

	//Always allow editing
	public function checkRole($level){
		$this->checkUser();
		$level = $this->formatLevel($level);
		if(isset($this->permission_allowed[$level])){
			return $this->permission_allowed[$level];
		}
		if($level>=2){ //Cannot delete
			$this->permission_allowed[$level] = (bool) false;
			return false;
		}
		$this->permission_allowed[$level] = (bool) true;
		return true;
	}

}
