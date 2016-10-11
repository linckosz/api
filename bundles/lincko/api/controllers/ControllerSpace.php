<?php
// Category 18

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Spaces;
use \bundles\lincko\api\models\libs\Data;

/*

SPACES

	space/read => post
		+id [integer] (the ID of the element)

	space/create => post
		+parent_id [integer] (the ID of the parent project)
		-name [string]
		-tasks [boolean]
		-notes [boolean]
		-files [boolean]
		-chats [boolean]

	space/update => post
		+id [integer]
		-parent_id [integer]
		-name [string]
		-tasks [boolean]
		-notes [boolean]
		-files [boolean]
		-chats [boolean]

	space/delete => post
		+id [integer]

	space/restore => post
		+id [integer]

*/

class ControllerSpace extends Controller {

	protected $app = NULL;
	protected $data = NULL;
	protected $form = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
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
		//Convert NULL to empty string to help isset returning true
		foreach ($form as $key => $value) {
			if(!is_numeric($value) && empty($value)){ //Exclude 0 to become an empty string
				$form->$key = '';
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
		if(isset($form->name) && is_string($form->name)){
			$form->name = trim(STR::break_line_conv($form->name,' '));
			if(strlen($form->name)==0){
				$form->name = $app->trans->getBRUT('api', 18, 0); //New Space
			}
		}
		if(isset($form->tasks)){
			$form->tasks = (int) boolval($form->tasks);
		}
		if(isset($form->notes)){
			$form->notes = (int) boolval($form->notes);
		}
		if(isset($form->files)){
			$form->files = (int) boolval($form->files);
		}
		if(isset($form->chats)){
			$form->chats = (int) boolval($form->chats);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 18, 1)."\n"; //Space creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Spaces::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->name) || !Spaces::validChar($form->name)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->color) && !Spaces::validChar($form->color, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'color';
		}
		else if(isset($form->icon) && !Spaces::validChar($form->icon, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'icon';
		}
		else if(isset($form->tasks) && !Spaces::validBoolean($form->tasks, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'tasks';
		}
		else if(isset($form->notes) && !Spaces::validBoolean($form->notes, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'notes';
		}
		else if(isset($form->files) && !Spaces::validBoolean($form->files, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'files';
		}
		else if(isset($form->chats) && !Spaces::validBoolean($form->chats, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'chats';
		}
		else if($model = new Spaces()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_id = $form->parent_id;
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->color)){ $model->color = $form->color; } //Optional
			if(isset($form->icon)){ $model->icon = $form->icon; } //Optional
			if(isset($form->tasks)){ $model->tasks = $form->tasks; } //Optional
			if(isset($form->notes)){ $model->notes = $form->notes; } //Optional
			if(isset($form->files)){ $model->files = $form->files; } //Optional
			if(isset($form->chats)){ $model->chats = $form->chats; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 18, 2)); //Space created.
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

		$failmsg = $app->trans->getBRUT('api', 18, 3)."\n"; //Space access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Spaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 32); //We could not validate the space ID.
			$errfield = 'id';
		}
		else if($model = Spaces::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 18, 4); //Space accessed.
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

		$failmsg = $app->trans->getBRUT('api', 18, 5)."\n"; //Space update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Spaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 32); //We could not validate the space ID.
			$errfield = 'id';
		}
		else if(isset($form->parent_id) && !Spaces::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->name) && !Spaces::validChar($form->name, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->color) && !Spaces::validChar($form->color, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'color';
		}
		else if(isset($form->icon) && !Spaces::validChar($form->icon, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'icon';
		}
		else if(isset($form->tasks) && !Spaces::validBoolean($form->tasks, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'tasks';
		}
		else if(isset($form->notes) && !Spaces::validBoolean($form->notes, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'notes';
		}
		else if(isset($form->files) && !Spaces::validBoolean($form->files, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'files';
		}
		else if(isset($form->chats) && !Spaces::validBoolean($form->chats, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'chats';
		}
		else if($model = Spaces::find($form->id)){
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->color)){ $model->color = $form->color; } //Optional
			if(isset($form->icon)){ $model->icon = $form->icon; } //Optional
			if(isset($form->tasks)){ $model->tasks = $form->tasks; } //Optional
			if(isset($form->notes)){ $model->notes = $form->notes; } //Optional
			if(isset($form->files)){ $model->files = $form->files; } //Optional
			if(isset($form->chats)){ $model->chats = $form->chats; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$msg = array('msg' => $app->trans->getBRUT('api', 18, 6)); //Space updated.
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

		$failmsg = $app->trans->getBRUT('api', 18, 7)."\n"; //Space deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Spaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 32); //We could not validate the space ID.
			$errfield = 'id';
		}

		if($model = Spaces::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 18, 8)); //Space deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Spaces::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 18, 9); //Space already deleted.
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

		$failmsg = $app->trans->getBRUT('api', 18, 20)."\n"; //Space restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Spaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 32); //We could not validate the space ID.
			$errfield = 'id';
		}

		if($model = Spaces::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 18, 21)); //Space restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Spaces::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 18, 22); //Space already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
