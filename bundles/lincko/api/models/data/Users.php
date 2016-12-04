<?php
// Category 7

namespace bundles\lincko\api\models\data;

use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\Onboarding;
use \bundles\lincko\api\models\Notif;

use Illuminate\Database\Capsule\Manager as Capsule;

class Users extends ModelLincko {

	protected $connection = 'data';

	protected $table = 'users';
	protected $morphClass = 'users';

	protected $primaryKey = 'id';

	public $timestamps = true;

	protected $visible = array(
		'id',
		'temp_id',
		'updated_at',
		'username',
		'firstname',
		'lastname',
		'gender',
		'profile_pic',
		'timeoffset',
		'resume',
		'_parent',
		'_lock',
		'_visible',
		'_invitation',
	);

	// CUSTOMIZATION //

	protected $search_fields = array(
		'username',
		'firstname',
		'lastname',
	);

	protected static $prefix_fields = array(
		'username' => '-username',
		'firstname' => '-firstname',
		'lastname' => '-lastname',
	);

	protected static $hide_extra = array(
		'temp_id',
		'_lock',
		'_visible',
		'_invitation',
		'username',
		'firstname',
		'lastname',
		'email',
	);

	protected $contactsLock = false; //By default do not lock the user

	protected $contactsVisibility = false; //By default do not make the user visible

	protected static $invitation_list = false; //Get List of invitation

	protected $name_code = 600;

	protected static $archive = array(
		'created_at' => 601,  //[{un}] joined @@title~~
		'_' => 602,//[{un}] modified [{hh}] profile
		'username' => 602,//[{un}] modified [{hh}] profile
		'firstname' => 602,//[{un}] modified [{hh}] profile
		'lastname' => 602,//[{un}] modified [{hh}] profile
		'gender' => 602,//[{un}] modified [{hh}] profile
		'email' => 602,//[{un}] modified [{hh}] profile
		'timeoffset' => 602,//[{un}] modified [{hh}] profile
		'resume' => 602,//[{un}] modified [{hh}] profile
		'pivot_invitation_1' => 695, //[{un}] has invited [{[{cun}]}]
		'pivot_access_0' => 696, //[{un}] blocked [{[{cun}]}]'s access to [{hh}] profile
		'pivot_access_1' => 697, //[{un}] authorized [{[{cun}]}]'s access to [{hh}] profile
		'_restore' => 698,//[{un}] restored [{hh}] profile
		'_delete' => 699,//[{un}] deleted [{hh}] profile
	);

	protected $model_integer = array(
		'fav',
		'gender',
		'profile_pic',
		'timeoffset',
		'resume',
	);

	protected $model_boolean = array(
		'in_charge',
		'approver',
		'_invitation',
	);

	protected static $permission_sheet = array(
		2, //[RCU] owner
		1, //[RC] max allow || super
	);

	protected static $has_perm = false;

	protected static $me = false;
	
////////////////////////////////////////////

	protected static $pivot_users_suffix = '_id_link';

	//One(Users) to One(UsersLog)
	//Warning: This does not work because the 2 tables are in 2 different databases
	public function usersLog(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\UsersLog', 'username_sha1');
	}

	//Many(Users) to Many(Chats)
	public function chats(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Chats', 'users_x_chats', 'users_id', 'chats_id')->withPivot('access', 'fav');
	}

	//One(Users) to Many(comments)
	public function comments(){
		return $this->hasMany('\\bundles\\lincko\\api\\models\\data\\Comments', 'created_by');
	}

