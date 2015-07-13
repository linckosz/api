<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\data\Users;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected $models = array();
	protected $lastvisit = 0; //Format 'Y-m-d H:i:s'
	protected $missing = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		$this->setLastVisit();
		$this->setMissing();
		return true;
	}

	protected function setLastVisit(){
		if(isset($this->data->data->lastvisit)){
			if(is_int($this->data->data->lastvisit) && $this->data->data->lastvisit>=0){
				$this->lastvisit = (new \DateTime('@'.$this->data->data->lastvisit))->format('Y-m-d H:i:s');
			}
		}
	}

	protected function setMissing(){
		if(isset($this->data->data->missing)){
			if(is_object($this->data->data->missing)){
				$this->missing = $this->data->data->missing;
			}
		}
	}

	protected function getModels(){
		$sql = 'SHOW TABLES;';
		$db = Capsule::connection('data');
		$data = $db->select( $db->raw($sql) );

		$classes = array();
		foreach ($data as $key => $value) {
			$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC(array_values($value)[0]);
			if(class_exists($tp)){
				$classes[] = $tp;
			}
		}
		$this->models = $classes;
	}

	protected function getList($detail=false, $missing=false){
		$app = $this->app;
		$result = new \stdClass;
		$usersContacts = new \stdClass;
		$uid = $app->lincko->data['uid'];
		$this->getModels();

		foreach($this->models as $key => $value) {
			if($this->lastvisit != 0){
				//Insire that the where is only with AND, not an OR!
				$data = $value::getLinked()->where('updated_at', '>=', $this->lastvisit)->get();	
			} else {
				$data = $value::getLinked()->get();
			}
			//Check if there is at least one update
			if(!$data->isEmpty()){
				//Get table name
				$table_name = (new $value)->getTable();
				//Add multi ID dependencies (Many to Many)
				foreach ($data as $key => $value) {
					$data[$key]->addMultiDependencies();
				}
				//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
				$table = array();
				$table_tp = json_decode($data->toJson());
				foreach ($table_tp as $key => $value) {
					$table_tp[$key]->new = 0;
					if(isset($value->viewed_by)){
						if(strpos($value->viewed_by, '-'.$app->lincko->data['uid'].'-') === false){
							$table_tp[$key]->new = 1;
						}
					}
					$compid = $data[$key]->getCompany();
					$id = $table_tp[$key]->id;

					//Create object
					unset($temp);
					if($detail){
						$temp = $table_tp[$key];
						if($table_name === 'users'){
							$temp->contactsLock = true; //By default we lock the user itself
							$temp->contactsVisibility = false; //By default we do not let the user seeing itself
						}
						//Delete ID property since it becomes the key of the table
						unset($temp->{'id'});
					} else {
						$temp = new \stdClass;
					}

					//Use Timestamp for JS
					if(isset($temp->created_at)){  $temp->created_at = (new \DateTime($temp->created_at))->getTimestamp(); }
					if(isset($temp->updated_at)){  $temp->updated_at = (new \DateTime($temp->updated_at))->getTimestamp(); }
					if(isset($temp->deleted_at)){  $temp->deleted_at = (new \DateTime($temp->deleted_at))->getTimestamp(); }

					//Only get History for getLatest() and getMissing()
					if($detail){
						//Get history
						if($history = $data[$key]->getHistory(false)){
							$temp->history = $history;
						}
					}

					if(!isset($result->$uid)){
						$result->$uid = new \stdClass;
					}
					if(!isset($result->$uid->$compid)){
						$result->$uid->$compid = new \stdClass;
					}
					if(!isset($result->$uid->$compid->$table_name)){
						$result->$uid->$compid->$table_name = new \stdClass;
					}

					$result->$uid->$compid->$table_name->$id = $temp;

					//Do not update conatact list when getLatest because it can exclude some contactsLock and contactsVisibility
					if(!$detail || $missing){
						//Get users contacts list as object
						$contacts = $data[$key]->getUsersContacts();
						foreach ($contacts as $contacts_key => $contacts_value) {
							if(!isset($usersContacts->$contacts_key)){
								$usersContacts->$contacts_key = new \stdClass;
								$usersContacts->$contacts_key->contactsLock = false;
								$usersContacts->$contacts_key->contactsVisibility = false;
								$usersContacts->$contacts_key->new = 0;
							}
							$usersContacts->$contacts_key->contactsLock = ($usersContacts->$contacts_key->contactsLock || $contacts_value->contactsLock);
							$usersContacts->$contacts_key->contactsVisibility = ($usersContacts->$contacts_key->contactsVisibility || $contacts_value->contactsVisibility);
						}
					}
				}
				unset($data);
			}
		}
		//Add all users to the main object
		foreach ($usersContacts as $key => $value) {
			unset($temp);
			if($detail && $missing && $user = Users::find($key)){
				$temp = json_decode($user->toJson());
				if($history = $user->getHistory(false)){
					$temp->history = $history;
				}
				$temp->contactsLock = $value->contactsLock;
				$temp->contactsVisibility = $value->contactsVisibility;
				//Delete ID property since it becomes the key of the table
				unset($temp->{'id'});
			} else {
				$temp = new \stdClass;
			}
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

		if($missing){
			$temp = new \stdClass;
			//We need to filter missing here by deleting what's too more, and do not make condition on missing before
			foreach ($result as $uid => $uid_value) {
				if(isset($this->missing->$uid) && isset($result->$uid)){
					foreach ($uid_value as $compid => $compid_value) {
						if(isset($this->missing->$uid->$compid) && isset($result->$uid->$compid)){
							foreach ($compid_value as $catid => $catid_value) {
								if(isset($this->missing->$uid->$compid->$catid) && isset($result->$uid->$compid->$catid)){
									foreach ($catid_value as $itemid => $itemid_value) {
										if(isset($this->missing->$uid->$compid->$catid->$itemid) && isset($result->$uid->$compid->$catid->$itemid)){
											//Build the missing item
											if(!isset($temp->$uid)){
												$temp->$uid = new \stdClass;
											}
											if(!isset($temp->$uid->$compid)){
												$temp->$uid->$compid = new \stdClass;
											}
											if(!isset($temp->$uid->$compid->$catid)){
												$temp->$uid->$compid->$catid = new \stdClass;
											}
											$temp->$uid->$compid->$catid->$itemid = $itemid_value;
										}
									}
								}
							}
						}
					}
				}
			}
			$result = $temp;
		}
		return $result;
	}

	public function getLatest(){
		return $this->getList(true, false);
	}

	public function getSchema(){
		return $this->getList(false, false);
	}

	public function getMissing(){
		return $this->getList(true, true);
	}

}

