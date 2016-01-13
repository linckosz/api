<?php
// Category 8

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\libs\Data;

class ControllerProject extends Controller {

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

		// title
		if(isset($form->title)){ $form_data->title = $form->title; }
		else if(isset($form->project_title_text)){ $form_data->title = $form->project_title_text; }

		// description
		if(isset($form->description)){ $form_data->description = $form->description; }
		else if(isset($form->project_description_textarea)){ $form_data->description = $form->project_description_textarea; }

		$errmsg = $app->trans->getBRUT('api', 8, 1); //Project creation failed. Please try again.
		$errfield = 'undefined';

		if(isset($form_data->title) && !Projects::validTitle($form_data->title)){
			$errmsg = $app->trans->getBRUT('api', 8, 2); //Project creation failed. We could not valid the title format: - 104 characters max
			$errfield = 'project_title_text';
		} else if(isset($form_data->description) && !Projects::validDescription($form_data->description)){
			$errmsg = $app->trans->getBRUT('api', 8, 3); //project creation failed. We could not valid the description format: - Unknown error
			$errfield = 'project_description_textarea';
		} else if(Projects::isValid($form_data)){
			$project = new Projects();
			$project->title = $form_data->title;
			if(isset($form_data->description)){ $project->description = $form_data->description; }
			if($project->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 8, 4), 'field' => 'undefined'); //Project created.
				$data = new Data();
				$data->dataUpdateConfirmation($msg, 201);
				return true;
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}