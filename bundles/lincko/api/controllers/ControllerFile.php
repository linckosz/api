<?php
// Category 3

namespace bundles\lincko\api\controllers;

use \libs\Json;
use \libs\Controller;
use \libs\Datassl;
use \libs\SimpleImage;
use \libs\File;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Authorization;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\libs\Data;
use WideImage\WideImage;

use phpthumb;

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
		// cannot use $this->setFields() here because of file reading
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
		$form = new \stdClass;

		$this->data = json_decode($app->request->getBody());
		if(!isset($this->data->data)){
			$this->data = $app->request->post();
			if(empty($this->data)){
				$app->render(400, array('show' => true, 'msg' => array('msg' => $app->trans->getBRUT('api', 0, 4)), 'error' => true,)); //No data form received.
				return true;
			} else {
				$form = (object) $this->data;
			}
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
		if(isset($form->name) && is_string($form->name)){
			$form->name = trim(STR::break_line_conv($form->name,' '));
			if(strlen($form->name)==0){
				$form->name = $app->trans->getBRUT('api', 14, 0); //New File
			}
		}
		if(isset($form->version_of) && is_numeric($form->version_of)){
			$form->version_of = (int) $form->version_of;
			if(!Files::getModel($form->version_of)){
				$form->version_of = 0;
			}
		}

		if(isset($form->parent_type) && isset($form->parent_id)){
			$attached = false;
			//Hard attach
			if(in_array($form->parent_type, Files::getParentListHard())){
				if($class = Files::getClass($form->parent_type)){
					if($parent = $class::getModel($form->parent_id)){
						$attached = true;
					}
				}
			}
			//Soft attach
			else if(in_array($form->parent_type, Files::getParentList())){
				if($class = Files::getClass($form->parent_type)){
					$parent = $class::getModel($form->parent_id);
					if($parent && method_exists($class, 'projects')){
						if($project = $parent->projects()->first()){
							$form->parent_type = 'projects';
							$form->parent_id = $project->id;
							$attached = true;
							if(!isset($form->pivot->{$form->parent_type.'>access'})){
								$form->pivot->{$form->parent_type.'>access'} = new \stdClass;
							}
							$form->pivot->{$form->parent_type.'>access'}->{$form->parent_id} = true;
						}
					}
				}
			}
			//By default store into MyPlaceholder
			if(!$attached){
				$form->parent_type = null;
				$form->parent_id = 0;
				 if($personal_private = Projects::getPersonal()){
					$form->parent_type = 'projects';
					$form->parent_id = $personal_private->id;
				}
			}
		}
		
		return $this->form = $form;
	}

	public function create_post(){
		$app = $this->app;
		$this->setFields();
		$form = $this->form;
		$failmsg = $app->trans->getBRUT('api', 14, 1)."\n"; //NFile upload failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		$success = false;

		if(!isset($form->parent_type) || !Files::validType($form->parent_type)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 7); //We could not validate the parent type.
			$errfield = 'parent_type';
		}
		else if(!isset($form->parent_id) || !Files::validNumeric($form->parent_id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->name) && !Files::validChar($form->name, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->comment) && !Files::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if(isset($_FILES)){
			foreach ($_FILES as $file => $fileArray) {
				if(is_array($fileArray['tmp_name'])){
					foreach ($fileArray['tmp_name'] as $j => $value) {
						if($model = new Files()){
							$model->name = $fileArray['name'][$j];
							if(isset($form->name)){ $model->name = $form->name; } //Optional
							$model->ori_type = mb_strtolower($fileArray['type'][$j]);
							$model->tmp_name = $fileArray['tmp_name'][$j];
							$model->error = $fileArray['error'][$j];
							$model->size = $fileArray['size'][$j];
							$model->parent_type = $form->parent_type;
							$model->parent_id = $form->parent_id;
							if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
							if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
							if(isset($form->version_of)){ $model->version_of = $form->version_of; } //Optional
							$model->pivots_format($form, false);
							if($model->getParentAccess() && $model->save()){
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
						if(isset($form->name)){ $model->name = $form->name; } //Optional
						$model->ori_type = mb_strtolower($fileArray['type']);
						$model->tmp_name = $fileArray['tmp_name'];
						$model->error = $fileArray['error'];
						$model->size = $fileArray['size'];
						$model->parent_type = $form->parent_type;
						$model->parent_id = $form->parent_id;
						if(isset($form->temp_id)){ $model->temp_id = $form->temp_id; } //Optional
						if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
						if(isset($form->version_of)){ $model->version_of = $form->version_of; } //Optional
						$model->pivots_format($form, false);
						if($model->getParentAccess() && $model->save()){
							$model->setForceSchema();
							$success = true;
						} else {
							$success = false;
						}
					}
				}
			}
		} else {
			$errmsg = $failmsg.$app->trans->getBRUT('api', 3, 2); //No file selected to upload.
		}

		if($success){
			$msg = array('msg' => $app->trans->getBRUT('api', 14, 2)); //File uploaded.
			$data = new Data();
			$data->dataUpdateConfirmation($msg, 201);
			return true;
		} else {
			if(isset($_FILES)){
				\libs\Watch::php(array_merge((array) $form, $_FILES), 'Upload failed',__FILE__,true);
			} else {
				\libs\Watch::php($form, 'Upload failed (no file)',__FILE__,true);
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function read_post(){
		$app = $this->app;
		$this->setFields();
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 14, 3)."\n"; //File access failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}
		else if($model = Files::find($form->id)){
			if($model->checkAccess(false)){
				$uid = $app->lincko->data['uid'];
				$key = $model->getTable();
				$msg = $app->trans->getBRUT('api', 14, 4); //File accessed.
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
		$this->setFields();
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 14, 5)."\n"; //File update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}
		else if(isset($form->parent_id) && !Files::validNumeric($form->parent_id, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 6); //We could not validate the parent ID.
			$errfield = 'parent_id';
		}
		else if(isset($form->parent_type) && !Files::validType($form->parent_type, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 7); //We could not validate the parent type.
			$errfield = 'parent_type';
		}
		else if(isset($form->name) && !Files::validChar($form->name, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 18); //We could not validate the name format: - 104 characters max
			$errfield = 'name';
		}
		else if(isset($form->comment) && !Files::validText($form->comment, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 3); //We could not validate the comment format: - Cannot be empty
			$errfield = 'comment';
		}
		else if($model = Files::find($form->id)){
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->parent_type)){ $model->parent_type = $form->parent_type; } //Optional
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$msg = array('msg' => $app->trans->getBRUT('api', 14, 6)); //File updated.
					$data = new Data();
					$data->dataUpdateConfirmation($msg, 200);
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
		$this->setFields();
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 14, 7)."\n"; //File deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}

		if($model = Files::find($form->id)){
			if($model->delete()){
				$msg = $app->trans->getBRUT('api', 14, 8); //File deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg, 'schema' => $schema)));
			}
		} else if($model = Files::withTrashed()->find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 14, 9); //File already deleted.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function restore_post(){
		$app = $this->app;
		$this->setFields();
		$form = $this->form;

		$failmsg = $app->trans->getBRUT('api', 14, 20)."\n"; //File restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}

		if($model = Files::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = $app->trans->getBRUT('api', 14, 21); //File restored.
				$data = new Data();
				$schema = $data->getSchema();
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg, 'schema' => $schema)));
			}
		} else if($model = Files::find($form->id)){
			$model->enableTrash(true);
			$access = $model->checkAccess();
			$model->enableTrash(false);
			if($access){
				$msg = $app->trans->getBRUT('api', 14, 22); //File already present.
				$app->render(200, array('show' => true, 'msg' => array('msg' => $msg)));
			}
		}

		$app->render(401, array('show' => true, 'msg' => array('msg' => $errmsg, 'field' => $errfield), 'error' => true));
		return false;
	}

	public function file_open_get($workspace, $uid, $type, $id){
		$app = $this->app;
		ob_clean();
		flush();
		$width = 200;
		$height = 200;
		$scale = false;
		$file = Files::find($id);
		if($file && $file->checkAccess(false)){
			$content_type = 'application/force-download';
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
					$content_type = $file->thu_type;
				} else {
					$path = $file->server_path.'/'.$file->created_by.'/'.$file->link;
					$content_type = $file->ori_type;
				}
				if(filesize($path)>0){
					//http://stackoverflow.com/questions/2000715/answering-http-if-modified-since-and-http-if-none-match-in-php/2015665#2015665
					$timestamp = filemtime($path); 
					$gmt_mtime = gmdate('r', $timestamp);
					header('Last-Modified: '.$gmt_mtime);
					header('Expires: '.gmdate(DATE_RFC1123,time()+86400));
					header('ETag: "'.md5($timestamp.$path).'"');
					if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
						if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$path)) {
							header('HTTP/1.1 304 Not Modified');
							return exit(0);
						}
					}
					header('Content-Type: '.$content_type.';');
					readfile($path);
					return exit(0);
				}
			}
		} else if(!is_null($file)){
			if($file->width > $file->height){
				$height = floor(200 * $file->width / $file->height);
				$scale = true;
			} else if($file->height > $file->width){
				$width = floor(200 * $file->height / $file->width);
				$scale = true;
			}
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			\libs\Watch::php("Files :\n".json_encode($file->toArray()), $msg, __FILE__, true);
		} else {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			\libs\Watch::php("No file", $msg, __FILE__, true);
		}
		
		$path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/unavailable.png';
		$timestamp = filemtime($path); 
		$gmt_mtime = gmdate('r', $timestamp);
		header('Last-Modified: '.$gmt_mtime);
		header('Expires: '.gmdate(DATE_RFC1123,time()+86400));
		header('ETag: "'.md5($timestamp.$path).'"');
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$path)) {
				header('HTTP/1.1 304 Not Modified');
				return exit(0);
			}
		}
		$src = WideImage::load($path);
		$white = $src->allocateColor(255, 255, 255);
		$src = $src->resizeCanvas($width, $height, 'center', 'center', $white);
		$src->output('png');
		
		return exit(0);
	}

}