	//Many(Users) to Many(Workspaces)
	public function workspaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Workspaces', 'users_x_workspaces', 'users_id', 'workspaces_id')->withPivot('access', 'super');
	}

	//Many(Users) to Many(Projects)
	public function projects(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Projects', 'users_x_projects', 'users_id', 'projects_id')->withPivot('access', 'fav');
	}

	//Many(Users) to Many(Tasks)
	public function tasks(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Tasks', 'users_x_tasks', 'users_id', 'tasks_id')->withPivot('access', 'fav', 'in_charge', 'approver');
	}

	//Many(Users) to Many(Notes)
	public function notes(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Notes', 'users_x_notes', 'users_id', 'notes_id')->withPivot('access', 'fav');
	}

	//Many(Users) to Many(Spaces)
	public function spaces(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Spaces', 'users_x_spaces', 'users_id', 'spaces_id')->withPivot('access', 'fav', 'hide');
	}

	//Many(Users) to Many(Users)
	public function users(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id', 'users_id_link')->withPivot('access', 'invitation');
	}

	//Many(Users) to Many(Users)
	public function usersLinked(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id_link', 'users_id')->withPivot('access', 'invitation');
	}

	//Many(Users) to Many(Roles)
	public function perm($users_id=false){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Roles', 'users_x_roles_x', 'users_id', 'roles_id')->withPivot('access', 'relation_id', 'parent_type', 'single');
	}

	//One(Projects) to Many(Tasks)
	public function profile(){
		return $this->belongsTo('\\bundles\\lincko\\api\\models\\data\\Files', 'profile_pic');
	}

////////////////////////////////////////////

	public static function isValid($form){
		if(
			   (isset($form->id) && !self::validNumeric($form->id, true))
			|| (isset($form->email) && !self::validEmail($form->email, true))
			|| (isset($form->password) && !self::validPassword($form->password, true))
			|| (isset($form->username) && !self::validChar($form->username, true) && !self::validTextNotEmpty($form->username, true))
			|| (isset($form->firstname) && !self::validChar($form->firstname, true))
			|| (isset($form->lastname) && !self::validChar($form->lastname, true))
			|| (isset($form->gender) && !self::validBoolean($form->gender, true))
			|| (isset($form->timeoffset) && !self::validNumeric($form->timeoffset, true))
			|| (isset($form->resume) && !self::validNumeric($form->resume, true))
		){
			return false;
		}
		return true;
	}

////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function scopegetItems($query, $list=array(), $get=false){
		$app = self::getApp();
		$query = $query
		->where(function ($query) { //Need to encapsule the OR, if not it will not take in account the updated_by condition in Data.php because of later prefix or suffix
			$app = self::getApp();
			$query
			//->with('usersLinked') //It affects heavily speed performance
			->whereHas('usersLinked', function ($query) {
				$app = self::getApp();
				$query
				->where('users_id', $app->lincko->data['uid'])
				->where(function ($query) {
					$query
					->where('access', 1)
					->orWhere('invitation', 1);
				});
			})
			->orWhere('users.id', $app->lincko->data['uid']);
		});
		//We do not allow to gather deleted users
		if($get){
			$result = $query->get();
			foreach($result as $key => $value) {
				$result[$key]->accessibility = true;
				if($result[$key]->id != $app->lincko->data['uid']){
					$result[$key]->contactsVisibility = true; //We make all users inside the userlist visible, expect the user itself
				} else {
					$result[$key]->contactsLock = true; //We do not allow to reject the user itself
				}
			}
			return $result;
		} else {
			return $query;
		}
	}

	//List all users directly attached to the corresponding object
	//toto => heavy operation and not sure if it's usefull if we can previously knows the list of users
	public function scopegetUsers($query, $list=array()){
		$this->var['list'] = $list;
		foreach ($list as $key => $value) {
			$this->var['table'] = $key;
			if($key=='roles') { $key = 'perm'; }
			if($key=='users') {
				$query = $query->orWhereIn('users.id', $value);
				continue;
			}
			$this->var['key'] = $key;
			if(method_exists(get_called_class(), $this->var['key'])){
				$query = $query
				->orWhereHas($this->var['key'], function ($query) {
					$query
					->whereIn($this->var['table'].'.id', $this->var['list'][$this->var['table']]);
				});
			}
		}
		return $query;
	}

	public static function getUsersContacts($list=array(), $visible=array()){
		$app = self::getApp();
		$users = self::whereIn('id', $list)->get();
		foreach($users as $key => $value) {
			$users[$key]->accessibility = true; //Because getLinked() only return all with Access allowed
			if($value->id == $app->lincko->data['uid']){
				$users[$key]->contactsLock = true; //We do not allow to reject the user itself
			} else if(in_array($value->id, $visible)){
				$users[$key]->contactsVisibility = true; //We make all users inside the userlist visible, expect the user itself
			}
		}
		return $users;
	}

	public function getContactsLock(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			$this->contactsLock = true; //Do not allow to delete the user itself on client side
		}
		return $this->contactsLock;
	}

	public function getContactsVisibility(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			$this->contactsVisibility = false; //Do not allow the user to talk to himself (technicaly, cannot attached comment to yourself, use MyPlaceholder instead)
		}
		return $this->contactsVisibility;
	}

	public static function getClass($class=false){
		if($class=='usersLinked'){
			return '\\bundles\\lincko\\api\\models\\data\\Users';
		}
		return parent::getClass($class);
	}

	public function setInvitation(){
		$app = self::getApp();
		$this->_invitation = false;
		if(self::$invitation_list===false){
			self::$invitation_list = array();
			if($theUser = $this->getUser()){
				if($contacts = $theUser->users){
					foreach ($contacts as $key => $value) {
						self::$invitation_list[$value->id] = (boolean) $value->pivot->invitation;
					}
				}
			}
		}
		if(!isset(self::$invitation_list[$this->id])){
			self::$invitation_list[$this->id] = false;
		}
		$this->_invitation = self::$invitation_list[$this->id];
		if($this->_invitation){
			$this->contactsVisibility = false;
			if(!isset(self::$contacts_list[$this->id])){ self::$contacts_list[$this->id] = array(); }
			$this->_lock = self::$contacts_list[$this->id][0] = false;
			$this->_visible = self::$contacts_list[$this->id][1] = false;
		}
		return $this->_invitation;
	}

	public function updateContactAttributes(){
		if(isset(self::$contacts_list[$this->id])){
			$this->_lock = self::$contacts_list[$this->id][0];
			$this->_visible = self::$contacts_list[$this->id][1];
		} else {
			$this->_lock = $this->getContactsLock();
			$this->_visible = $this->getContactsVisibility();
		}
		$this->setInvitation($this->id);
		return true;
	}

	public function getForceSchema(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			return $this->force_schema;
		}
		return 0;
	}

	public function getCheckSchema(){
		$app = self::getApp();
		if($this->id == $app->lincko->data['uid']){
			return $this->check_schema;
		}
		return 0;
	}

	public static function amIadmin(){
		$user = self::getUser();
		if($user->admin){
			return true;
		}
		self::errorMsg('You are not an Lincko developper');
		return false;
	}

