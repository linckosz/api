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

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] grant
		1, //[RC] max allow
	);
	
	//Authorized by default since it's not part of a company
	protected static $permission_grant = 1;
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){}
	public function restore(){}

}
