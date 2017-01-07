<?php
// Category 3

namespace bundles\lincko\api\controllers;

use \libs\Json;
use \libs\Controller;
use \libs\Datassl;
use \libs\SimpleImage;
use \libs\File;
use \libs\STR;
use \libs\Folders;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\libs\Data;
use WideImage\WideImage;
use Endroid\QrCode\QrCode;

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
				if(isset($form->data) && is_string($form->data)){
					$form = json_decode($form->data);
				}
			}
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
		if(isset($form->fav) && is_numeric($form->fav)){
			$form->fav = (int) $form->fav;
		}
		if(isset($form->parent_type) && is_string($form->parent_type)){
			$form->parent_type = strtolower(trim($form->parent_type));
		}
		if(isset($form->parent_id) && is_numeric($form->parent_id)){
			$form->parent_id = (int) $form->parent_id;
		}
		if(isset($form->name) && is_string($form->name)){
			$form->name = trim(STR::break_line_conv(STR::br2space($form->name),' '));
			if(strlen($form->name)==0){
				$form->name = $app->trans->getBRUT('api', 14, 0); //New File
			} else if(strlen($form->name)>104){
				$form->name = substr($form->name, 0, 101).'...';
			}
		}
		if(isset($form->version_of) && is_numeric($form->version_of)){
			$form->version_of = (int) $form->version_of;
			if(!Files::getModel($form->version_of)){
				$form->version_of = 0;
			}
		}

		//Work with soft and hard linkages
		$parent_list = Files::getParentList();
		$parent_list_soft = Files::getParentListSoft();
		if(!is_null($parent_list_soft) && isset($form->parent_type) && isset($form->parent_id)){
			$attached = false;
			//Hard attach
			if(
				   ( is_string($parent_list) && $form->parent_type==$parent_list )
				|| ( is_array($parent_list) && in_array($form->parent_type, $parent_list))
			){
				if($class = Files::getClass($form->parent_type)){
					if($parent = $class::getModel($form->parent_id)){
						$attached = true;
					}
				}
			}
			//Soft attach
			else if(
				   ( is_string($parent_list_soft) && $form->parent_type==$parent_list_soft )
				|| ( is_array($parent_list_soft) && in_array($form->parent_type, $parent_list_soft))
			){
				$loop = 1000;
				$parent_type = $form->parent_type;
				$parent_id = $form->parent_id;
				while($loop && !$attached){
					$loop--;
					if($class = Files::getClass($parent_type)){
						if($parent = $class::getModel($parent_id)){
							$parent->setParentAttributes();
							if(in_array($parent->parent_type, $parent_list) && method_exists($class, $parent->parent_type)){
								if($model = $parent->{$parent->parent_type}()->first()){
									$attached = true;
									$loop = false;
									if(!isset($form->{$form->parent_type.'>access'})){
										$form->{$form->parent_type.'>access'} = new \stdClass;
									}
									$form->{$form->parent_type.'>access'}->{$form->parent_id} = true;
									$form->parent_type = $parent->parent_type;
									$form->parent_id = $model->id;
								}
							}
							$parent_type = $parent->parent_type;
							$parent_id = $parent->parent_id;
						} else {
							$loop = false;
						}
					} else {
						$loop = false;
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

	public function create_options(){
		$app = $this->app;
		$app->render(200, array('msg' => 'OK'));
		return true;
	}

	public function upload_post(){
		return $this->create_post();
	}

	public function create_post(){
		$app = $this->app;
		$this->setFields();
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 14, 1)."\n"; //File upload failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		$success = false;

		if(isset($form->fav) && !Tasks::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
		}
		else if(!isset($form->parent_type) || !Files::validType($form->parent_type)){ //Required
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
							if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
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
								$success = true;
							} else {
								$success = false;
								break;
							}
						}
					}
				} else {
					if($model = new Files()){
						if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
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
			$data->dataUpdateConfirmation($msg, 201, false, $lastvisit, false);
			return true;
		} else {
			if(isset($_FILES)){
				\libs\Watch::php(array_merge((array) $form, $_FILES), 'Upload failed', __FILE__, __LINE__, true);
			} else {
				\libs\Watch::php($form, 'Upload failed (no file)', __FILE__, __LINE__, true);
			}
		}

		//We have to use Json object to be able to get back the message attached
		$json = new Json($errmsg, true, 401);
		$json->render(401);

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
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 14, 5)."\n"; //File update failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 5); //You are not allowed to edit the server data.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}
		else if(isset($form->fav) && !Tasks::validNumeric($form->fav, true)){ //Optional
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 25); //We could not validate the format: - Integer
			$errfield = 'fav';
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
			if(isset($form->fav)){ $model->fav = $form->fav; } //Optional
			if(isset($form->parent_id)){ $model->parent_id = $form->parent_id; } //Optional
			if(isset($form->parent_type)){ $model->parent_type = $form->parent_type; } //Optional
			if(isset($form->name)){ $model->name = $form->name; } //Optional
			if(isset($form->comment)){ $model->comment = $form->comment; } //Optional
			$dirty = $model->getDirty();
			$pivots = $model->pivots_format($form);
			if(count($dirty)>0 || $pivots){
				if($model->getParentAccess() && $model->save()){
					$model->enableTrash(false);
					$msg = array('msg' => $app->trans->getBRUT('api', 14, 6)); //File updated.
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
		$this->setFields();
		$form = $this->form;
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 14, 7)."\n"; //File deletion failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}

		if($model = Files::find($form->id)){
			if($model->delete()){
				$msg = array('msg' => $app->trans->getBRUT('api', 14, 8)); //File deleted.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
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
		$lastvisit = time();

		$failmsg = $app->trans->getBRUT('api', 14, 20)."\n"; //File restoration failed.
		$errmsg = $failmsg.$app->trans->getBRUT('api', 0, 7); //Please try again.
		$errfield = 'undefined';

		if(!isset($form->id) || !Files::validNumeric($form->id)){ //Required
			$errmsg = $failmsg.$app->trans->getBRUT('api', 8, 30); //We could not validate the file ID.
			$errfield = 'id';
		}

		if($model = Files::onlyTrashed()->find($form->id)){
			if($model->restore()){
				$msg = array('msg' => $app->trans->getBRUT('api', 14, 21)); //File restored.
				$data = new Data();
				$schema = $data->getSchema();
				$data->dataUpdateConfirmation($msg, 200, false, $lastvisit, true, $schema);
				return true;
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

	//This is used for third party software, it's more secured
	public function open_post($sha, $type, $id){
		$app = $this->app;
		$workspace = $app->lincko->data['workspace_id'];
		return $this->open_get($workspace, $sha, $type, $id);
	}

	public function qrcode_get(){
		$app = $this->app;
		ob_clean();
		flush();
		$uid = $app->lincko->data['uid'];
		$user = Users::find($uid);
		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_HOST'].'/uid/'.Datassl::encrypt($user->id, 'invitation');
		$timestamp = $user->created_at->timestamp; 
		$gmt_mtime = gmdate('r', $timestamp);
		header('Last-Modified: '.$gmt_mtime);
		header('Expires: '.gmdate(DATE_RFC1123, time()+16000000)); //About 6 months cached
		header('ETag: "'.md5($uid.'-'.$timestamp).'"');
		if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) || isset($_SERVER['HTTP_IF_NONE_MATCH'])) {
			if ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($uid.'-'.$timestamp)) {
				header('HTTP/1.1 304 Not Modified');
				return exit(0);
			}
		}

		//https://packagist.org/packages/endroid/qrcode
		$qrCode = new QrCode();

		$server_path_full = $app->lincko->filePathPrefix.$app->lincko->filePath;
		$folder = new Folders;
		$folder->createPath($server_path_full.'/'.$app->lincko->data['uid'].'/qrcode/');

		$exists = false;
		$basename = $_SERVER['REQUEST_SCHEME'].'-'.$_SERVER['SERVER_HOST'].'-';

		if($user->profile_pic>0 && $file = Files::withTrashed()->find($user->profile_pic)){
			$path = $folder->getPath().$basename.$user->profile_pic.'.png';
			$puid = $file->created_by;
			if(!is_null($file->puid)){
				$puid = $file->puid;
			}
			$thumbnail = $server_path_full.'/'.$puid.'/thumbnail/'.$file->link;
			if(is_file($path)){
				$exists = true;
			} else if(is_file($thumbnail)){
				//Generate the qrcode picture
				$mini = $folder->getPath().$basename.$user->profile_pic.'_mini.png';
				$src = WideImage::load($thumbnail);
				$src = $src->resize(36, 36, 'outside', 'any');
				$src = $src->crop("center", "middle", 36, 36);
				$src = $src->roundCorners(6, null, 2);
				$src = $src->saveToFile($mini, 9, PNG_NO_FILTER);
				$qrCode
					->setLogo($mini)
					->setLogoSize(36)
				;
			}
		} else {
			$path = $folder->getPath().$basename.'qrcode.png';
			if(is_file($path)){
				$exists = true;
			}
		}
		
		if(!$exists){
			$qrCode
				->setText($url)
				->setSize(160)
				->setPadding(5)
				->setErrorCorrection('high')
				//->setForegroundColor(array('r' => 251, 'g' => 160, 'b' => 38, 'a' => 0)) //Orange is not working very well
				->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
				->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
				->setImageType(QrCode::IMAGE_TYPE_PNG)
			;
			header('Content-Type: '.$qrCode->getContentType());
			$qrCode->save($path);
			$qrCode->render();
		} else {
			WideImage::load($path)->output('png');;
		}
		
		return exit(0);
	}

	public function profile_get($workspace, $uid){
		$app = $this->app;
		ob_clean();
		flush();
		$width = 200;
		$height = 200;
		$scale = false;
		$access = false;
		$user = Users::withTrashed()->find($uid);

		if($user && $id = $user->profile_pic){
			$file = Files::withTrashed()->find($id);
			Workspaces::getSFTP();			
			if($file && $file->category=='image'){
				$puid = $file->created_by;
				if(!is_null($file->puid)){
					$puid = $file->puid;
				}
				$path = $app->lincko->filePathPrefix.$file->server_path.'/'.$puid.'/thumbnail/'.$file->link;
				$content_type = $file->thu_type;
				if(filesize($path)!==false){
					//http://stackoverflow.com/questions/2000715/answering-http-if-modified-since-and-http-if-none-match-in-php/2015665#2015665
					$timestamp = filemtime($path); 
					$gmt_mtime = gmdate('r', $timestamp);
					header('Last-Modified: '.$gmt_mtime);
					header('Expires: '.gmdate(DATE_RFC1123,time()+16000000)); //About 6 months cached
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
		}
		
		$path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/user.png';
		$timestamp = filemtime($path); 
		$gmt_mtime = gmdate('r', $timestamp);
		header('Last-Modified: '.$gmt_mtime);
		header('Expires: '.gmdate(DATE_RFC1123,time()+3000000)); //About 1 month
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

	public function open_get($workspace, $sha, $type, $id){
		$app = $this->app;
		ob_clean();
		flush();
		$width = 200;
		$height = 200;
		$scale = false;
		$access = false;
		$file = Files::withTrashed()->find($id);
		if($app->lincko->data['uid'] && $file && $file->link==Datassl::decrypt_smp(base64_decode($sha)) ){ //We allow businesses to be able to see deleted files for restoring purpose
			$access = $file->checkAccess(false);
			if(!$access){
				//Allow profile pictures
				if(Users::Where('profile_pic', $file->id)){
					$access = true;
				}
			}
		}
		
		if($access){
			Workspaces::getSFTP();
			$content_type = 'application/force-download';
			if($file->progress<100 && $file->category=='video'){
				$file->checkProgress();
			}
			if($type=='thumbnail' && is_null($file->thu_type)){ //If the thumbnail is not available we download
				$type = 'download';
			}
			if($type=='link' && $file->category!='image'){ //If the file is different than an image we download by default
				$type = 'download';
			}
			$puid = $file->created_by;
			if(!is_null($file->puid)){
				$puid = $file->puid;
			}
			if($type=='download'){
				$path = $app->lincko->filePathPrefix.$file->server_path.'/'.$puid.'/'.$file->link;
				$name = $file->name;
				if($file->progress<100 && $file->category=='video'){
					$path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/mp4.png';
					$name = 'converting.png';
				}
				if(filesize($path)!==false){
					header('Content-Description: File Transfer');
					header('Content-Type: attachment/force-download;');
					//header('Content-Type: application/octet-stream'); //toto => test for ios
					header('Content-Disposition: attachment; filename="'.$name.'"');
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Content-Length: ' . filesize($path));
					readfile($path);
					return exit(0);
				}
			} else if($type=='link' || $type=='thumbnail'){
				if($type=='thumbnail'){
					$path = $app->lincko->filePathPrefix.$file->server_path.'/'.$puid.'/thumbnail/'.$file->link;
					$content_type = $file->thu_type;
				} else {
					$path = $app->lincko->filePathPrefix.$file->server_path.'/'.$puid.'/'.$file->link;
					$content_type = $file->ori_type;
				}
				if(filesize($path)!==false){
					//http://stackoverflow.com/questions/2000715/answering-http-if-modified-since-and-http-if-none-match-in-php/2015665#2015665
					$timestamp = filemtime($path); 
					$gmt_mtime = gmdate('r', $timestamp);
					header('Last-Modified: '.$gmt_mtime);
					header('Expires: '.gmdate(DATE_RFC1123,time()+16000000)); //About 6 months cached
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
			\libs\Watch::php("Files :\n".json_encode($file->toArray(), JSON_UNESCAPED_UNICODE), $msg, __FILE__, __LINE__, true);
		} else {
			$msg = $app->trans->getBRUT('api', 0, 0); //You are not allowed to access the server data.
			\libs\Watch::php("No file\nworkspace: $workspace\nsha: $sha\ntype: $type\nid: $id", $msg, __FILE__, __LINE__, true);
		}
		
		$path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/unavailable.png';
		$timestamp = filemtime($path); 
		$gmt_mtime = gmdate('r', $timestamp);
		header('Last-Modified: '.$gmt_mtime);
		header('Expires: '.gmdate(DATE_RFC1123,time()+3000000)); //About 1 month
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

	//This is a process to run in background, so we are not expecting an output
	public function progress_post($id=false){
		$app = $this->app;
		if(!$id){
			$this->setFields();
			$form = $this->form;
			if(isset($form->id) ){
				$id = $form->id;
			}
		}
		if($id && $file = Files::withTrashed()->find($id)){
			$lastvisit = time();
			$file->setProgress();
		}
		return true;
	}

}
