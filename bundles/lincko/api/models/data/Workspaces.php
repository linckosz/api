<?php


namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \libs\Datassl;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\Inform;

class Workspaces extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'workspaces';
	protected $morphClass = 'workspaces';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'updated_at',
		'name',
		'domain',
		'url',
		'default_role',
		'cus_color_one',
		'cus_color_two',
		'cus_logo',
		'_users',
		'_parent',
		'_perm',
	);

	// CUSTOMIZATION //

	protected static $hide_extra = array(
		'temp_id',
		'name',
		'url',
		'default_role',
	);

	protected $model_integer = array(
		'default_role',
		'cus_logo',
	);

	protected $contactsLock = true; //Do not allow to delete users from contact list

	protected $name_code = 300;

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => array(true, 301), //[{un}] created a new workspace
		'_' => array(true, 302), //[{un}] modified the workspace
		'name' => array(true, 303), //[{un}] changed the workspace name
		'domain' => array(true, 304), //[{un}] changed the workspace domain link
		'pivot_users_access_0' => array(true, 396), //[{un}] blocked [{cun}]'s access to the workspace
		'pivot_users_access_1' => array(true, 397), //[{un}] authorized [{cun}]'s access to the workspace
		'_restore' => array(true, 398), //[{un}] restored the workspace
		'_delete' => array(true, 399), //[{un}] deleted the workspace
	);

	protected static $parent_list_get = array('users');

	protected static $allow_role = true;

	//Turn true for paid account only the time the account is created
	protected $allow_workspace_creation = false;
	
	protected static $permission_sheet = array(
		0, //[R] owner
		2, //[RCU] max allow || super
	);

	protected static $has_perm = true;

	//For remote file access, it will record an array of SFTP encrypted values
	protected static $remote_sftp = false;

	protected static $server_path = null;

	protected static $workspace = false;

	protected static $dependencies_visible = array(
		'users' => array('users_x_workspaces', array('super')),
	);

////////////////////////////////////////////

	//Many(Workspaces) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_workspaces', 'workspaces_id', 'users_id')->withPivot('access', 'super');
	}

	//One(Workspaces) to Many(Projects)
	public function projects(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'parent_id');
	}

	//One(Workspaces) to Many(Namecards)
	public function namecards(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Namecards', 'parent_id');
	}

	//Many(Roles) to Many Poly (Users)
	public function roles(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'parent_id');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->name) && !self::validChar($form->name, true))
			|| (isset($form->domain) && !self::validDomain($form->domain, true))
			|| (isset($form->url) && !self::validURL($form->url, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	public function allowWorkspaceCreation(){
		$this->allow_workspace_creation = true;
	}

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function save(array $options = array()){
		$app = ModelLincko::getApp();
		$new = !isset($this->id);
		$return = parent::save($options);
		if($new){
			//Set the role to administrator for the Workspace creator
			$this->setRolePivotValue($app->lincko->data['uid'], 1, null, false);
		}
		return $return;
	}

	public function checkAccess($show_msg=true){
		$app = ModelLincko::getApp();
		if(!is_bool($this->accessibility)){
			if(!isset($this->id)){ //Allow access for new workspace with authorization
				$this->accessibility = (bool) false;
				if($this->allow_workspace_creation){
					$this->accessibility = (bool) true;
				}
			}
		}
		return parent::checkAccess($show_msg);
	}

	public function scopegetItems($query, $list=array(), $get=false){
		$app = ModelLincko::getApp();
		if((isset($app->lincko->api['x_i_am_god']) && $app->lincko->api['x_i_am_god']) || (isset($app->lincko->api['x_'.$this->getTable()]) && $app->lincko->api['x_'.$this->getTable()])){
			$query = $query
			->whereHas('users', function ($query) {
				$app = ModelLincko::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where('access', 1);
			});
		} else {
			$query = $query->whereId(-1); //We reject if no specific access
		}
		//We do not allow to gather deleted workspaces
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
			}
			return $result;
		} else {
			return $query;
		}
	}

	public function checkPermissionAllow($level, $msg=false){
		$app = ModelLincko::getApp();
		$this->checkUser();
		if(!$this->checkAccess()){
			return false;
		}
		$level = $this->formatLevel($level);
		if($level==1){
			if($this->allow_workspace_creation){ //Allow creation (for paid account)
				return true;
			}
			return false;
		}
		return parent::checkPermissionAllow($level);
	}

	//Do not show creation event
	public function getHistoryCreationCode(&$items=false){
		return false;
	}

	//Do not show creation event
	public function getHistoryCreation($history_detail=false, array $parameters = array(), &$items=false){
		return new \stdClass;
	}

	public function getContactsVisibility(){
		$app = ModelLincko::getApp();
		if($this->id == $app->lincko->data['workspace_id']){
			return true; //Make all user linked to the workspace visible by the user into the contact list
		} else {
			return false;
		}
	}

