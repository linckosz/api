<?php

namespace bundles\lincko\api\controllers\integration;

use \bundles\lincko\api\models\Integration;
use \libs\Controller;
use \libs\Json;
use Endroid\QrCode\QrCode;
use \config\Handler;

class ControllerIntegration extends Controller {

	protected $app = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		return true;
	}

	public function connect_post(){
		$app = $this->app;
		$json = new Json('Third party connection succeed!', false, 200, false, false, array(), false);
		$json->render(200);
		return true;
	}

	public function code_get(){
		Handler::session_initialize(true);
		if(isset($_SESSION['integration_code'])){
			echo $_SESSION['integration_code'];
		} else {
			echo false;
		}
		return exit(0);
	}

	public function qrcode_get($mini=false){
		$app = $this->app;
		Integration::clean();
		ob_clean();
		flush();
		$code = substr(md5(uniqid()), 0, 8);
		while(Integration::find($code)){
			usleep(10000);
			$code = substr(md5(uniqid()), 0, 8);
		}

		/*
		$integration = new Integration;
		$integration->code = $code;
		$integration->save();
		*/
		Handler::session_initialize(true);
		$_SESSION['integration_code'] = $code;


		$url = $_SERVER['REQUEST_SCHEME'].'://'.$_SERVER['SERVER_HOST'].'/integration/code/'.$code;
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

		//https://packagist.org/packages/endroid/qrcode
		$qrCode = new QrCode();

		if($mini && $mini_path = $app->lincko->path.'/bundles/lincko/api/public/images/generic/integration/'.$mini.'.png'){
			if(is_file($mini_path)){
				$qrCode
					->setLogo($mini_path)
					->setLogoSize(144)
				;
			}
		}

		$qrCode
			->setText($url)
			->setSize(800)
			->setPadding(40)
			->setErrorCorrection('medium')
			//->setForegroundColor(array('r' => 251, 'g' => 160, 'b' => 38, 'a' => 0)) //Orange is not working very well
			->setForegroundColor(array('r' => 0, 'g' => 0, 'b' => 0, 'a' => 0))
			->setBackgroundColor(array('r' => 255, 'g' => 255, 'b' => 255, 'a' => 0))
			->setImageType(QrCode::IMAGE_TYPE_PNG)
		;
		header('Content-Type: '.$qrCode->getContentType());
		$qrCode->render();
		
		return exit(0);
	}

}
