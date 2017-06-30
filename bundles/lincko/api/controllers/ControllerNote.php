<?php
// Category 10

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\libs\Data;

/*

NOTES

	note/read => post
		+id [integer] (the ID of the element)

	note/create => post
		+parent_id [integer] (the ID of the parent project)
		-title [string]
		+comment [string]

	note/update => post
		+id [integer]
		-parent_id [integer]
		-title [string]
		-comment [string]

	note/delete => post
		+id [integer]

	note/restore => post
		+id [integer]

*/

class ControllerNote extends Controller {

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
		if(isset($form->fav) && is_numeric($form->fav)){
			$form->fav = (int) $form->fav;
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		if(isset($form->comment) && is_string($form->comment)){
			$form->comment = STR::HTMLwithReturnLine($form->comment);
		}
		if(isset($form->title) && is_string($form->title)){
			$form->title = trim(STR::break_line_conv(STR::br2space($form->title),' '));
			if(strlen($form->title)==0){
				$form->title = $app->trans->getBRUT('api', 10, 0); //New Note
			} else if(strlen($form->title)>200){
				$form->title = substr($form->title, 0, 197).'...';
			}
		}
		if(isset($form->locked)){
			$form->locked = (int) boolval($form->locked);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 10, 1)."\n"; //Note creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(isset($form->fav) && !Notes::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
		}
		else if(!isset($form->parent_id) || !Notes::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->title) && !Notes::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->comment) && !Notes::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if(isset($form->locked) && !Notes::validBoolean($form->locked, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'locked';
		}
		else if($model = new Notes()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
			$model->parent_id = $form->parent_id;
			if(isset($form->title)){ $model->title = $form->title; } //Optional
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			if(isset($form->locked)){ $model->locked = $form->locked; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 10, 2)); //Note created.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 201, true, $lastvisit, false);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function read_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 10, 3)."\n"; //Note access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Notes::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		}
		else if($model = Notes::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 10, 4); //Note accessed.
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

		$failmsg = $app->trans->getBRUT('api', 10, 5)."\n"; //Note update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';
		if(!isset($form->id) || !Notes::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		}
		else if(isset($form->fav) && !Notes::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
		}
		else if(isset($form->parent_id) && !Notes::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->title) && !Notes::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->comment) && !Notes::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if(isset($form->locked) && !Notes::validBoolean($form->locked, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'locked';
		}
		else if($model = Notes::withTrashed()->find($form->id)){
			if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->title)){ $model->title = $form->title; } //Optional
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			if(isset($form->locked)){ $model->locked = $form->locked; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(isset($form->locked) || count($dirty)>0 || $pivots){
				//For deleted items we can modify links only
				if(count($dirty)==0 && $pivots){
					$model->enableTrash(true);
				}
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 10, 6)); //Note updated.
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

		$failmsg = $app->trans->getBRUT('api', 10, 7)."\n"; //Note deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Notes::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		} else if($model = Notes::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 10, 8)); //Note deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Notes::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 10, 9); //Note already deleted.
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

		$failmsg = $app->trans->getBRUT('api', 10, 20)."\n"; //Note restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Notes::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		} else if($model = Notes::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 10, 21)); //Note restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Notes::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 10, 22); //Note already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function clone_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 10, 7)."\n"; //Note deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Notes::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 14); //We could not validate the note ID.
			$errfield = 'id';
		} else if($model = Notes::getModel($form->id)){
			if($model->clone()){

				//Setup dependencies
				$pivots = new \stdClass;
				$save = false;

				//files => link
				$items = $model->files;
				foreach ($items as $item) {
					$pivot = $item->pivot;
					$attributes = $pivot->toArray();
					foreach ($attributes as $key => $value) {
						if($key=='notes_id' || $key=='files_id'){
							continue;
						}
						if(!isset($pivots->{'files>'.$key})){
							$pivots->{'files>'.$key} = new \stdClass;
						}
						$pivots->{'files>'.$key}->{$item->id} = $value;
						$save = true;
					}
				}

				//tasks => link
				$items = $model->tasks;
				foreach ($items as $item) {
					$pivot = $item->pivot;
					$attributes = $pivot->toArray();
					foreach ($attributes as $key => $value) {
						if($key=='notes_id' || $key=='tasks_id'){
							continue;
						}
						if(!isset($pivots->{'tasks>'.$key})){
							$pivots->{'tasks>'.$key} = new \stdClass;
						}
						$pivots->{'tasks>'.$key}->{$item->id} = $value;
						$save = true;
					}
				}

				if($save){
					$clone->forceGiveAccess();
					$clone->saveHistory(false);
					$clone->pivots_format($pivots, false);
					$clone->save();
				}

				$msg = array('msg' => $app->trans->getBRUT('api', 10, 13)); //Note copied.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, true, $lastvisit, true, $schema);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function lock_start_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Notes::validNumeric($form->id)){ //Required
			if($model = Notes::getModel($form->id)){
				$result = $model->startLock();
			}
		}
		if($result[1]){
			//return $this->read_post();
			return (new ControllerData)->latest_post();
		} else {
			$msg = $app->trans->getBRUT('api', 10, 4); //Note accessed.
			$app->render(200, array('msg' => $msg));
			return true;
		}
	}

	public function lock_unlock_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Notes::validNumeric($form->id)){ //Required
			if($model = Notes::getModel($form->id)){
				$result = $model->unLock();
			}
		}
		if($result[1]){
			//return $this->read_post();
			return (new ControllerData)->latest_post();
		} else {
			$msg = $app->trans->getBRUT('api', 10, 4); //Note accessed.
			$app->render(200, array('msg' => $msg));
			return true;
		}
	}

	public function lock_check_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Notes::validNumeric($form->id)){ //Required
			if($model = Notes::getModel($form->id)){
				$result = $model->checkLock();
			}
		}
		if($result[1]>0){
			return (new ControllerData)->latest_post();
		} else {
			return $this->read_post(); //We do only read the item itself if lastvisit is missing
		}
	}

}