////////////////////////////////////////////

	public static function formatURL($data){
		$data = strtolower($data);
		$data = preg_replace("/[^a-z0-9]/ui", '', $data);
		$temp = $data = trim($data);
		$i = 0;
		while(!self::validURL($temp) && self::whereUrl($temp)->take(1)->count()>0 && $i<10){
			$temp = $temp.rand(1,9);
			if(strlen($temp)>16){
				$temp = $data;
			}
			$i++;
		}
		return $temp;
	}

	public static function getWorkspace($force=false){
		$app = ModelLincko::getApp();
		if($force || !static::$workspace){
			if($app->lincko->data['workspace_id']>0 && $workspace = Workspaces::where('id', $app->lincko->data['workspace_id'])->first()){
				static::$workspace = $workspace;
			}
		} 
		return static::$workspace;
	}

	public static function setServerPath($path=null){
		self::$server_path = $path;
	}

	public static function setSFTP($attributes){
		if(!self::$remote_sftp && isset($attributes['sftp_host']) && isset($attributes['sftp_port']) && isset($attributes['sftp_pwd'])){
			self::$remote_sftp = array(
				'host' => $attributes['sftp_host'],
				'port' => $attributes['sftp_port'],
				'pwd' => $attributes['sftp_pwd'],
			);
		}
	}

	public static function getSFTP(){
		$sftp = false;
		if(self::$server_path!=null && self::$remote_sftp && !isset(self::$remote_sftp['sftp'])){
			$app = ModelLincko::getApp();
			$conn = ssh2_connect(Datassl::decrypt_smp(self::$remote_sftp['host']), Datassl::decrypt_smp(self::$remote_sftp['port']));
			ssh2_auth_password($conn, 'sftp', Datassl::decrypt_smp(self::$remote_sftp['pwd']));
			self::$remote_sftp['conn'] = $conn;
			$sftp = ssh2_sftp($conn);
			self::$remote_sftp['sftp'] = $sftp;
			$path = self::$server_path;
			$app->lincko->filePathPrefix = 'ssh2.sftp://'.$sftp;
			$app->lincko->filePath = $path;
		}
		if(isset(self::$remote_sftp['sftp'])){
			$sftp = self::$remote_sftp['sftp'];
		}
		return $sftp;
	}

	public static function getCONN(){
		$conn = false;
		if(!isset(self::$remote_sftp['conn'])){
			self::getSFTP();
		}
		if(isset(self::$remote_sftp['conn'])){
			$conn = self::$remote_sftp['conn'];
		}
		return $conn;
	}

	public static function getPrefixSFTP(){
		$sftp = '';
		if(isset(self::$remote_sftp['sftp'])){
			$host = Datassl::decrypt_smp(self::$remote_sftp['host']);
			$port = Datassl::decrypt_smp(self::$remote_sftp['port']);
			$pwd = Datassl::decrypt_smp(self::$remote_sftp['pwd']);
			$sftp = "sftp://sftp:$pwd@$host:$port";
		}
		return $sftp;
	}

}
