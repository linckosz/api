<?php
// Category 3

namespace bundles\lincko\api\controllers;

use \libs\Json;
use \libs\Controller;
use \libs\Datassl;
use \libs\SimpleImage;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Files;

class ControllerFile extends Controller {

	protected $app = NULL;
	protected $data = NULL;
	protected $form = NULL;
	protected $user = NULL;
	protected $msg = '';
	protected $error = true;
	protected $status = 412;
	protected $files = array();

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->msg = $app->trans->getBRUT('api', 3, 6); //Server access issue. Please retry.
		$this->form = new \stdClass;
		return true;
	}

	public function result(){
		$app = $this->app;
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		ob_clean();
		echo '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title>jQuery Iframe Transport Plugin Redirect Page</title>
</head>
<body>
<script>
document.body.innerText=document.body.textContent=decodeURIComponent(window.location.search.slice(1));
</script>
</body>
</html>
		';
		return exit(0);
	}

	protected function setFields(){
		$app = $this->app;
		$this->data = $app->request->post();

		$form = new \stdClass;
		/*
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
		*/
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$this->setFields();
		$data = $this->data;
		$this->msg = $app->trans->getBRUT('api', 3, 5); //Upload failed. Please retry.

		$parent_type = null;
		$parent_id = null;
		$temp_id = null;
		$attached = false;
		$pivot = false;
		if(isset($data['parent_type']) && isset($data['parent_id'])){
			//Hard attach
			if(in_array($data['parent_type'], Files::getParentListHard())){
				if($class = Files::getClass($data['parent_type'])){
					if($parent = $class::getModel($data['parent_id'])){
						$parent_type = $data['parent_type'];
						$parent_id = $data['parent_id'];
						$attached = true;
					}
				}
			}
			//Soft attach
			else if(in_array($data['parent_type'], Files::getParentList())){
				if($class = Files::getClass($data['parent_type'])){
					$parent = $class::getModel($data['parent_id']);
					if($parent && method_exists($class, 'projects')){
						if($project = $parent->projects()->first()){
							$parent_type = 'projects';
							$parent_id = $project->id;
							$attached = true;
							$pivot = new \stdClass;
							$pivot->{$data['parent_type'].'>access'} = new \stdClass;
							$pivot->{$data['parent_type'].'>access'}->$data['parent_id'] = true;
						}
					}
				}
			}
		}

		//By default store into MyPlaceholder
		if(!$attached && $personal_private = Projects::getPersonal()){
			$parent_type = 'projects';
			$parent_id = $personal_private->id;
		}

		$success = false;
		if(isset($_FILES)){
			foreach ($_FILES as $file => $fileArray) {
				if(is_array($fileArray['tmp_name'])){
					foreach ($fileArray['tmp_name'] as $j => $value) {
						if($model = new Files()){
							$model->name = $fileArray['name'][$j];
							$model->ori_type = mb_strtolower($fileArray['type'][$j]);
							$model->tmp_name = $fileArray['tmp_name'][$j];
							$model->error = $fileArray['error'][$j];
							$model->size = $fileArray['size'][$j];
							$model->parent_type = $parent_type;
							$model->parent_id = $parent_id;
							$model->temp_id = $data['temp_id'];
							if(is_object($pivot)){
								$model->pivots_format($pivot, false);
							}
							if($model->save()){
								$model->setForceSchema();
								$success = true;
							} else {
								$success = false;
								break;
							}
						}
					}
				} else {
					if($model = new Files()){
						$model->name = $fileArray['name'];
						$model->ori_type = mb_strtolower($fileArray['type']);
						$model->tmp_name = $fileArray['tmp_name'];
						$model->error = $fileArray['error'];
						$model->size = $fileArray['size'];
						$model->parent_type = $parent_type;
						$model->parent_id = $parent_id;
						$model->temp_id = $data['temp_id'];
						if(is_object($pivot)){
							$model->pivots_format($pivot, false);
						}
						if($model->save()){
							$model->setForceSchema();
							$success = true;
						} else {
							$success = false;
						}
					}
				}
			}
		} else {
			$this->msg = $app->trans->getBRUT('api', 3, 2); //No file selected to upload.
		}

		if($success){
			$this->msg = $app->trans->getBRUT('api', 3, 3); //Upload Successful
			$this->error = false;
			$this->status = 200;
		} else {
			if(isset($_FILES)){
				\libs\Watch::php(array_merge($data, $_FILES), 'Upload failed',__FILE__,true);
			} else {
				\libs\Watch::php($data, 'Upload failed (no file)',__FILE__,true);
			}
		}

		$json = new Json($this->msg, $this->error, $this->status, false, false, $this->files);
		$json->render();

		return false;

	}

	public function read_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 14, 3)."\n".$app->trans->getBRUT('api', 0, 0); //File access failed. You are not allowed to access the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function update_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 14, 5)."\n".$app->trans->getBRUT('api', 0, 5); //File update failed. You are not allowed to edit the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function delete_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 14, 7)."\n".$app->trans->getBRUT('api', 0, 6); //File deletion failed. You are not allowed to delete the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$errmsg = $app->trans->getBRUT('api', 14, 20)."\n".$app->trans->getBRUT('api', 0, 9); //File restoration failed. You are not allowed to restore the server data.
		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg), 'error' => true));
		return false;
	}

	public function file_open_get($workspace, $uid, $type, $id){
		$app = $this->app;
		ob_clean();
		flush();
		$file = Files::find($id);
		if($type=='thumbnail' && is_null($file->thu_type)){ //If the thumbnail is not available we download
			$type = 'download';
		}
		if($type=='link' && $file->category!='image'){ //If the file is different than an image we download by default
			$type = 'download';
		}
		if($type=='download'){
			$path = $file->server_path.'/'.$file->created_by.'/'.$file->link;
			if(filesize($path)>0){
				header('Content-Description: File Transfer');
				header('Content-Type: application/force-download;');
				header('Content-Disposition: attachment; filename="'.$file->name.'"');
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: ' . filesize($path));
				readfile($path);
			}
		} else if($type=='link' || $type=='thumbnail'){
			if($type=='thumbnail'){
				$path = $file->server_path.'/'.$file->created_by.'/thumbnail/'.$file->link;
			} else {
				$path = $file->server_path.'/'.$file->created_by.'/'.$file->link;
			}
			if(filesize($path)>0){
				$src = new SimpleImage($path);
				$src->output();
				unset($src);
			}
		}
		return exit(0);
	}

}
