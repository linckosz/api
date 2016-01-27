<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\libs\PivotUsersRoles;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected static $models = NULL;
	protected $lastvisit = false; //Format 'Y-m-d H:i:s'
	protected $partial = NULL;

	protected $item_detail = true;
	protected $history_detail = false;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function reinit(){
		$this->lastvisit = false;
		$this->partial = NULL;
		$this->item_detail = true;
		$this->history_detail = false;
	}

	public function dataUpdateConfirmation($msg, $status=200){
		$app = $this->app;

		if($this->setLastVisit()){
			$lastvisit = time()-1;
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 9), //You got the latest updates.
					'partial' => $this->getLatest(),
					'lastvisit' => $lastvisit,
				),
				$msg
			);
		} else {
			$msg = array_merge(
				array(
					'msg' => $app->trans->getBRUT('api', 8888, 13), //Server OK
					'partial' => $this->getNewest(),
				),
				$msg
			);
		}

		$app->render($status, array('msg' => $msg,));
		return true;
	}

	protected function setLastVisit($timestamp='false'){
		if(is_int($timestamp)){
			if($timestamp>0){
				return $this->lastvisit = (new \DateTime('@'.$timestamp))->format('Y-m-d H:i:s');
			}
			return $this->lastvisit = false;
		} else if(isset($this->data->data->lastvisit)){
			if(is_int($this->data->data->lastvisit) && $this->data->data->lastvisit>0){
				return $this->lastvisit = (new \DateTime('@'.$this->data->data->lastvisit))->format('Y-m-d H:i:s');
			}
			return $this->lastvisit = false;
		}
		return $this->lastvisit = (new \DateTime())->format('Y-m-d H:i:s');
	}

	public function getTimestamp(){
		if($this->lastvisit){
			return (new \DateTime($this->lastvisit))->getTimestamp();
		} else if($this->setLastVisit()){
			return (new \DateTime($this->lastvisit))->getTimestamp();
		} else {
			return 0;
		}
	}

	protected function setPartial(){
		if(isset($this->data->data->partial)){
			if(is_object($this->data->data->partial)){
				return $this->partial = $this->data->data->partial;
			}
		}
		//This will help missing and history to not scan the whole database if we do not provide a partial parameter
		return $this->partial = new \stdClass;
	}

	public static function getModels(){
		if(is_null(self::$models)){
			$sql = 'SHOW TABLES;';
			$db = Capsule::connection('data');
			$db->enableQueryLog();
			$data = $db->select( $db->raw($sql) );
			$classes = array();
			foreach ($data as $key => $value) {
				$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC(array_values((array) $value)[0]);
				if(class_exists($tp)){
					$table_name = $tp::getTableStatic();
					$classes[$table_name] = $tp;
				}
			}
			self::$models = $classes;
		}
		return self::$models;
	}

	/*
	TIPS (31 dec 2015):
		A way to accelerate the code should be to do only one SQL request (like $tp = Companies::with('projects.tasks')->find(3)->toJson() ), but this do not call toJson for child items, and we migth need to rebuild the client side database, this is a heavy rewriting to do only if we really need to speedup the code execution. It can also exclude the possibility to link a task to 2 projects
	*/
	protected function getList(){
		$app = $this->app;
		$result = new \stdClass;
		$usersContacts = new \stdClass;
		$uid = $app->lincko->data['uid'];
		self::getModels();

		$full_data = false;

		//If the lastvisit is not set, and we do not work with partial database, we force to get all details
		if(!$this->lastvisit && is_null($this->partial) && !$this->history_detail){
			$full_data = true;
			//We check if the user has a personnal project, if not we create one
			Projects::setPersonal();
		}

		$roles = PivotUsersRoles::getLinked()->get();
		$roles_list = array();
		foreach($roles as $value) {
			if($value->roles_id!=null || $value->single!=null){
				if(isset($roles_list[$value->relation_type])){ $roles_list[$value->relation_type] = array(); }
				if($value->roles_id!=null){
					$roles_list[$value->relation_type][$value->relation_id] = array(
						'roles_id' => $value->roles_id,
					);
				}
				if($value->single!=null){
					$roles_list[$value->relation_type][$value->relation_id] = array(
						'single' => $value->single,
					);
				}
			}
		}

		foreach(self::$models as $key => $value) {
			//Insure that the where is only with AND, not an OR!
			$data = $value::getItems($this->lastvisit);

			//Check if there is at least one update
			if(!$data->isEmpty()){
				$id_list = array();
				//Get table name
				$model = new $value;
				$table_name = $model->getTable();

				if(!is_null($this->partial)){
					$comp = array('_', $app->lincko->data['company_id']);
					foreach($comp as $compid) {
						if(!isset($this->partial->$uid) || !isset($this->partial->$uid->$compid) || !isset($this->partial->$uid->$compid->$table_name)){
							continue;
						}
					}
				}

				if(!isset($result->$uid)){
					$result->$uid = new \stdClass;
				}

				//Get the relations list
				if($full_data){
					if(!isset($result->$uid->{'_'})){
						$result->$uid->{'_'} = new \stdClass;
					}

					if(!isset($result->$uid->{'_'}->{'_relations'})){
						$result->$uid->{'_'}->{'_relations'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'_relations'}->$table_name)){
						if($this->item_detail){
							//Build the relations with UP ("parents" which is the default), and DOWN ("children" which has to be launched)
							$result->$uid->{'_'}->{'_relations'}->$table_name = $model->getRelations();
						} else {
							$result->$uid->{'_'}->{'_relations'}->$table_name = new \stdClass;
						}
					}

					if(!isset($result->$uid->{'_'}->{'_history_title'})){
						$result->$uid->{'_'}->{'_history_title'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'_history_title'}->$table_name)){
						if($this->item_detail){
							$result->$uid->{'_'}->{'_history_title'}->$table_name = $model->getHistoryTitles();
						} else {
							$result->$uid->{'_'}->{'_history_title'}->$table_name = new \stdClass;
						}
					}
				}

				$compid = false;
				//Launching any model Method inside this loo migth kill the server (too many mysql requests)
				foreach ($data as $key => $value) {
					unset($temp);
					$id = $value->id;
					//On client side we store companies in shared folder '_'
					if($table_name == 'companies'){
						$compid = '_';
					} else {
						$compid = $value->getCompany();
					}
					//If we the element is in another company, we do not need to keep the data
					if($compid != '_' && $compid != $app->lincko->data['company_id']){
						continue;
					}

					//If the items doesn't exist in partial, no need to record it
					if(!is_null($this->partial) && !isset($this->partial->$uid->$compid->$table_name->$id)){
						continue;
					}

					//Create object

					$id_list[] = $id;
					if($this->item_detail){
						$temp = json_decode($value->toJson());
					} else {
						$temp = new \stdClass;
					}

					//Only get History for getLatest() and getMissing() and getHistory()
					if(isset($temp->id)){
						unset($temp->{'id'}); //Delete ID property since it becomes the key of the table
						//Get only creation history to avoid mysql overload
						$temp->history = $value->getHistoryCreation();
					}

					//Set parent information
					if(!is_null($value->getParentName())){
						$temp->parent = $value->getParentName();
						$temp->parent_id = $value->{$temp->parent.'_id'};
					} else {
						$temp->parent = null;
					}
					
					//Set Role information
					if(isset($roles_list[$table_name][$id])){
						$temp->_perm = $roles_list[$table_name][$id];
					}
					
					if(!isset($result->$uid->$compid)){
						$result->$uid->$compid = new \stdClass;
					}
					if(!isset($result->$uid->$compid->$table_name)){
						$result->$uid->$compid->$table_name = new \stdClass;
					}

					$result->$uid->$compid->$table_name->$id = $temp;

				}
				
				if(!empty($id_list)){
					
					if($full_data){
						$contacts = $value::getUsersContactsID($id_list);
						foreach ($contacts as $contacts_key => $contacts_value) {
							if($contacts_key != $app->lincko->data['uid']){ //Do not overwritte the user itself
								if(!isset($usersContacts->$contacts_key)){
									$usersContacts->$contacts_key = new \stdClass;
									$usersContacts->$contacts_key->contactsLock = false;
									$usersContacts->$contacts_key->contactsVisibility = false;
									$usersContacts->$contacts_key->new = 0;
								}
								//Keep true if at least once
								$usersContacts->$contacts_key->contactsLock = ($usersContacts->$contacts_key->contactsLock || $contacts_value->contactsLock);
								$usersContacts->$contacts_key->contactsVisibility = ($usersContacts->$contacts_key->contactsVisibility || $contacts_value->contactsVisibility);
							}
						}
					}

					if($this->item_detail){
						//Get dependency (all ManyToMany that have other fields than access)
						$dependencies = $value::getDependencies($id_list);
						foreach ($dependencies as $id => $temp) {
							if(isset($result->$uid->$compid->$table_name->$id)){
								$result->$uid->$compid->$table_name->$id = (object) array_merge((array) $result->$uid->$compid->$table_name->$id, (array) $temp);
							}
						}
						//Get history
						$histories = $value::getHistories($id_list, $this->history_detail);
						foreach ($histories as $id => $temp) {
							if(isset($result->$uid->$compid->$table_name->$id)){
								$result->$uid->$compid->$table_name->$id->history = (object) array_merge((array) $result->$uid->$compid->$table_name->$id->history, (array) $temp->history);
							}
						}
						//For history, we only keep the items that are filled in
						if($this->history_detail){
							if(empty((array) $histories)){
								unset($result->$uid->$compid->$table_name);
							} else {
								foreach ($result->$uid->$compid->$table_name as $id => $temp) {
									if(!isset($histories->$id)){
										unset($result->$uid->$compid->$table_name->$id);
									}
								}
							}
							if(empty((array) $result->$uid->$compid)){
								unset($result->$uid->$compid);
							}
						}
					}
				}

				unset($data);
			}
		}

		//Delete to main user to not overwrite its settings
		unset($usersContacts->{$app->lincko->data['uid']});
		//Add all users to the main object
		foreach ($usersContacts as $key => $value) {
			unset($temp);
			if($key != $app->lincko->data['uid']){ //we do not overwritte the user itself
				if($user = Users::find($key)){
					if($this->item_detail){
						$temp = json_decode($user->toJson());
						//Add history for poeple visible in user list only (can get detauls because not heavy data)
						if($value->contactsVisibility && $history = $user->getHistory(true)){
							$temp->history = $history;
						}
						$temp->contactsLock = $value->contactsLock;
						$temp->contactsVisibility = $value->contactsVisibility;
					} else {
						$temp = new \stdClass;
					}
					//Delete ID property since it becomes the key of the table
					unset($temp->{'id'});
					if(!isset($result->$uid)){
						$result->$uid = new \stdClass;
					}
					if(!isset($result->$uid->{'_'})){
						$result->$uid->{'_'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'users'})){
						$result->$uid->{'_'}->{'users'} = new \stdClass;
					}
					$result->$uid->{'_'}->{'users'}->$key = $temp;
				}	
			}
		}
		
		//Enable this code to see if there is any bootle neck (time) doing mysql requests
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() ,'QueryLog', __FILE__, false, false, true);
		
		return $result;
	}

	public function getLatest($timestamp=false){
		$this->reinit();
		$this->setLastVisit($timestamp);
		$this->partial = NULL;
		return $this->getList();
	}

	public function getNewest(){
		$this->reinit();
		$app = $this->app;
		$this->lastvisit = (new \DateTime('@'.$app->lincko->data['lastvisit']))->format('Y-m-d H:i:s');
		$this->partial = NULL;
		return $this->getList();
	}

	public function getSchema(){
		$this->reinit();
		$this->lastvisit = false;
		$this->partial = NULL;
		$this->item_detail = false;
		return $this->getList();
	}

	public function getMissing(){
		$this->reinit();
		$this->lastvisit = false;
		$this->setPartial();
		return $this->getList();
	}

	public function getHistory(){
		$this->reinit();
		$this->lastvisit = false;
		$this->history_detail = true;
		$this->setPartial();
		return $this->getList();
	}

	public function getForceSchema(){
		$user = Users::getUser();
		$force_schema = $user->force_schema;
		if($force_schema>0){
			$user->timestamps = false; //Disable timestamp update_at
			$user->force_schema = 0;
			$user->save();
		}
		return $force_schema;
	}

	public function setForceSchema(){
		$user = Users::getUser();
		return $user->setForceSchema();
	}

	public function setForceReset(){
		return Users::setForceReset();
	}

}

