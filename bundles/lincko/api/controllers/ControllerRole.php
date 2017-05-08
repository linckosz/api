<?php
// Category 17

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\STR;
use \bundles\lincko\api\models\data\Roles;
use \bundles\lincko\api\models\libs\Data;

/*

ROLES

	role/read => post
		+id [integer] (the ID of the element)

	role/create => post
		+parent_id [integer] (the ID of the parent workspace)
		+name [string]
		-perm_grant [boolean] (Grant permission)
		-perm_all [integer] (RCUD level: 0-3)
		-perm_workspaces [integer] (RCUD level: 0-3)
		-perm_projects [integer] (RCUD level: 0-3)
		-perm_tasks [integer] (RCUD level: 0-3)
		-perm_notes [integer] (RCUD level: 0-3)
		-perm_files [integer] (RCUD level: 0-3)
		-perm_chats [integer] (RCUD level: 0-3)
		-perm_comments [integer] (RCUD level: 0-3)

	role/update => post
		+id [integer]
		-parent_id [integer]
		-name [string]
		-perm_grant [boolean]
		-perm_all [integer]
		-perm_workspaces [integer]
		-perm_projects [integer]
		-perm_tasks [integer]
		-perm_notes [integer]
		-perm_files [integer]
		-perm_chats [integer]
		-perm_comments [integer]

	role/delete => post
		+id [integer]

	role/restore => post
		+id [integer]

*/

