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

	//Always allow editing
	public function checkRole($level){
		return true;
	}

}
