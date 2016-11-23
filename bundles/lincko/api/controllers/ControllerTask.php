<?php
// Category 9

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\libs\Data;

/*

TASKS

	task/read => post
		+id [integer] (the ID of the element)

	task/create => post
		+parent_id [integer] (the ID of the parent project)
		+title [string]
		-comment [string]
		-start [interger | timestamp] (the beginning of the task)
		-duration [interger | seconds] (the duration of the task)
		-fixed [boolean] (If the start date move along with a dependant task)
		-approved [boolean] (If the task is completed and approved)
		-status [integer] (0:pause / 1:done / 2:ongoing / 3:canceled) => this might be redefined later according to spaces
		-progress [integer] (task progression, 0-100)


	task/update => post
		+id [integer]
		-parent_id [integer]
		-title [string]
		-comment [string]
		-start [interger | timestamp]
		-duration [interger | seconds]
		-fixed [boolean]
		-approved [boolean]
		-status [integer]
		-progress [integer]

	task/delete => post
		+id [integer]

	task/restore => post
		+id [integer]

*/

class ControllerTask extends Controller {

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
		if(isset($form->title) && is_string($form->title)){
			$form->title = trim(STR::break_line_conv(STR::br2space($form->title),' '));
			if(strlen($form->title)==0){
				$form->title = $app->trans->getBRUT('api', 9, 0); //New Task
			} else if(strlen($form->title)>200){
				$form->title = substr($form->title, 0, 197).'...';
			}
		}
		if(isset($form->start) && is_numeric($form->start)){
			$form->start = (int) $form->start;
			$form->start = (new \DateTime('@'.$form->start))->format('Y-m-d H:i:s');
		}
		if(isset($form->duration) && is_numeric($form->duration)){
			$form->duration = (int) $form->duration;
		}
		if(isset($form->fixed)){
			$form->fixed = (int) boolval($form->fixed);
		}
		if(isset($form->approved)){
			$form->approved = (int) boolval($form->approved);
		}
		if(isset($form->status) && is_numeric($form->status)){
			$form->status = (int) $form->status;
		}
		if(isset($form->progress) && is_numeric($form->progress)){
			$form->progress = (int) $form->progress;
		}
		if(isset($form->milestone)){
			$form->milestone = (int) boolval($form->milestone);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 9, 1)."\n"; //Task creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(isset($form->fav) && !Tasks::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
		}
		else if(!isset($form->parent_id) || !Tasks::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->title) || !Tasks::validTitle($form->title)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->comment) && !Tasks::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if(isset($form->start) && !Tasks::validDate($form->start, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 22); //We could not validate the date format: - Timestamp
			$errfield = 'start';
		}
		else if(isset($form->duration) && !Tasks::validNumeric($form->duration, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 23); //We could not validate the duration format: - Seconds
			$errfield = 'duration';
		}
		else if(isset($form->fixed) && !Tasks::validBoolean($form->fixed, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'fixed';
		}
		else if(isset($form->approved) && !Tasks::validBoolean($form->approved, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'approved';
		}
		else if(isset($form->status) && !Tasks::validNumeric($form->status, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'status';
		}
		else if(isset($form->progress) && !Tasks::validProgress($form->progress, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 26); //We could not validate the format: - Pourcentage
			$errfield = 'progress';
		}
		else if(isset($form->milestone) && !Tasks::validBoolean($form->milestone, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'milestone';
		}
		else if($model = new Tasks()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
			$model->parent_id = $form->parent_id;
			$model->title = $form->title;
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			if(isset($form->start)){ $model->start = $form->start; } //Optional
			if(isset($form->duration)){ $model->duration = $form->duration; } //Optional
			if(isset($form->fixed)){ $model->fixed = $form->fixed; } //Optional
			if(isset($form->approved)){ $model->approved = $form->approved; } //Optional
			if(isset($form->status)){ $model->status = $form->status; } //Optional
			if(isset($form->progress)){ $model->progress = $form->progress; } //Optional
			if(isset($form->milestone)){ $model->milestone = $form->milestone; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 9, 2)); //Task created.
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

		$failmsg = $app->trans->getBRUT('api', 9, 3)."\n"; //Task access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Tasks::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 5); //We could not validate the task ID.
			$errfield = 'id';
		}
		else if($model = Tasks::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 9, 4); //Task accessed.
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

		$failmsg = $app->trans->getBRUT('api', 9, 5)."\n"; //Task update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Tasks::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 5); //We could not validate the task ID.
			$errfield = 'id';
		}
		else if(isset($form->fav) && !Tasks::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
		}
		else if(isset($form->parent_id) && !Tasks::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->title) && !Tasks::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->comment) && !Tasks::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if(isset($form->start) && !Tasks::validDate($form->start, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 22); //We could not validate the date format: - Timestamp
			$errfield = 'start';
		}
		else if(isset($form->duration) && !Tasks::validNumeric($form->duration, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 23); //We could not validate the duration format: - Seconds
			$errfield = 'duration';
		}
		else if(isset($form->fixed) && !Tasks::validBoolean($form->fixed, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'fixed';
		}
		else if(isset($form->approved) && !Tasks::validBoolean($form->approved, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'approved';
		}
		else if(isset($form->status) && !Tasks::validNumeric($form->status, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'status';
		}
		else if(isset($form->progress) && !Tasks::validProgress($form->progress, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 26); //We could not validate the format: - Pourcentage
			$errfield = 'progress';
		}
		else if(isset($form->milestone) && !Tasks::validBoolean($form->milestone, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'milestone';
		}
		else if($model = Tasks::find($form->id)){
			if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->title)){ $model->title = $form->title; } //Optional
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			if(isset($form->start)){ $model->start = $form->start; } //Optional
			if(isset($form->duration)){ $model->duration = $form->duration; } //Optional
			if(isset($form->fixed)){ $model->fixed = $form->fixed; } //Optional
			if(isset($form->approved)){ $model->approved = $form->approved; } //Optional
			if(isset($form->status)){ $model->status = $form->status; } //Optional
			if(isset($form->progress)){ $model->progress = $form->progress; } //Optional
			if(isset($form->milestone)){ $model->milestone = $form->milestone; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$msg = array('msg' => $app->trans->getBRUT('api', 9, 6)); //Task updated.
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

		$failmsg = $app->trans->getBRUT('api', 9, 7)."\n"; //Task deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Tasks::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 5); //We could not validate the task ID.
			$errfield = 'id';
		}

		if($model = Tasks::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 9, 8)); //Task deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Tasks::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 9, 9); //Task already deleted.
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

		$failmsg = $app->trans->getBRUT('api', 9, 20)."\n"; //Task restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Tasks::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 5); //We could not validate the task ID.
			$errfield = 'id';
		}

		if($model = Tasks::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 9, 21)); //Task restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Tasks::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 9, 22); //Task already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function lock_start_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Tasks::validNumeric($form->id)){ //Required
			if($model = Tasks::getModel($form->id)){
				$result = $model->startLock();
			}
		}
		if($result[1]){
			return $this->read_post();
		} else {
			$msg = $app->trans->getBRUT('api', 9, 4); //Task accessed.
			$app->render(200, array('msg' => $msg));
			return true;
		}
	}

	public function lock_unlock_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Tasks::validNumeric($form->id)){ //Required
			if($model = Tasks::getModel($form->id)){
				$result = $model->unLock();
			}
		}
		if($result[1]){
			return $this->read_post();
		} else {
			$msg = $app->trans->getBRUT('api', 9, 4); //Task accessed.
			$app->render(200, array('msg' => $msg));
			return true;
		}
	}

	public function lock_check_post(){
		$app = $this->app;
		$form = $this->form;
		$result = false;
		if(isset($form->id) && Tasks::validNumeric($form->id)){ //Required
			if($model = Tasks::getModel($form->id)){
				$result = $model->checkLock();
			}
		}
		if($result[1]){
			return $this->read_post();
		} else {
			$msg = $app->trans->getBRUT('api', 9, 4); //Task accessed.
			$app->render(200, array('msg' => $msg));
			return true;
		}
	}

}
