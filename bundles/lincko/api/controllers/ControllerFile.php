<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Folders;

class ControllerFile extends Controller {

	protected $app = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		return true;
	}

	protected function displayHTML($msg=''){
		$app = $this->app;
		ob_clean();
		header("Content-type: text/html; charset=UTF-8");
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

	public function _get(){
		$app = $this->app;
		$msg = '
<form id="api_file_form" action="https://file.'.$app->lincko->domain.':8443/file" method="post" target="toto" enctype="multipart/form-data" onsubmit="alert(\'ok\');return true;">
	<label for="api_file_form_video">
		<span id="api_file_upload_video">video</span>
		<input type="file" accept="video/*" capture="camcorder" id="api_file_form_video" name="file_video" />
	</label>
	<label for="api_file_form_photo">
		<span id="api_file_upload_photo">photo</span>
		<input type="file" accept="image/*" capture="camera" id="api_file_form_photo" name="file_photo" />
	</label>
	<label for="api_file_form_files">
		<span id="api_file_upload_files">files</span>
		<input type="file" id="api_file_form_files" name="file_files" />
	</label>
	<input type="hidden" value="untruc" name="something" />
</form>
		';
		return $this->displayHTML($msg);
	}

	public function _post(){
		$app = $this->app;

		$folder = new Folders;
		$folder->createPath($app->lincko->filePath.'/temp/');
		if(isset($_FILES)){\libs\Watch::php($_FILES,'$name',__FILE__);
			foreach ($_FILES as $file => $fileArray) {
				
				if(is_array($fileArray['tmp_name'])){
					foreach ($fileArray['tmp_name'] as $j => $value) {
						$array_tmp_name = $fileArray['tmp_name'][$j];
						$array_name = $fileArray['name'][$j];
						if($fileArray['size'][$j]>0){
							copy($array_tmp_name, $folder->getPath().$array_name);
						}
					}
				} else {
					$array_tmp_name = $fileArray['tmp_name'];
					$array_name = $fileArray['name'];
					if($fileArray['size']>0){
						copy($array_tmp_name, $folder->getPath().$array_name);
					}
				}

				
			}
		}

		$msg = var_export($app->request->post(), true);
		if(isset($_FILES)){
			$msg .= var_export($_FILES, true);
		}
		//\libs\Watch::php($msg,'$msg',__FILE__);
		return $this->displayHTML($msg);
	}

}
