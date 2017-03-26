<?php
// Category 13

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\Invitation;

class ControllerInvitation extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$data = json_decode($app->request->getBody());
		if(isset($data->data)){
			$this->data = $data->data;
		}
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		return true;
	}


	public function email_post(){
		$app = $this->app;
		$data = $this->data;
		$email = null;
		if(isset($data->invitation_code) && !empty($data->invitation_code)){
			if($invitation = Invitation::withTrashed()->where('code', $data->invitation_code)->whereNotNull('email')->first(array('email'))){
				$email = $invitation->email;
			}
		}
		$app->render(200, array('show' => false, 'msg' => $email));
		return exit(0);
	}

	//Asynchrone operation
	public function monkeyking_post(){
		$app = $this->app;

		$onboarding = new Onboarding;
		$onboarding->changeMonkeyKing();

		echo 'ok';
		return exit(0);
	}

}
