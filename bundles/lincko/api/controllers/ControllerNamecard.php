<?php
// Category 21

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Namecards;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\libs\Data;

/*

NAMECARDS

*/

class ControllerNamecard extends Controller {

	protected $app = NULL;
	protected $data = NULL;
	protected $form = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		$this->form = new \stdClass;
		$this->setFields();
		return true;
	}

	protected function setFields(){
		$app = $this->app;
		if(is_null($this->form)){
			$form = new \stdClass;
		} else {
			$form = $this->form;
		}
		if(!isset($this->data->data)){
			$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4)), 'error' => true,)); //No data form received.
			return true;
		} else {
			//Convert to object
			$data = (object) $this->data->data;
		}
		foreach ($data as $key => $value) {
			$form->$key = $value;
		}
		//Convert NULL to empty string to help isset returning true
		if(is_array($form) || is_object($form)){
			foreach ($form as $key => $value) {
				if(!is_numeric($value) && empty($value)){ //Exclude 0 to become an empty string
					$form->$key = '';
				}
			}
		}
		if(isset($form->id) && is_numeric($form->id)){
			$form->id = (int) $form->id;
		}
		if(isset($form->temp_id) && is_string($form->temp_id)){
			$form->temp_id = trim($form->temp_id);
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		if(isset($form->email) && is_string($form->email)){
			$form->email = trim(STR::break_line_conv($form->email,' '));
		}
		if(isset($form->username) && is_string($form->username)){
			$form->username = trim(STR::break_line_conv($form->username,' '));
		}
		if(isset($form->firstname) && is_string($form->firstname)){
			$form->firstname = trim(STR::break_line_conv($form->firstname,' '));
		}
		if(isset($form->lastname) && is_string($form->lastname)){
			$form->lastname = trim(STR::break_line_conv($form->lastname,' '));
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 21, 1)."\n"; //Namecard creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Namecards::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->email) && !Namecards::validEmail($form->email)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		else if(isset($form->username) && (!Namecards::validChar($form->username, true) || !Namecards::validTextNotEmpty($form->username, true))){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 10); //We could not validate the username format: - 104 characters max - Without space
			$errfield = 'username';
		}
		else if(isset($form->firstname) && !Namecards::validChar($form->firstname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 8); //We could not validate the first name format: - 104 characters max
			$errfield = 'firstname';
		}
		else if(isset($form->lastname) && !Namecards::validChar($form->lastname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 9); //We could not validate the last name format: - 104 characters max
			$errfield = 'lastname';
		}
		else if(isset($form->phone) && !Namecards::validChar($form->phone, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'phone';
		}
		else if($model = new Namecards()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_id = $form->parent_id;
			if(isset($form->username)){ $model->username = $form->username; } //Optional
			if(isset($form->email)){ $model->email = $form->email; } //Optional
			if(isset($form->firstname)){ $model->firstname = $form->firstname; } //Optional
			if(isset($form->lastname)){ $model->lastname = $form->lastname; } //Optional
			if(isset($form->address)){ $model->address = $form->address; } //Optional
			if(isset($form->phone)){ $model->phone = $form->phone; } //Optional
			if(isset($form->business)){ $model->business = $form->business; } //Optional
			if(isset($form->additional)){ $model->additional = $form->additional; } //Optional
			if(isset($form->linkedin)){ $model->linkedin = $form->linkedin; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 21, 2)); //Namecard created.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 201, false, $lastvisit, false);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function read_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 21, 3)."\n"; //Namecard access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Namecards::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 39); //We could not validate the namecard ID.
			$errfield = 'id';
		}
		else if($model = Namecards::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 21, 4); //Namecard accessed.
				$data = new Data();
				$force_partial = new \stdClass;
				$force_partial->$uid = new \stdClass;
				$force_partial->$uid->$key = new \stdClass;
				$force_partial->$uid->$key->{$form->id} = new \stdClass;
				$partial = $data->getMissing($force_partial);
				if(isset($partial) && isset($partial->$uid) && !empty($partial->$uid)){
					$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => 'reading')));
					return true;
				}
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function update_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 21, 5)."\n"; //Namecard update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Namecards::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the Namecard ID.
			$errfield = 'id';
		}
		else if(isset($form->email) && !Namecards::validEmail($form->email)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 11); //We could not validate the Email address format: - {name}@{domain}.{ext} - 191 characters maxi
			$errfield = 'email';
		}
		else if(isset($form->username) && (!Namecards::validChar($form->username, true) || !Namecards::validTextNotEmpty($form->username, true))){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 10); //We could not validate the username format: - 104 characters max - Without space
			$errfield = 'username';
		}
		else if(isset($form->firstname) && !Namecards::validChar($form->firstname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 8); //We could not validate the first name format: - 104 characters max
			$errfield = 'firstname';
		}
		else if(isset($form->lastname) && !Namecards::validChar($form->lastname, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 9); //We could not validate the last name format: - 104 characters max
			$errfield = 'lastname';
		}
		else if(isset($form->phone) && !Namecards::validChar($form->phone, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 34); //We could not validate the format
			$errfield = 'phone';
		}
		else if($model = Namecards::find($form->id)){
			if(isset($form->username)){ $model->username = $form->username; } //Optional
			if(isset($form->email)){ $model->email = $form->email; } //Optional
			if(isset($form->firstname)){ $model->firstname = $form->firstname; } //Optional
			if(isset($form->lastname)){ $model->lastname = $form->lastname; } //Optional
			if(isset($form->address)){ $model->address = $form->address; } //Optional
			if(isset($form->phone)){ $model->phone = $form->phone; } //Optional
			if(isset($form->business)){ $model->business = $form->business; } //Optional
			if(isset($form->additional)){ $model->additional = $form->additional; } //Optional
			if(isset($form->linkedin)){ $model->linkedin = $form->linkedin; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				//For shared workspace and the user itself only, we allow editing
				if($app->lincko->data['workspace_id']==0 && $model->parent_id==$app->lincko->data['uid']){
					$model->forceGiveAccess(2); //Allow edit
				}
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 21, 6)); //Namecard updated.
					$data = new Data();
					$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);
					return true;
				}
			} else {
				$errmsg = $app->trans->getBRUT('api', 8, 29); //Already up to date.
				$app->render(200, array('show' => false, 'msg' => array('msg' => $errmsg)));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function change_post(){
		$app = $this->app;
		$form = $this->form;

		if(isset($form->id)){
			return $this->update_post();
		} else if(isset($form->parent_id)){
			if($namecard = Namecards::withTrashed()->where('parent_id', $form->parent_id)->where('workspaces_id', $app->lincko->data['workspace_id'])->first()){
				$this->form->id = $namecard->id;
				return $this->update_post();
			} else {
				return $this->create_post();
			}
		}

		$failmsg = $app->trans->getBRUT('api', 21, 5)."\n"; //Namecard update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function delete_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 21, 7)."\n".$app->trans->getBRUT('api', 0, 6); //Namecard deletion failed. You are not allowed to delete the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 21, 20)."\n".$app->trans->getBRUT('api', 0, 9); //Namecard restoration failed. You are not allowed to restore the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

}
