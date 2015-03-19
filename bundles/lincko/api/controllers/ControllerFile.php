<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Folders;
use \libs\Datassl;
use \bundles\lincko\api\models\Users;
use \bundles\lincko\api\models\Authorization;

class ControllerFile extends Controller {

	protected $app = NULL;
	protected $user = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		return true;
	}

	protected function displayHTML($msg=''){
		$app = $this->app;
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		ob_clean();
		echo '
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<script>
document.domain = "'.$app->lincko->domain.'";

function api_file_upload_action(Obj){
	if("wrapper_upload_action" in window.top){
		window.top.wrapper_upload_action(Obj);
	}
}

</script>
</head>
<body>
		'.$msg.'
</body>
</html>
		';
		return exit(0);
	}

	public function _get(){
		$app = $this->app;
		$msg = '
<form id="api_file_form" action="https://file.'.$app->lincko->domain.':8443/file" method="post" target="api_file_upload_iframe" enctype="multipart/form-data" onsubmit="return true;">
	<label for="api_file_form_video">
		<span id="api_file_upload_video">video</span>
		<input type="file" accept="video/*" capture="camcorder" id="api_file_form_video" name="file_video" multiple />
	</label>
	<label for="api_file_form_photo">
		<span id="api_file_upload_photo">photo</span>
		<input type="file" accept="image/*" capture="camera" id="api_file_form_photo" name="file_photo" multiple />
	</label>
	<label for="api_file_form_files">
		<span id="api_file_upload_files">files</span>
		<input type="file" id="api_file_form_files" name="file_files" multiple />
	</label>
	<input type="hidden" value="" id="api_file_shangzai_puk" name="shangzai_puk" />
	<input type="hidden" value="" id="api_file_shangzai_cs" name="shangzai_cs" />
	<input type="hidden" value="" id="api_file_project_id" name="project_id" />
</form>
<!-- this iframe must be duplicated only because Firefox looks for target in the same iframe, not the main parent document like all others browsers -->
<iframe id="api_file_upload_iframe" name="api_file_upload_iframe" frameborder="0" height="0" width="0" scrolling="no" src=""></iframe>
		';
		return $this->displayHTML($msg);
	}

	public function _post(){
		$app = $this->app;
		$post = $app->request->post();
		$authorized = false;
		$msg = '';
		$json = array(
			'msg' => $app->trans->getBRUT('api', 3, 1), //An error occurred while uploading the file(s). Please retry.
			'error' => true,
			'resign' => false,
		);

		if(isset($post['shangzai_puk']) && isset($post['shangzai_cs'])){
			$shangzai_puk = $this->uncryptData($post['shangzai_puk']);
			$shangzai_cs = $this->uncryptData($post['shangzai_cs']);
			\libs\Watch::php($shangzai_puk,'$shangzai_puk',__FILE__);
			if($authorization = Authorization::find($shangzai_puk)){
				$checksum = md5($authorization->private_key.$shangzai_puk);
				if($user = Users::find($authorization->user_id) && $checksum === $shangzai_cs){
					$authorized = true;
				}
			}
		}

		if($authorized){
			$folder = new Folders;
			$folder->createPath($app->lincko->filePath.'/temp/');
			if(isset($_FILES)){
				foreach ($_FILES as $file => $fileArray) {
					if(is_array($fileArray['tmp_name'])){
						foreach ($fileArray['tmp_name'] as $j => $value) {
							$array_tmp_name = $fileArray['tmp_name'][$j];
							$array_name = $fileArray['name'][$j];
							if($fileArray['size'][$j]>0){
								copy($array_tmp_name, $folder->getPath().$array_name);
								$json['msg'] = $app->trans->getBRUT('api', 3, 3); //Upload Successful
								$json['error'] = false;
							}
						}
					} else {
						$array_tmp_name = $fileArray['tmp_name'];
						$array_name = $fileArray['name'];
						if($fileArray['size']>0){
							copy($array_tmp_name, $folder->getPath().$array_name);
							$json['msg'] = $app->trans->getBRUT('api', 3, 3); //Upload Successful
							$json['error'] = false;
						}
					}
				}
			} else {
				$json['msg'] = $app->trans->getBRUT('api', 3, 2); //No file selected to upload.
			}
		} else {
			$json['resign'] = true;
		}
		
		$msg = '<script>api_file_upload_action('.json_encode($json).');</script>';

		return $this->displayHTML($msg);
	}

	//This function only works if the client and the server have the same secret_key
	protected function uncryptData($value){
		$app = $this->app;
		return \Slim\Http\Util::decodeSecureCookie(
			$value,
			$app->config('cookies.secret_key'),
			$app->config('cookies.cipher'),
			$app->config('cookies.cipher_mode')
		);
	}

}
