<?php
// Category 7

namespace bundles\lincko\api\models\data;

use Carbon\Carbon;
use \libs\Datassl;
use \libs\Email;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\Notif;
use \bundles\lincko\api\models\libs\Data;

use Illuminate\Database\Capsule\Manager as Capsule;

class Users extends ModelLincko {

	//Warning => We cannot handle local (HK) and remote (3rd party) at the same time, so we do local (HK) only
	public function import($user){
		//(toto) Do not make it work for remote servers
		if(empty($user) || $app->lincko->data['remote']){
			return false;
		}
		$from_id = $user->id;
		$to_id = $this->id;
		if($from_id==$to_id){
			return false;
		}

		$users_tables = array();

		$models = Data::getModels();
		//\libs\Watch::php($models, '$models', __FILE__, __LINE__, false, false, true);
		foreach ($models as $table => $class) {
			/*
			if($table=='users'){
				continue;
			}
			$instance = new $class;
			$columns = $class::getColumns();
			//\libs\Watch::php($columns, 'table: '.$table, __FILE__, __LINE__, false, false, true);

			$inform = array();
			if(in_array('_perm', $columns)){
				$ask = false;
				$model = $class::withTrashed();
				foreach ($columns as $column) {
					if(substr($column, -3)=='_by'){
						$ask = true;
						$model = $model->orWhere($column, $from_id);
					}
				}
				if($ask && $list = $model->get(array('_perm'))){
					foreach ($list as $item) {
						if($perm = json_decode($item->_perm)){
							foreach ($perm as $users_id => $value) {
								$users_tables[$table][$users_id] = $users_id;
							}
						}
					}
				}
				//\libs\Watch::php($users_tables, 'users_tables '.$table, __FILE__, __LINE__, false, false, true);
			}

			//\libs\Watch::php($instance->freshTimestamp(), 'freshTimestamp '.$table, __FILE__, __LINE__, false, false, true);

			foreach ($columns as $column) {
				if(substr($column, -3)=='_by'){
					$time = $instance->freshTimestamp();
					//$class::withTrashed()->Where($column, $from_id)->getQuery()->update([$column => $to_id, 'updated_at' => $time, 'extra' => null]);
					$temp = $class::withTrashed()->Where($column, $from_id)->get(array('id'));
					\libs\Watch::php($temp->toArray(), 'temp '.$table, __FILE__, __LINE__, false, false, true);
					usleep(30000);
				}
			}
			*/

			//$pivots = PivotUsers(array($table)))->withTrashed()
		}

		return true;
	}

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

	protected $save_history = true;

	protected static $archive = array(
		'created_at' => 601,  //[{un}] joined @@title~~
		'_' => 602,//[{un}] modified [{hh}] profile
		//'username' => 602,//[{un}] modified [{hh}] profile
		//'firstname' => 602,//[{un}] modified [{hh}] profile
		//'lastname' => 602,//[{un}] modified [{hh}] profile
		//'gender' => 602,//[{un}] modified [{hh}] profile
		'email' => 602,//[{un}] modified [{hh}] profile
		//'timeoffset' => 602,//[{un}] modified [{hh}] profile
		//'resume' => 602,//[{un}] modified [{hh}] profile
		'pivot_users_invitation_1' => 695, //[{un}] has invited [{cun}]
		'pivot_users_access_0' => 696, //[{un}] blocked [{cun}]'s access to [{hh}] profile
		'pivot_users_access_1' => 697, //[{un}] authorized [{cun}]'s access to [{hh}] profile
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
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id', 'users_id_link')->withPivot('access', 'invitation', 'models');
	}

	//Many(Users) to Many(Users)
	public function usersLinked(){
		return $this->belongsToMany('\\bundles\\lincko\\api\\models\\data\\Users', 'users_x_users', 'users_id_link', 'users_id')->withPivot('access', 'invitation', 'models');
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
		if(isset($app->lincko->data['uid']) && $app->lincko->data['uid']!==false){
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
			//To disbale it We have to insre that the transactional operation is handle in upper level
			//$db = Capsule::connection($this->connection);
			//$db->beginTransaction();
			try {
				$return = parent::save($options);
				$app->lincko->data['uid'] = $this->id;
				$app->lincko->data['username'] = $this->username;
				//We first login to shared worksace, which does not need to set a role permission, since everyone is an administrator (but not super)
				$app->lincko->data['workspace'] = '';
				$app->lincko->data['workspace_id'] = 0;
				//$db->commit();
			} catch(\Exception $e){
				\libs\Watch::php(\error\getTraceAsString($e, 10), 'Exception: '.$e->getLine().' / '.$e->getMessage(), __FILE__, __LINE__, true);
				$return = null;
				//$db->rollback();
				return $return;
			}
		}	
		return $return;
	}

