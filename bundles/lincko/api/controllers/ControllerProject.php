<?php
// Category 12

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\libs\Data;
use WideImage\WideImage;
use Endroid\QrCode\QrCode;

/*

PROJECTS

	project/read => post
		+id [integer] (the ID of the element)

	project/create => post
		+parent_id [integer] (the ID of the parent workspace)
		+title [string]
		-description [string]

	project/update => post
		+id [integer]
		-parent_id [integer]
		-title [string]
		-description [string]

	project/delete => post
		+id [integer]

	project/restore => post
		+id [integer]

*/

class ControllerProject extends Controller {

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
		if(isset($form->title) && is_string($form->title)){
			$form->title = trim(STR::break_line_conv(STR::br2space($form->title),' '));
			if(strlen($form->title)==0){
				$form->title = $app->trans->getBRUT('api', 12, 0); //New Project
			} else if(strlen($form->title)>200){
				$form->title = substr($form->title, 0, 197).'...';
			}
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		if(isset($form->resume) && is_numeric($form->resume)){
			$form->resume = (int) $form->resume;
			if($form->resume<0){
				$form->resume = 24 + $form->resume;
			}
			if($form->resume>=24){
				$form->resume = 0;
			}
		}
		if(isset($form->public)){
			$form->public = (int) boolval($form->public);
		}
		if(isset($form->qrcode)){
			$form->qrcode = (int) boolval($form->qrcode);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 12, 1)."\n"; //Project creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Projects::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->title) || !Projects::validTitle($form->title)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->description) && !Projects::validText($form->description, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'description';
		}
		else if(isset($form->diy) && !Projects::validDIY($form->diy, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 33); //We could not validate the format: - JSON
			$errfield = 'diy';
		}
		else if(isset($form->public) && !Projects::validBoolean($form->public, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'public';
		}
		else if(isset($form->qrcode) && !Projects::validBoolean($form->qrcode, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'qrcode';
		}
		else if($model = new Projects()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_id = $form->parent_id;
			$model->title = $form->title;
			if(isset($form->description)){ $model->description = $form->description; } //Optional
			if(isset($form->diy)){ $model->diy = $form->diy; } //Optional
			if(isset($form->public)){
				if($form->public){
					$form->qrcode = 1;
				} else {
					$form->qrcode = 0;
				}
			} //Optional
			if(isset($form->qrcode)){
				if($form->qrcode){
					$model->qrcode = STR::random(8);
				} else {
					$model->qrcode = null;
				}
			} //Optional
			if(isset($form->resume)){ $model->resume = $form->resume; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 12, 2)); //Project created.
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

		$failmsg = $app->trans->getBRUT('api', 12, 3)."\n"; //Project access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Projects::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		}
		else if($model = Projects::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 12, 4); //Project accessed.
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

		$failmsg = $app->trans->getBRUT('api', 12, 5)."\n"; //Project update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Projects::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		}
		else if(isset($form->parent_id) && !Projects::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->title) && !Projects::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->description) && !Projects::validText($form->description, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'description';
		}
		else if(isset($form->diy) && !Projects::validDIY($form->diy, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 33); //We could not validate the format: - JSON
			$errfield = 'diy';
		}
		else if(isset($form->public) && !Projects::validBoolean($form->public, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'public';
		}
		else if(isset($form->qrcode) && !Projects::validBoolean($form->qrcode, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 24); //We could not validate the format: - Boolean
			$errfield = 'qrcode';
		}
		else if($model = Projects::find($form->id)){
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->title)){ $model->title = $form->title; } //Optional
			if(isset($form->description)){ $model->description = $form->description; } //Optional
			if(isset($form->resume)){ $model->resume = $form->resume; } //Optional
			if(isset($form->diy)){ $model->diy = $form->diy; } //Optional
			if(isset($form->public)){
				$model->public = $form->public;
				if($model->public && is_null($model->qrcode)){
					$model->qrcode = STR::random(8); //Initialize if never done
				}
			} //Optional
			if(isset($form->qrcode)){
				if($form->qrcode){
					$model->qrcode = STR::random(8);
				} else {
					$model->qrcode = null;
				}
			} //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 12, 6)); //Project updated.
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

		$failmsg = $app->trans->getBRUT('api', 12, 10)."\n"; //Project archiving failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Projects::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		} else if($model = Projects::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 12, 11)); //Project archived.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Projects::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 12, 12); //Project already archived.
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

		$failmsg = $app->trans->getBRUT('api', 12, 20)."\n"; //Project restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Projects::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		} else if($model = Projects::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 12, 21)); //Project restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Projects::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 12, 22); //Project already present.
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

		$failmsg = $app->trans->getBRUT('api', 0, 10)."\n"; //Operation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Projects::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		}
		else if(isset($form->title) && !Projects::validTitle($form->title, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 2); //We could not validate the title format: - 200 characters max
			$errfield = 'title';
		}
		else if(isset($form->description) && !Projects::validText($form->description, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'description';
		}
		else if($model = Projects::getModel($form->id)){
			if($clone = $model->clone()){
				if(isset($form->temp_id)){ $clone->temp_id = $form->temp_id; } //Optional
				if(isset($form->title)){ $clone->title = $form->title; } //Optional
				if(isset($form->description)){ $clone->description = $form->description; } //Optional
				$clone->save();
				$msg = array('msg' => $app->trans->getBRUT('api', 12, 13)); //Project copied.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, true, $lastvisit, true, $schema);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function my_project_get(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 12, 3)."\n"; //Project access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if($model = Projects::getPersonal()){
			if($model->checkAccess(false) && $model->personal_private == $app->lincko->data['uid']){
				$app->render(200, array('msg' => $model->id));
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