////////////////////////////////////////////

	public function scopetheUser($query){
		$app = self::getApp();
		if(isset($app->lincko->data['uid'])){
			return $query->where('users.id', $app->lincko->data['uid']);
		}
		return $query->where('users.id', -1); //It will force an error since the user -1 does not exists
	}

	public static function getUser($force=false){
		if($force || !static::$me){
			static::$me = self::theUser()->first();
		} 
		return static::$me;
	}

	protected function get_HisHer(){
		$app = self::getApp();
		if($this->gender == 0){
			return $app->trans->getBRUT('api', 7, 1); //his
		} else {
			return $app->trans->getBRUT('api', 7, 2); //her
		}
	}

	public function setHistory($key=null, $new=null, $old=null, array $parameters = array(), $pivot_type=null, $pivot_id=null){
		$parameters['hh'] = $this->get_HisHer();
		parent::setHistory($key, $new, $old, $parameters, $pivot_type, $pivot_id);
	}

	//Do not show creation event
	public function getHistoryCreation($history_detail=false, array $parameters = array(), $items=false){
		return new \stdClass;
	}

	public function createdBy(){
		return $this->id;
	}

	public function getSha(){
		return substr($this->username_sha1, 0, 20); //Truncate to 20 characters because of phone notification alias isue (limited to 64bits = 20 characters in Hex)
	}

	public function save(array $options = array()){
		$app = self::getApp();
		$return = null;
		if(isset($this->id)){
			$return = parent::save($options);
		} else {
			if(isset($this->timeoffset) && !isset($this->resume)){
				//By default set personal resume at 6pm
				$this->resume = 18 + $this->timeoffset;
				if($this->resume < 0){
					$this->resume = 24 + $this->resume;
				}
				if($this->resume >= 24){
					$this->resume = fmod($this->resume, 24);
				}
			}
			try {
				$return = parent::save($options);

				$app->lincko->data['uid'] = $this->id;
				$app->lincko->data['username'] = $this->username;

				//We first login to shared worksace, which does not need to set a role permission, since everyone is an administrator (but not super)
				$app->lincko->data['workspace'] = '';
				$app->lincko->data['workspace_id'] = 0;
				
				$project = Projects::setPersonal();

				$app->lincko->data['user_log']->save();
				$onboarding = new Onboarding;
				$onboarding->next(10001); //initialize the onboarding process
			} catch(\Exception $e){
				\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, true);
				$return = null;
				return $return;
			}
		}	
		return $return;
	}

	//It checks if the user has access to it
	public function checkAccess($show_msg=true){
		$app = self::getApp();
		if(!isset($this->id) || (isset($this->id) && $this->id == $app->lincko->data['uid'])){ //Always allow for the user itself
			return $this->accessibility = (bool) true;
		}
		return parent::checkAccess($show_msg);
	}

	public function checkPermissionAllow($level, $msg=false){
		$app = self::getApp();
		$level = $this->formatLevel($level);
		if($level==1 && !isset($this->id) && $app->lincko->data['create_user'] && !Users::getUser()){ //Allow creation for new user and out of the application only
			return true;
		}
		return parent::checkPermissionAllow($level, $msg);
	}

	public function toJson($detail=true, $options = 0){
		$app = self::getApp();
		$this->updateContactAttributes();
		//the play with accessibility allow Data.php to gather information about some other users that are not in the user contact list
		$accessibility = $this->accessibility;
		$this->accessibility = true;
		$temp = parent::toJson($detail, $options);
		$this->accessibility = $accessibility;
		$temp = json_decode($temp);
		//Do not show email for all other users
		if($this->id == $app->lincko->data['uid']){
			$temp->email = $this->email;
		} else {
			$temp->email = "";
		}
		$temp = json_encode($temp, $options);
		return $temp;
	}

	public function toVisible(){
		$app = self::getApp();
		$this->updateContactAttributes();
		//the play with accessibility allow Data.php to gather information about some other users that are not in the user contact list
		$accessibility = $this->accessibility;
		$this->accessibility = true;
		$model = parent::toVisible();
		$this->accessibility = $accessibility;
		//Do not show email for all other users
		if($this->id == $app->lincko->data['uid']){
			$model->email = $this->email;
		} else {
			$model->email = "";
		}
		return $model;
	}

	public function extraDecode(){
		$app = self::getApp();
		$this->updateContactAttributes();
		//Do not show email for all other users
		if($this->id != $app->lincko->data['uid']){
			$this->email = "";
		}
		return parent::extraDecode();
	}

	public function getUsername(){
		return $this->username;
	}

	public function pivots_format($form, $history_save=true){
		$app = self::getApp();
		$save = parent::pivots_format($form, $history_save);
		if(isset($this->pivots_var->users)){
			if(!isset($this->pivots_var->usersLinked)){ $this->pivots_var->usersLinked = new \stdClass; }
			foreach ($this->pivots_var->users as $users_id => $column_list) {
				if(!isset($this->pivots_var->usersLinked->$users_id)){ $this->pivots_var->usersLinked->$users_id = new \stdClass; }
				foreach ($column_list as $column => $value) {
					//This insure to give or block access to both users in case of invitation
					if($column=='access'){
						$save = true;
						$access = $value[0];
						$this->pivots_var->users->$users_id->invitation = array(false, false);
						$this->pivots_var->usersLinked->$users_id->invitation = array(false, false);
						if($access){
							$this->pivots_var->usersLinked->$users_id->access = array(true, true);
						} else {
							$this->pivots_var->usersLinked->$users_id->access = array(false, true);
						}
						Users::find($users_id)->touchUpdateAt();
					}
				}
			}
		}
		return $save;	
	}

	public function setLanguage(){
		$app = self::getApp();
		$language = $app->trans->getClientLanguage();
		if(!empty($language) && $language!=$this->language){
			$this->language = strtolower($language);
			$this->brutSave(); //Because teh language settings doesn't need to be shown on front
		}
	}

	public function getLanguage(){
		return $this->language;
	}

}