	//Unsafe method
	public function giveEditAccess(){
		$app = self::getApp();
		$this->accessibility = (bool) true;
		self::$permission_users[$app->lincko->data['uid']][$this->getTable()][$this->id] = 2;
	}

	//It checks if the user has access to it
	public function checkAccess($show_msg=true){
		$app = self::getApp();
		if($this->accessibility){
			return true;
		} else if(!isset($this->id) || (isset($this->id) && $this->id == $app->lincko->data['uid'])){ //Always allow for the user itself
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

	public function toJson($detail=true, $options = 256){ //256: JSON_UNESCAPED_UNICODE
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
					//toto => this can be a security issue becuse users>access is set on front
					if($column=='access'){
						$save = true;
						$access = $value[0];
						$this->pivots_var->users->$users_id->invitation = array(false, false);
						$this->pivots_var->usersLinked->$users_id->invitation = array(false, false);
						//this should be secure enough since we allow access at true only if there is an invitation pending
						if($access && $pivot = (new PivotUsers(array('users')))->withTrashed()->where('invitation', 1)->where('users_id', $app->lincko->data['uid'])->where('users_id_link', $users_id)->first()){
							$this->pivots_var->usersLinked->$users_id->access = array(true, true);
							//set models access from host request
							if(!is_null($pivot->models)){
								$invitation_models = json_decode($pivot->models);
								if(is_object($invitation_models)){
									foreach ($invitation_models as $table => $list) {
										//Don't give access to others users or workspace
										if($table=='workspaces' || $table=='users'){
											continue;
										}
										if(!isset($this->pivots_var->$table)){ $this->pivots_var->$table = new \stdClass; }
										//Make sure that the host have access to the original item
										if(is_numeric($list)){
											$id = intval($list);
											if(!isset($this->pivots_var->$table->$id)){ $this->pivots_var->$table->$id = new \stdClass; }
											$this->pivots_var->$table->$id->access = array(true, true);
											if($table=='tasks'){
												$this->pivots_var->$table->$id->in_charge = array(true, false);
												$this->pivots_var->$table->$id->approver = array(true, false);
											}
											//After saving, reset item permission to give access to the new user
											if(!isset(self::$permission_reset[$table])){ self::$permission_reset[$table] = array(); }
											self::$permission_reset[$table][$id] = $id;
										} else if(is_array($list) || is_object($list)){
											foreach ($list as $id) {
												$id = intval($id);
												if(!isset($this->pivots_var->$table->$id)){ $this->pivots_var->$table->$id = new \stdClass; }
												$this->pivots_var->$table->$id->access = array(true, true);
												if($table=='tasks'){
													$this->pivots_var->$table->$id->in_charge = array(true, false);
													$this->pivots_var->$table->$id->approver = array(true, false);
												}
												//After saving, reset item permission to give access to the new user
												if(!isset(self::$permission_reset[$table])){ self::$permission_reset[$table] = array(); }
												self::$permission_reset[$table][$id] = $id;
											}
										}
									}
								}
							}
							if(!isset($this->pivots_var->users)){ $this->pivots_var->users = new \stdClass; }
							if(!isset($this->pivots_var->users->$users_id)){ $this->pivots_var->users->$users_id = new \stdClass; }
							$this->pivots_var->users->$users_id->models = array(false, false);
							$this->pivots_var->users->$users_id->access = array(true, false);
							$this->pivots_var->usersLinked->$users_id->models = array(false, false);
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

	public static function inviteSomeoneCode($data){
		$app = self::getApp();
		$invite = false;
		if(isset($data->user_code) && $user_code = Datassl::decrypt($data->user_code, 'invitation')){
			if($guest = Users::find($user_code)){
				$invite = self::inviteSomeone($guest, $data);
			}
		}
		$app->lincko->flash['unset_user_code'] = true;
		return $invite;
	}

	public static function inviteSomeone($guest, $data){
		$app = self::getApp();
		$user = Users::getUser();
		$pivot = (new PivotUsers(array('users')))->withTrashed()->where('users_id', $guest->id)->where('users_id_link', $user->id)->first();

		$pivots_previous = false;
		if($pivot && $invitation_models = json_decode($pivot->models)){
			$invitation_models = json_decode($pivot->models);
			if(is_object($invitation_models) && !empty($invitation_models)){
				$pivots_previous = $invitation_models;
			}
		}
		if(!$pivot || !$pivot->access){
			$username = $user->username;
			$username_guest = $guest->username;
			$pivots = new \stdClass;
			$pivots->{'usersLinked>invitation'} = new \stdClass;
			$pivots->{'usersLinked>invitation'}->{$guest->id} = true;
			$pivots->{'usersLinked>access'} = new \stdClass;
			$pivots->{'usersLinked>access'}->{$guest->id} = false;
			if($data && isset($data->invite_access)){
				$invite_access = new \stdClass;
				if($pivots_previous){
					foreach ($pivots_previous as $table => $list) {
						if(!isset($invite_access->$table)){
							$invite_access->$table = new \stdClass;
						}
						if(is_numeric($list)){
							$id = intval($list);
							$invite_access->$table->$id = $id;
						} else if(is_array($list) || is_object($list)){
							foreach ($list as $id) {
								$id = intval($id);
								$invite_access->$table->$id = $id;
							}
						} 
					}
				}
				$invitation_models = json_decode($data->invite_access);
				if($invitation_models){
					foreach ($invitation_models as $table => $list) {
						if(!isset($invite_access->$table)){
							$invite_access->$table = new \stdClass;
						}
						if(is_numeric($list)){
							$id = intval($list);
							$invite_access->$table->$id = $id;
						} else if(is_array($list) || is_object($list)){
							foreach ($list as $id) {
								$id = intval($id);
								$invite_access->$table->$id = $id;
							}
						} 
					}
				}
				$pivots->{'usersLinked>models'} = new \stdClass;
				$pivots->{'usersLinked>models'}->{$guest->id} = json_encode($invite_access);
			}
			$user->pivots_format($pivots);
			//$user->pivots_save();
			$user->save();
			$link = 'https://'.$app->lincko->domain;
			$mail = new Email();

			$mail_subject = $app->trans->getBRUT('api', 1002, 1); //New Lincko collaboration request
			$mail_body_array = array(
				'mail_username_guest' => $username_guest,
				'mail_username' => $username,
				'mail_link' => $link,
			);
			$mail_body = $app->trans->getBRUT('api', 1002, 2, $mail_body_array); //You have a new collaboration request!<br><br>@@mail_username~~ has invited you to collaborate together using Lincko.

			$mail_template_array = array(
				'mail_head' => $mail_subject,
				'mail_body' => $mail_body,
				'mail_foot' => '',
			);
			$mail_template = $app->trans->getBRUT('api', 1000, 1, $mail_template_array);

			//Send mobile notification
			(new Notif)->push($mail_subject, $mail_body, $guest, $guest->getSha());

			if(Users::validEmail($guest->email)){
				$mail->addAddress($guest->email);
				$mail->setSubject($mail_subject);
				$mail->sendLater($mail_template);
			}
		} else if($pivot && $pivot->access){ //toto => I am not sure why it's here, we should never match that condition (inviting someone that is already in the contact list)
			//we directly give access to models
			if($data && isset($data->invite_access)){
				$invitation_models = json_decode($data->invite_access);
				$guest->giveEditAccess(); //A bit unsafe method
				foreach ($invitation_models as $table => $list) {
					//Don't give access to others users or workspace
					if($table=='workspaces' || $table=='users'){
						continue;
					}
					if(is_numeric($list)){
						$id = intval($list);
						$pivots = new \stdClass;
						$pivots->{$table.'>access'} = new \stdClass;
						$pivots->{$table.'>access'}->{$id} = true;
						if($table=='tasks'){
							$pivots->{$table.'>in_charge'}->{$id} = true;
							$pivots->{$table.'>approver'}->{$id} = true;
						}
						$guest->pivots_format($pivots);
						$guest->save();
						if($class = Users::getClass($table)){
							if($item = $class::withTrashed()->find($id)){
								$item->setPerm();
								//$item->touchUpdateAt();
							}
						}
						
					} else if(is_array($list) || is_object($list)){
						foreach ($list as $id) {
							$id = intval($id);
							$pivots = new \stdClass;
							$pivots->{$table.'>access'} = new \stdClass;
							$pivots->{$table.'>access'}->{$id} = true;
							if($table=='tasks'){
								$pivots->{$table.'>in_charge'}->{$id} = true;
								$pivots->{$table.'>approver'}->{$id} = true;
							}
							$guest->pivots_format($pivots);
							$guest->save();
							if($class = Users::getClass($table)){
								if($item = $class::withTrashed()->find($id)){
									$item->setPerm();
									//$item->touchUpdateAt();
								}
							}
							
						}
					}
				}
			}
		}
		return true;
	}

	public static function importGhost($uid, $code){
		
	}

}
