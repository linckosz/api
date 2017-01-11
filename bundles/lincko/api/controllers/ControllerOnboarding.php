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
		if(isset($this->data->data) && !is_object($this->data->data)){
			$this->data->data = (object) $this->data->data;
		}
		return true;
	}


	public function next_post(){
		$app = $this->app;
		$data = $this->data;
		$lastvisit = time();

		$onboarding = new Onboarding;

		//Check if it has been answered previously
		$is_answered = false;
		if(isset($data->current)){
			$is_answered = $onboarding->isAnswered($data->current);
		}

		//Next is the Translation ID of the next question
		if(!$is_answered && isset($data->next)){
			$answer = false;
			if(isset($data->answer) && !empty($data->answer)){
				$answer = $data->answer;
			}
			$temp_id = '';
			if(isset($data->temp_id) && !empty($data->temp_id)){
				$temp_id = $data->temp_id;
			}
			$onboarding->next($data->next, $answer, $temp_id);
		}

		//Current is the Comments_ID of the previous question (not the translation ID)
		if(!$is_answered && isset($data->current)){
			$onboarding->answered($data->current);
		}

		$msg = array('msg' => $app->trans->getBRUT('api', 8888, 18)); //You launched the next tutorial.
		$data = new Data();
		$data->dataUpdateConfirmation($msg, 200, false, $lastvisit);

		return true;
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
