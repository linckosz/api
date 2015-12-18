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
			}
		}
		return false;
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
		}

		foreach(self::$models as $key => $value) {
			if($this->lastvisit !== false){
				//Insure that the where is only with AND, not an OR!
				$data = $value::getLinked()->where('updated_at', '>=', $this->lastvisit)->get();	
			} else {
				$data = $value::getLinked()->get();
			}
			//Check if there is at least one update
			if(!$data->isEmpty()){
				//Get table name
				$model = new $value;
				$table_name = $model->getTable();
				//Get the relations list
				if($reset || $action == 'schema' || $action == 'missing'){
					if(!isset($result->$uid)){
						$result->$uid = new \stdClass;
					}
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
				
				//We do not have to delete if the user do not have access, it's already handled by getLinked
				if($action !== 'schema'){
					foreach ($data as $key => $value) {
						//Add multi ID dependencies (Many to Many)
						$data[$key]->addDependencies();
					}
				}

				foreach ($data as $key => $value) {
					$compid = $value->getCompany();
					if($compid != '_' && $compid != $app->lincko->data['company_id']){
						//If we the element is in another company, we do not need to keep the data
						continue;
					}
					$table_tp = json_decode($value->toJson($detail));
					//If the table need to be shown as viewed, if it doesn't exist we consider it's already viewed
					$table_tp->new = 0;
					if(isset($value->viewed_by)){
						if(strpos($value->viewed_by, '-'.$app->lincko->data['uid'].'-') === false){
							$table_tp->new = 1;
						}
					}
					$id = $table_tp->id;

					//Create object
					unset($temp);
					if($action == 'latest' || $action == 'missing' || $action == 'history'){
						$temp = $table_tp;
						if($table_name === 'users'){
							$temp->contactsLock = true; //By default we lock the user itself
							$temp->contactsVisibility = false; //By default we do not let the user seeing itself
							$temp->email = '';
							if($temp->id == $app->lincko->data['uid']){
								$temp->email = $value->email;
							}
						}
						//Delete ID property since it becomes the key of the table
						unset($temp->{'id'});
					} else {
						$temp = new \stdClass;
					}

					//Only get History for getLatest() and getMissing()
					if($action == 'latest' || $action == 'missing' || $action == 'history'){
						//Get history
						$temp->history = $value->getHistory($history_detail);
					}

					//Do not include getLatest adn getHistory because it will always create it, it's useless (CPU overkill)
					if($reset || $action == 'schema' || $action == 'missing'){
						//Get title for history
						if(!isset($result->$uid)){
							$result->$uid = new \stdClass;
						}
						if(!isset($result->$uid->{'_'})){
							$result->$uid->{'_'} = new \stdClass;
						}
						if(!isset($result->$uid->{'_'}->{'_history_title'})){
							$result->$uid->{'_'}->{'_history_title'} = new \stdClass;
						}
						if($action == 'schema'){
							$result->$uid->{'_'}->{'_history_title'}->$table_name = new \stdClass;
						} else if(!isset($result->$uid->{'_'}->{'_history_title'}->$table_name)){
							$result->$uid->{'_'}->{'_history_title'}->$table_name = $value->getHistoryTitles();
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

					//We only update contact list from getSchema, because other can exclude some contactsLock and contactsVisibility
					if($reset || $action == 'schema' || $action == 'missing'){
						//Get users contacts list as object
						$contacts = $value->getUsersContacts();
						foreach ($contacts as $contacts_key => $contacts_value) {
							if($contacts_key != $app->lincko->data['uid']){ //we do not overwritte the user itself
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
				if($action == 'missing' && $user = Users::find($key)){
					$temp = json_decode($user->toJson($detail));
					if($history = $user->getHistory('_')){
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
		if($force_schema){
			$user->timestamps = false; //Disable timestamp update_at
			$user->force_schema = false;
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

