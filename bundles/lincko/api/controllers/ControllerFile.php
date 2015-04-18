<?php
// Category 3

namespace bundles\lincko\api\controllers;

use \libs\Json;
use \libs\Controller;
use \libs\Folders;
use \libs\Datassl;
use \bundles\lincko\api\models\Users;
use \bundles\lincko\api\models\Authorization;

class ControllerFile extends Controller {

	protected $app = NULL;
	protected $user = NULL;
	protected $msg = '';
	protected $error = true;
	protected $resignin = true;
	protected $status = 401;
	protected $files = array();

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->msg = $app->trans->getBRUT('api', 3, 6); //Server access issue. Please retry.
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

		if(isset($post['shangzai_puk']) && isset($post['shangzai_cs'])){
			$shangzai_puk = $this->uncryptData($post['shangzai_puk']);
			$shangzai_cs = $this->uncryptData($post['shangzai_cs']);
			if($authorization = Authorization::find($shangzai_puk)){
				$checksum = md5($authorization->private_key.$shangzai_puk);
				if($user = Users::find($authorization->user_id) && $checksum === $shangzai_cs){
					$this->resignin = false;
					$this->status = 412;
				}
			}
		}

		if(!$this->resignin){
			if(isset($_FILES)){
				foreach ($_FILES as $file => $fileArray) {
					if(is_array($fileArray['tmp_name'])){
						foreach ($fileArray['tmp_name'] as $j => $value) {
							$file_tmp = array(
								'name' => $fileArray['name'][$j],
								'type' => $fileArray['type'][$j],
								'tmp_name' => $fileArray['tmp_name'][$j],
								'error' => $fileArray['error'][$j],
								'size' => $fileArray['size'][$j],
							);
							$this->handleFile($file_tmp);
						}
					} else {
						$this->handleFile($fileArray);
					}
				}
			} else {
				$this->msg = $app->trans->getBRUT('api', 3, 2); //No file selected to upload.
			}
		} else {
			if(isset($_FILES)){
				\libs\Watch::php(array_merge($post, $_FILES),'Upload failed',__FILE__,true);
			} else {
				\libs\Watch::php($post,'Upload failed (no file)',__FILE__,true);
			}
		}

		$json = new Json($this->msg, $this->error, $this->status, false, $this->resignin, $this->files);
		$json->render();

		return false;

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

	protected function handleFile($file){
		$app = $this->app;
		$this->msg = $app->trans->getBRUT('api', 3, 5); //Upload failed. Please retry.
		$folder = new Folders;
		$folder->createPath($app->lincko->filePath.'/temp/');
		$obj = (object) array(
			'name' => $file['name'],
			'size' => $file['size'],
			'error' => null,
		);
		$array_tmp_name = $file['tmp_name'];
		$array_name = $file['name'];
		if($file['size']<=0){
			$obj->error = $app->trans->getBRUT('api', 3, 4); //File empty
		} else {
			copy($array_tmp_name, $folder->getPath().$array_name);
			$this->msg = $app->trans->getBRUT('api', 3, 3); //Upload Successful
			$this->error = false;
			$this->status = 200;
		}
		array_push($this->files, $obj);
		return true;
	}

}
