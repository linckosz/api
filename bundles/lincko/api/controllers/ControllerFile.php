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
	protected $json = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->json = (object) array('files' => null);
		$this->json->files = array();
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

function app_upload_action(Obj){
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

	public function _options(){
		return $this->displayHTML();
	}

	public function _get(){
		return $this->displayHTML('ok');
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
			if(isset($_FILES)){
				\libs\Watch::php(array_merge($post, $_FILES),'Upload failed',__FILE__,true);
			} else {
				\libs\Watch::php($post,'Upload failed (no file)',__FILE__,true);
			}
			$json['resign'] = true;
		}
		
		$msg = '<script>app_upload_action('.json_encode($json).');</script>';

		ob_clean();
		$this->json_push();
		echo json_encode($this->json);
		return exit(0);

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

	protected function json_push(){
		$obj = (object) array(
			'name' => null,
			'size' => null,
			'url' => null,
			'thumbnailUrl' => null,
			'deleteUrl' => null,
			'deleteType' => 'DELETE',
			'error' => null,
		);
		$obj->name = "picture1.jpg";
		$obj->size = 902604;
		//$obj->error = "Filetype not allowed";
		array_push($this->json->files, $obj); 
	}

}
