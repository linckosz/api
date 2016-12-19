<?php
// Category 16

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\libs\Data;

/*

WORKSPACES

	workspaces/read => post
		+id [integer] (the ID of the element)

	workspaces/create => post
		+name [string] (The name of the workspace)
		-domain [string] (The domain of a team/company website)
		-url [string] (Subdomain of a workspace, toto.lincko.com)

	workspaces/update => post
		+id [integer]
		-name [string]
		-domain [string]
		-url [string | alphanumeric]

	workspaces/delete => post
	!rejected!

	workspaces/restore => post
	!rejected!

*/

class ControllerWorkspace extends Controller {

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
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		if(isset($form->name) && is_string($form->name)){
			$form->name = trim(STR::break_line_conv($form->name,' '));
			if(strlen($form->name)==0){
				$form->name = $app->trans->getBRUT('api', 16, 0); //New Workspace
			}
		}
		if(isset($form->domain) && is_string($form->domain)){
			$form->domain = trim($form->domain);
		}
		if(isset($form->url) && is_string($form->url)){
			$form->url = trim($form->url);
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 16, 1)."\n"; //Workspace creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->name) || !Workspaces::validChar($form->name)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->domain) && !Workspaces::validDomain($form->domain, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 19); //We could not validate the domain format: - {domain}.{ext} - 191 characters maxi
			$errfield = 'domain';
		}
		else if(isset($form->url) && !Workspaces::validURL($form->url, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 20); //We could not validate the URL format: - 104 characters max - Alphanumeric
			$errfield = 'url';
		}
		else if($model = new Workspaces()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->name = $form->name;
			if(isset($form->domain)){ $model->domain = $form->domain; } //Optional
			if(isset($form->url)){ $model->url = $form->url; } //Optional
			$model->pivots_format($form, true);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 16, 2)); //Workspace created.
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

		$failmsg = $app->trans->getBRUT('api', 16, 3)."\n"; //Workspace access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Workspaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 4); //We could not validate the project ID.
			$errfield = 'id';
		}
		else if($model = Workspaces::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 16, 4); //Workspace accessed.
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

		$failmsg = $app->trans->getBRUT('api', 16, 5)."\n"; //Workspace update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Workspaces::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 28); //We could not validate the discussion group ID.
			$errfield = 'id';
		}
		if(isset($form->name) && !Workspaces::validChar($form->name, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->domain) && !Workspaces::validDomain($form->domain, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 19); //We could not validate the domain format: - {domain}.{ext} - 191 characters maxi
			$errfield = 'domain';
		}
		else if(isset($form->url) && !Workspaces::validURL($form->url, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 20); //We could not validate the URL format: - 104 characters max - Alphanumeric
			$errfield = 'url';
		}
		else if($model = Workspaces::find($form->id)){
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->domain)){ $model->domain = $form->domain; } //Optional
			if(isset($form->url)){ $model->url = $form->url; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 16, 6)); //Workspace updated.
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
		$errmsg = $app->trans->getBRUT('api', 16, 7)."\n".$app->trans->getBRUT('api', 0, 6); //Workspace deletion failed. You are not allowed to delete the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 16, 20)."\n".$app->trans->getBRUT('api', 0, 9); //Workspace restoration failed. You are not allowed to restore the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

}
