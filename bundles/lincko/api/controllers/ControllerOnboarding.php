<?php
// Category 13

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\Onboarding;

class ControllerOnboarding extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$data = json_decode($app->request->getBody());
		if(isset($data->data)){
			$this->data = $data->data;
		}
		return true;
	}


	public function next_post(){
		$app = $this->app;
		$data = $this->data;
		$lastvisit = time();

		$onboarding = new Onboarding;

		//Next is the Translation ID of the next question
		if(isset($data->next)){
			$answer = false;
			if(isset($data->answer) && !empty($data->answer)){
				$answer = $data->answer;
			}
			$onboarding->next($data->next, $answer);
		}

		//Current is the COmments_ID of the previous question (not the translation ID)
		if(isset($data->current)){
			$onboarding->answered($data->current);
		}

		$msg = array('msg' => $app->trans->getBRUT('api', 8888, 18)); //ou launched the next tutorial.
		$data = new Data();
		$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);

		return true;
	}

}
