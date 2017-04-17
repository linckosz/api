<?php
// Category 11

namespace bundles\lincko\api\controllers;

use \libs\Email;
use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Messages;
use \bundles\lincko\api\models\libs\Data;


/*

MESSAGES

	message/read => post
		+id [integer] (the ID of the element)

	message/create => post
		+parent_id [integer] (the ID of the parent object)
		+comment [string]

	message/update => post
	!rejected!

	message/delete => post
	!rejected!

	message/restore => post
	!rejected!

	message/recall => post
		+id [integer]

	message/collect => post
		+parent_id [integer]
		-row_number [integer] (Get a certain number of items, default is 30)
		-id_max [integer] (We try to get all ID below that one, id_max is not included)

*/

class ControllerMessage extends Controller {

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
		$form = new \stdClass;
		if(!isset($this->data->data)){
			$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4)), 'error' => true,)); //No data form received.
			return true;
		} else {
			$form = $this->data->data;
		}
		//Convert to object
		$form = (object)$form;
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
		if(isset($form->comment) && is_string($form->comment)){
			$form->comment = STR::br2ln(trim($form->comment));
			$form->comment = STR::HTMLwithReturnLine($form->comment);
		}
		if(isset($form->id_max) && is_numeric($form->id_max)){
			$form->id_max = (int) $form->id_max;
		}
		if(isset($form->row_number) && is_numeric($form->row_number)){
			$form->row_number = (int) $form->row_number;
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 11, 1)."\n"; //Message creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Messages::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->comment) || !Messages::validTextNotEmpty($form->comment)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if($model = new Messages()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_id = $form->parent_id;
			$model->comment = $form->comment;
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 11, 2)); //Message created.
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

		$failmsg = $app->trans->getBRUT('api', 11, 3)."\n"; //Message access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Messages::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 1); //We could not validate the message ID.
			$errfield = 'id';
		}
		else if($model = Messages::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 11, 4); //Message accessed.
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
		$errmsg = $app->trans->getBRUT('api', 11, 5)."\n".$app->trans->getBRUT('api', 0, 5); //Message update failed. You are not allowed to edit the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function delete_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 11, 7)."\n".$app->trans->getBRUT('api', 0, 6); //Message deletion failed. You are not allowed to delete the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 11, 20)."\n".$app->trans->getBRUT('api', 0, 9); //Message restoration failed. You are not allowed to restore the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function recall_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 11, 23)."\n"; //Message recall failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 11, 25); //You can only recall a message within 2 minutes.
		$errfield = 'undefined';

		if(!isset($form->id) || !Messages::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 1); //We could not validate the message ID.
			$errfield = 'id';
		}
		else if($model = Messages::find($form->id)){
			$model->recalled_by = (int)$app->lincko->data['uid'];
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 11, 24)); //Message recalled.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function collect_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 11, 3)."\n"; //Message access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Messages::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->id_max) && !Messages::validNumeric($form->id_max, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 1); //We could not validate the message ID.
			$errfield = 'id_max';
		}
		else if(isset($form->id_max) && !Messages::validNumeric($form->id_max, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'id_max';
		}
		else {
			$parent_id = $form->parent_id;
			if(isset($form->row_number)){
				Messages::setRowNumber($form->row_number);
			} else {
				Messages::setRowNumber(); //Default
			}
			if(isset($form->id_max)){
				Messages::setIdMax($form->id_max);
			} else {
				Messages::setIdMax(); //Default
			}
			$uid = $app->lincko->data['uid'];
			$key = (new Messages)->getTable();
			$msg = $app->trans->getBRUT('api', 11, 4); //Message accessed.
			$data = new Data();
			$force_partial = new \stdClass;
			$force_partial->$uid = new \stdClass;
			$force_partial->$uid->$key = new \stdClass;

			$collect = Messages::getCollection([$parent_id], false, ['id']);
			foreach ($collect as $value) {
				$force_partial->$uid->$key->{$value->id} = true;
			}
			
			$partial = $data->getMissing($force_partial);
			if(isset($partial) && isset($partial->$uid) && !empty($partial->$uid)){
				$app->render(200, array('msg' => array('msg' => $msg, 'partial' => $partial, 'info' => 'reading')));
				return true;
			}
			

		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