class ControllerRole extends Controller {

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
		if(isset($form->name) && is_string($form->name)){
			$form->name = trim(STR::break_line_conv($form->name,' '));
			if(strlen($form->name)==0){
				$form->name = $app->trans->getBRUT('api', 17, 0); //New Role
			}
		}
		if(isset($form->perm_grant)){
			$form->perm_grant = (int) boolval($form->perm_grant);
			if($form->perm_grant<=0){
				$form->perm_grant = 0;
			} else {
				$form->perm_grant = 1;
			}
		}
		if(isset($form->perm_all) && is_numeric($form->perm_all)){
			$form->perm_all = (int) $form->perm_all;
			if($form->perm_all<0 || $form->perm_all>3 || $form->perm_all==1){
				$form->perm_all = 0;
			}
		}
		if(isset($form->perm_workspaces) && is_numeric($form->perm_workspaces)){
			$form->perm_workspaces = (int) $form->perm_workspaces;
			if($form->perm_workspaces<0 || $form->perm_workspaces>3 || $form->perm_workspaces==1){
				$form->perm_workspaces = 0;
			}
		}
		if(isset($form->perm_projects) && is_numeric($form->perm_projects)){
			$form->perm_projects = (int) $form->perm_projects;
			if($form->perm_projects<0 || $form->perm_projects>3 || $form->perm_projects==1){
				$form->perm_projects = 0;
			}
		}
		if(isset($form->perm_tasks) && is_numeric($form->perm_tasks)){
			$form->perm_tasks = (int) $form->perm_tasks;
			if($form->perm_tasks<0 || $form->perm_tasks>3 || $form->perm_tasks==1){
				$form->perm_tasks = 0;
			}
		}
		if(isset($form->perm_notes) && is_numeric($form->perm_notes)){
			$form->perm_notes = (int) $form->perm_notes;
			if($form->perm_notes<0 || $form->perm_notes>3 || $form->perm_notes==1){
				$form->perm_notes = 0;
			}
		}
		if(isset($form->perm_files) && is_numeric($form->perm_files)){
			$form->perm_files = (int) $form->perm_files;
			if($form->perm_files<0 || $form->perm_files>3 || $form->perm_files==1){
				$form->perm_files = 0;
			}
		}
		if(isset($form->perm_chats) && is_numeric($form->perm_chats)){
			$form->perm_chats = (int) $form->perm_chats;
			if($form->perm_chats<0 || $form->perm_chats>3 || $form->perm_chats==1){
				$form->perm_chats = 0;
			}
		}
		if(isset($form->perm_comments) && is_numeric($form->perm_comments)){
			$form->perm_comments = (int) $form->perm_comments;
			if($form->perm_comments<0 || $form->perm_comments>3 || $form->perm_comments==1){
				$form->perm_comments = 0;
			}
		}
		if(isset($form->perm_namecards) && is_numeric($form->perm_namecards)){
			$form->perm_namecards = (int) $form->perm_namecards;
			if($form->perm_namecards<0 || $form->perm_namecards>3 || $form->perm_namecards==1){
				$form->perm_namecards = 0;
			}
		}
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 17, 1)."\n"; //Role creation failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->parent_id) || !Roles::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(!isset($form->name) || !Roles::validChar($form->name)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->perm_grant) && !Roles::validBoolean($form->perm_grant, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 17); //We could not validate the grant access.
			$errfield = 'perm_grant';
		}
		else if(isset($form->perm_all) && !Roles::validRCUD($form->perm_all, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_all';
		}
		else if(isset($form->perm_workspaces) && !Roles::validRCUD($form->perm_workspaces, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_workspaces';
		}
		else if(isset($form->perm_projects) && !Roles::validRCUD($form->perm_projects, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_projects';
		}
		else if(isset($form->perm_tasks) && !Roles::validRCUD($form->perm_tasks, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_tasks';
		}
		else if(isset($form->perm_notes) && !Roles::validRCUD($form->perm_notes, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_notes';
		}
		else if(isset($form->perm_files) && !Roles::validRCUD($form->perm_files, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_files';
		}
		else if(isset($form->perm_chats) && !Roles::validRCUD($form->perm_chats, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_chats';
		}
		else if(isset($form->perm_comments) && !Roles::validRCUD($form->perm_comments, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_comments';
		}
		else if(isset($form->perm_namecards) && !Roles::validRCUD($form->perm_namecards, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_namecards';
		}
		else if($model = new Roles()){
			if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
			$model->parent_id = $form->parent_id;
			$model->name = $form->name;
			if(isset($form->perm_grant)){ $model->perm_grant = $form->perm_grant; } //Optional
			if(isset($form->perm_all)){ $model->perm_all = $form->perm_all; } //Optional
			//We don't allow editing workspaces
			if(isset($form->perm_projects)){ $model->perm_projects = $form->perm_projects; } //Optional
			if(isset($form->perm_tasks)){ $model->perm_tasks = $form->perm_tasks; } //Optional
			if(isset($form->perm_notes)){ $model->perm_notes = $form->perm_notes; } //Optional
			if(isset($form->perm_files)){ $model->perm_files = $form->perm_files; } //Optional
			//We don't allow editing chats
			//We don't allow editing comments
			if(isset($form->perm_namecards)){ $model->perm_namecards = $form->perm_namecards; } //Optional
			$model->pivots_format($form, false);
			if($model->getParentAccess() && $model->save()){
				$msg = array('msg' => $app->trans->getBRUT('api', 17, 2)); //Role created.
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

		$failmsg = $app->trans->getBRUT('api', 17, 3)."\n"; //Role access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Roles::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 15); //We could not validate the role ID.
			$errfield = 'id';
		}
		else if($model = Roles::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 17, 4); //Role accessed.
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

		$failmsg = $app->trans->getBRUT('api', 17, 5)."\n"; //Role update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Roles::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 15); //We could not validate the role ID.
			$errfield = 'id';
		}
		else if(isset($form->parent_id) && !Roles::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->name) && !Roles::validChar($form->name, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->perm_grant) && !Roles::validBoolean($form->perm_grant, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 17); //We could not validate the grant access.
			$errfield = 'perm_grant';
		}
		else if(isset($form->perm_all) && !Roles::validRCUD($form->perm_all, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_all';
		}
		else if(isset($form->perm_workspaces) && !Roles::validRCUD($form->perm_workspaces, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_workspaces';
		}
		else if(isset($form->perm_projects) && !Roles::validRCUD($form->perm_projects, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_projects';
		}
		else if(isset($form->perm_tasks) && !Roles::validRCUD($form->perm_tasks, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_tasks';
		}
		else if(isset($form->perm_notes) && !Roles::validRCUD($form->perm_notes, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_notes';
		}
		else if(isset($form->perm_files) && !Roles::validRCUD($form->perm_files, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_files';
		}
		else if(isset($form->perm_chats) && !Roles::validRCUD($form->perm_chats, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_chats';
		}
		else if(isset($form->perm_comments) && !Roles::validRCUD($form->perm_comments, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_comments';
		}
		else if(isset($form->perm_namecards) && !Roles::validRCUD($form->perm_namecards, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 16); //We could not validate the user permission.
			$errfield = 'perm_namecards';
		}
		else if($model = Roles::find($form->id)){
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->perm_grant)){ $model->perm_grant = $form->perm_grant; } //Optional
			if(isset($form->perm_all)){ $model->perm_all = $form->perm_all; } //Optional
			//We don't allow editing workspaces
			if(isset($form->perm_projects)){ $model->perm_projects = $form->perm_projects; } //Optional
			if(isset($form->perm_tasks)){ $model->perm_tasks = $form->perm_tasks; } //Optional
			if(isset($form->perm_notes)){ $model->perm_notes = $form->perm_notes; } //Optional
			if(isset($form->perm_files)){ $model->perm_files = $form->perm_files; } //Optional
			//We don't allow editing chats
			//We don't allow editing comments
			if(isset($form->perm_namecards)){ $model->perm_namecards = $form->perm_namecards; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 17, 6)); //Role updated.
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

		$failmsg = $app->trans->getBRUT('api', 17, 7)."\n"; //Role deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Roles::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 15); //We could not validate the role ID.
			$errfield = 'id';
		} else if($model = Roles::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 17, 8)); //Role deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Roles::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 17, 9); //Role already deleted.
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

		$failmsg = $app->trans->getBRUT('api', 17, 20)."\n"; //Role restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Roles::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 15); //We could not validate the role ID.
			$errfield = 'id';
		} else if($model = Roles::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 17, 21)); //Role restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
			}
		} else if($model = Roles::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 17, 22); //Role already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

}
