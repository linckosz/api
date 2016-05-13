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

	protected $accessibility = true; //Always allow History creation

	protected static $foreign_keys = array(
		'created_by' => '\\bundles\\lincko\\api\\models\\data\\Users',
	);

	protected static $permission_sheet = array(
		0, //[R] owner
		1, //[RC] max allow || super
	);

	protected static $parent_list = array('users', 'comments', 'chats', 'workspaces', 'projects', 'tasks', 'notes', 'files');
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	//We do not record history
	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		return true;
	}

	//We do not attach
	protected function pivots_save(array $parameters = array()){
		return true;
	}

	public function save(array $options = array()){
		//Do accept only new history, disallow modification
		if(isset($this->id)){
			return false;
		}
		$return = parent::save($options);
		return $return;
	}

	public function checkPermissionAllow($level, $msg=false){
		$this->checkUser();
		$level = $this->formatLevel($level);
		if($level==1){ //Creation
			return true;
		}
		return false;
	}

}
