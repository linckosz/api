<?php
// Category 11

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\libs\Data;

/*

COMMENTS

	comment/read => post
		+id [integer] (the ID of the element)

	comment/create => post
		+parent_type [string] (the type of the parent object, or null)
		+parent_id [integer] (the ID of the parent object, or -1)
		+comment [string]

	comment/update => post
		+id [integer]
		-parent_type [string]
		-parent_id [integer]
		-comment [string]

	comment/delete => post
	!rejected!

	comment/restore => post
	!rejected!

*/

class ControllerComment extends Controller {

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
		if(isset($form->parent_type) && is_string($form->parent_type)){
			$form->parent_type = strtolower(trim($form->parent_type));
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 11, 1)."\n"; //Message creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		//We do not allow the user to comment on himself, we switch to his MyPlacehoder by default
		if(isset($form->parent_type) && $form->parent_type == 'users' && isset($form->parent_id) && $form->parent_id == $app->lincko->data['uid'] ){
			$form->parent_type = 'projects';
			$project = Projects::
				  orderBy('created_by', 'asc') //By security, always take the ealiest created private project
				->where('personal_private', $app->lincko->data['uid'])
				->where('projects.parent_id', null) //Insure to get only the workspace information
				->first();
			$form->parent_id = $project->id;
		}

		if(!isset($form->parent_type) || !Comments::validType($form->parent_type)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 7); //We could not validate the parent type.
			$errfield = 'parent_type';
		}
		else if(!isset($form->parent_id) || !Comments::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->comment) || !Comments::validText($form->comment)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if($model = new Comments()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_type = $form->parent_type;
			$model->parent_id = $form->parent_id;
			$model->comment = $form->comment;
			if(empty($model->parent_type)){
				$model->parent_type = null;
				$model->parent_id = 0;
			}
			$model->pivots_format($form, false);
			if($model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 11, 2)); //Message created.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 201);
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

		if(!isset($form->id) || !Comments::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 1); //We could not validate the message ID.
			$errfield = 'id';
		}
		else if($model = Comments::find($form->id)){
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

		$failmsg = $app->trans->getBRUT('api', 11, 23)."\n"; //Message recall failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Comments::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 1); //We could not validate the message ID.
			$errfield = 'id';
		}
		else if($model = Comments::find($form->id)){
			$model->recalled_by = (int)$app->lincko->data['uid'];
			if($model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 11, 24)); //Message recalled.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 200);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
