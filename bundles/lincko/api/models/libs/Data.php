<?php

namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Capsule\Manager as Capsule;
use \libs\STR;

use \bundles\lincko\api\models\data\Users;

class Data {

	protected $app = NULL;
	protected $data = NULL;
	protected static $models = NULL;
	protected $lastvisit = false; //Format 'Y-m-d H:i:s'
	protected $partial = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
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
		if(is_int($timestamp) && $timestamp>=0){
			return $this->lastvisit = (new \DateTime('@'.$timestamp))->format('Y-m-d H:i:s');
		} else if(isset($this->data->data->lastvisit)){
			if(is_int($this->data->data->lastvisit) && $this->data->data->lastvisit>=0){
				return $this->lastvisit = (new \DateTime('@'.$this->data->data->lastvisit))->format('Y-m-d H:i:s');
			} else {
				return $this->lastvisit = false;
			}
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
				$this->partial = $this->data->data->partial;
			}
		}
	}

	public static function getModels(){
		if(is_null(self::$models)){
			$sql = 'SHOW TABLES;';
			$db = Capsule::connection('data');
			$data = $db->select( $db->raw($sql) );
			$classes = array();
			foreach ($data as $key => $value) {
				$tp = '\\bundles\\lincko\\api\\models\\data\\'.STR::textToFirstUC(array_values($value)[0]);
				if(class_exists($tp)){
					$table_name = $tp::getTableStatic();
					$classes[$table_name] = $tp;
				}
			}
			self::$models = $classes;
		}
		return self::$models;
	}

	protected function getList($action){
		$app = $this->app;
		$result = new \stdClass;
		$usersContacts = new \stdClass;
		$uid = $app->lincko->data['uid'];
		self::getModels();
		$detail = false;
		if($action == 'latest' || $action == 'missing'){
			$detail = true;
		}
		$history_detail = false;
		if($action == 'history'){
			$history_detail = true;
		}
		//We force to get all data because it's a client database full reset stauts
		$reset = false;
		if($action == 'latest'&& $this->getTimestamp()<=0){
			$reset = true;
			$this->lastvisit = false;
		}

		foreach(self::$models as $key => $value) {
			if($this->lastvisit !== false){
				//Insure that the where is only with AND, not an OR!
				$data = $value::getItems($this->lastvisit);
			} else {
				$data = $value::getItems();
			}

			//Check if there is at least one update
			if(!$data->isEmpty()){
				$id_list = array();
				//Get table name
				$model = new $value;
				$table_name = $model->getTable();

				if($action == 'schema' && $table_name == 'roles'){
					\libs\Watch::php($data->toArray(), '$data', __FILE__, false, false, true);
				}

				if(!isset($result->$uid)){
					$result->$uid = new \stdClass;
				}

				//Get the relations list
				if($reset || $action == 'schema' || $action == 'missing'){
					if(!isset($result->$uid->{'_'})){
						$result->$uid->{'_'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'_relations'})){
						$result->$uid->{'_'}->{'_relations'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'_relations'}->$table_name)){
						//Build the relations with UP ("parents" which is the default), and DOWN ("children" which has to be launched)
						$result->$uid->{'_'}->{'_relations'}->$table_name = $model->getRelations();
					}
				}

				//Do not include getLatest adn getHistory because it will always create it, it's useless (CPU overkill)
				if($reset || $action == 'schema' || $action == 'missing'){
					if(!isset($result->$uid->{'_'})){
						$result->$uid->{'_'} = new \stdClass;
					}
					if(!isset($result->$uid->{'_'}->{'_history_title'})){
						$result->$uid->{'_'}->{'_history_title'} = new \stdClass;
					}
					if($action == 'schema'){
						$result->$uid->{'_'}->{'_history_title'}->$table_name = new \stdClass;
					} else if(!isset($result->$uid->{'_'}->{'_history_title'}->$table_name)){
						$result->$uid->{'_'}->{'_history_title'}->$table_name = $model->getHistoryTitles();
					}
				}

				//Launching any model Method inside this loo migth kill the server (too many mysql requests)
				foreach ($data as $key => $value) {
					$compid = $value->getCompany();
					if($compid != '_' && $compid != $app->lincko->data['company_id']){
						//If we the element is in another company, we do not need to keep the data
						continue;
					}

					//Create object
					unset($temp);
					
					$id = $value->id;
					$id_list[] = $id;
					if($action == 'latest' || $action == 'missing' || $action == 'history'){
						
						$temp = json_decode($value->toJson($detail));
					} else {
						$temp = new \stdClass;
					}

					//Only get History for getLatest() and getMissing() and getHistory()
					if(isset($temp->id)){
						unset($temp->{'id'}); //Delete ID property since it becomes the key of the table
						//Get only creation history to avoid mysql overload
						$temp->history = $value->getHistoryCreation();
					}

					if(!isset($result->$uid->$compid)){
						$result->$uid->$compid = new \stdClass;
					}
					if(!isset($result->$uid->$compid->$table_name)){
						$result->$uid->$compid->$table_name = new \stdClass;
					}

					$result->$uid->$compid->$table_name->$id = $temp;

				}

				//We only update contact list from getSchema, because other can exclude some contactsLock and contactsVisibility
				if($reset || $action == 'schema' || $action == 'missing'){
					$contacts = $value::getUsersContactsID($id_list);
					foreach ($contacts as $contacts_key => $contacts_value) {
						if($contacts_key == $app->lincko->data['uid']){
							
						} else {
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

				if($action != 'schema'){
					//Get dependency (all ManyToMany that have other fields than access)
					$dependencies = $value::getDependencies($id_list);
					foreach ($dependencies as $id => $temp) {
						if(isset($result->$uid->$compid->$table_name->$id)){
							$result->$uid->$compid->$table_name->$id = (object) array_merge((array) $result->$uid->$compid->$table_name->$id, (array) $temp);
						}
					}
				}

				if($action == 'latest' || $action == 'missing' || $action == 'history'){
					//Get history
					$histories = $value::getHistories($id_list, $history_detail);
					foreach ($histories as $id => $temp) {
						if(isset($result->$uid->$compid->$table_name->$id)){
							$result->$uid->$compid->$table_name->$id->history = (object) array_merge((array) $result->$uid->$compid->$table_name->$id->history, (array) $temp->history);
						}
					}
				}

				unset($data);
			}
		}

		if($action === 'schema'){
			\libs\Watch::php($result, '$result', __FILE__, false, false, true);
		}
		//Delete to main user to not overwrite its settings
		unset($usersContacts->{$app->lincko->data['uid']});
		//Add all users to the main object
		foreach ($usersContacts as $key => $value) {
			unset($temp);
			if($key != $app->lincko->data['uid']){ //we do not overwritte the user itself
				if($user = Users::find($key)){
					$temp = json_decode($user->toJson($detail));
					//Add history for poeple visible in user list only (can get detauls because not heavy data)
					if($value->contactsVisibility && $history = $user->getHistory(true)){
						$temp->history = $history;
					}
					$temp->contactsLock = $value->contactsLock;
					$temp->contactsVisibility = $value->contactsVisibility;
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

		if($action == 'missing'){
			$temp = new \stdClass;
			//We need to filter missing here by deleting what's too more, and do not make condition on missing before
			foreach ($result as $uid => $uid_value) {
				if(isset($this->partial->$uid) && isset($result->$uid)){
					foreach ($uid_value as $compid => $compid_value) {
						if(isset($this->partial->$uid->$compid) && isset($result->$uid->$compid)){
							foreach ($compid_value as $catid => $catid_value) {
								if(isset($this->partial->$uid->$compid->$catid) && isset($result->$uid->$compid->$catid)){
									foreach ($catid_value as $itemid => $itemid_value) {
										if(isset($this->partial->$uid->$compid->$catid->$itemid) && isset($result->$uid->$compid->$catid->$itemid)){
											//Build the partial item
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

	public function getLatest($timestamp=false){
		$this->setLastVisit($timestamp);
		$this->partial = NULL;
		return $this->getList('latest');
	}

	public function getNewest(){
		$app = $this->app;
		$this->lastvisit = (new \DateTime('@'.$app->lincko->data['lastvisit']))->format('Y-m-d H:i:s');
		$this->partial = NULL;
		return $this->getList('latest');
	}

	public function getSchema(){
		$this->lastvisit = false;
		$this->partial = NULL;
		return $this->getList('schema');
	}

	public function getMissing(){
		$this->lastvisit = false;
		$this->setPartial();
		return $this->getList('missing');
	}

	public function getHistory(){
		$this->lastvisit = false;
		$this->setPartial();
		return $this->getList('history');
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

