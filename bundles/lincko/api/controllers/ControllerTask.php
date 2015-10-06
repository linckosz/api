<?php
// Category 8

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\libs\Data;

class ControllerTask extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());		
		return true;
	}

	public function create_post(){
		$app = $this->app;
		$data = $this->data;
		if(!isset($data->data)){
			$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4), 'field' => 'undefined'), 'error' => true,)); //No data form received.
			return true;
		}
		$form = $data->data;
		$form_data = new \stdClass;

		// projects_id
		if(isset($form->projects_id)){ $form_data->projects_id = $form->projects_id; }

		// title
		if(isset($form->title)){ $form_data->title = $form->title; }
		else if(isset($form->task_title_text)){ $form_data->title = $form->task_title_text; }

		// comment
		if(isset($form->comment)){ $form_data->comment = $form->comment; }
		else if(isset($form->task_comment_textarea)){ $form_data->comment = $form->task_comment_textarea; }

		$errmsg = $app->trans->getBRUT('api', 9, 1); //Task creation failed. Please try again.
		$errfield = 'undefined';

		if(!isset($form_data->projects_id)){
			$errmsg = $app->trans->getBRUT('api', 9, 5); //Task creation failed. We could not valid the project ID.
		} else if(isset($form_data->title) && !Tasks::validTitle($form_data->title)){
			$errmsg = $app->trans->getBRUT('api', 9, 2); //Task creation failed. We could not valid the title format: - 104 characters max
			$errfield = 'task_title_text';
		} else if(isset($form_data->comment) && !Tasks::validComment($form_data->comment)){
			$errmsg = $app->trans->getBRUT('api', 9, 3); //Task creation failed. We could not valid the comment format: - Unknown error
			$errfield = 'task_comment_textarea';
		} else if(Tasks::isValid($form_data)){
			$task = new Tasks();
			$task->title = $form_data->title;
			if(isset($form_data->comment)){ $task->comment = $form_data->comment; }
			$task->projects_id = $form_data->projects_id;
			if($task->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 9, 4), 'field' => 'undefined'); //Task created.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 201);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}