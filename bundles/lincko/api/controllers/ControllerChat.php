<?php
// Category 13

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Chats;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\PivotUsers;

/*

CHATS

	chat/read => post
		+id [integer] (the ID of the element)

	chat/create => post
		+parent_type [string] (the type of the parent object, or null)
		+parent_id [integer] (the ID of the parent object, or -1)
		+title [string]

	chat/update => post
		+id [integer]
		-parent_type [string]
		-parent_id [integer]
		-title [string]

	chat/delete => post
		+id [integer] (the ID of the element)

	chat/restore => post
		+id [integer] (the ID of the element)

*/
class ControllerChat extends Controller {

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
		if(isset($form->parent_type) && is_string($form->parent_type)){
			$form->parent_type = strtolower(trim($form->parent_type));
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
			if(is_null($form->parent_type)){ //At root level
				$form->parent_id = 0;
			}
		}
		if(isset($form->title) && is_string($form->title)){
			$form->title = trim(STR::break_line_conv(STR::br2space($form->title),' '));
			if(strlen($form->title)==0){
				$form->title = $app->trans->getBRUT('api', 13, 0); //New Discussion group
			} else if(strlen($form->title)>200){
				$form->title = substr($form->title, 0, 197).'...';
			}
		}
		if(isset($form->single) && is_numeric($form->single)){
			$form->single = (int) $form->single;
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 13, 1)."\n"; //Discussion group creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_type) || !Chats::validType($form->parent_type)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 7); //We could not validate the parent type.
			$errfield = 'parent_type';
		}
		else if(!isset($form->parent_id) || !Chats::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->title) || !Chats::validTitle($form->title)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if($model = new Chats()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_type = $form->parent_type;
			$model->parent_id = $form->parent_id;
			$model->title = $form->title;
			if(empty($model->parent_type)){
				$model->parent_type = null;
				$model->parent_id = 0;
			}
			$save = true;
			if(isset($form->single)){ //Optional, create single user chat
				$exits = false;
				$pivot = new PivotUsers(array('chats'));
				if($pivot->tableExists($pivot->getTable())){
					$list = $pivot->where('users_id', $app->lincko->data['uid'])->where('single', $form->single)->withTrashed()->get();
					if($list->count()>0){
						$save = false;
					}
				}
				if($save && $guest = Users::find($form->single)){
					$pivots = new \stdClass;
					$pivots->{'users>access'} = new \stdClass;
					$pivots->{'users>access'}->{$app->lincko->data['uid']} = true;
					$pivots->{'users>access'}->{$form->single} = true;
					$pivots->{'users>single'} = new \stdClass;
					$pivots->{'users>single'}->{$app->lincko->data['uid']} = $form->single;
					$pivots->{'users>single'}->{$form->single} = $app->lincko->data['uid'];
					$model->single = true;
					$model->pivots_format($pivots, false);
				}
			} else {
				$model->pivots_format($form, false);
			}
			if($save && $model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 13, 2)); //Discussion group created.
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

		$failmsg = $app->trans->getBRUT('api', 13, 3)."\n"; //Discussion group access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Chats::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 13); //We could not validate the discussion group ID.
			$errfield = 'id';
		}
		else if($model = Chats::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 13, 4); //Discussion group accessed.
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

		$failmsg = $app->trans->getBRUT('api', 13, 5)."\n"; //Discussion group update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Chats::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 13); //We could not validate the discussion group ID.
			$errfield = 'id';
		}
		else if(isset($form->parent_type) && !Chats::validType($form->parent_type, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 7); //We could not validate the parent type.
			$errfield = 'parent_type';
		}
		else if(isset($form->parent_id) && !Chats::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->title) && !Chats::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if($model = Chats::find($form->id)){
			if(isset($form->parent_type)){ $model->parent_type = $form->parent_type; } //Optional
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->title)){ $model->title = $form->title; } //Optional
			if(empty($model->parent_type)){
				$model->parent_type = null;
				$model->parent_id = 0;
			}
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if($model->single && count($dirty)>0){
				$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
			} else if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 13, 6)); //Discussion group updated.
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

	public function delete_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 13, 7)."\n"; //Discussion group deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Chats::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		} else if($model = Chats::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 13, 8)); //Discussion group deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Chats::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 13, 9); //Discussion group already deleted.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 13, 20)."\n"; //Discussion group restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Chats::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		} else if($model = Chats::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 13, 21)); //Discussion group restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Chats::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 13, 22); //Discussion group already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
