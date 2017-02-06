<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Folders;
use \bundles\lincko\api\models\libs\Action;
use \bundles\lincko\api\models\data\Users;

class ControllerInfo extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function beginning_post(){
		$app = $this->app;
		$path = $app->lincko->filePathPrefix.$app->lincko->filePath.'/'.$app->lincko->data['uid'].'/screenshot/';
		$folder = new Folders;
		$folder->createPath($path);
		if(isset($this->data->data) && isset($this->data->data->canvas) && $image = explode('base64,',$this->data->data->canvas)){
			if(isset($image[1])){
				file_put_contents($path.time(), base64_decode($image[1]));
			}
		}
		$app->render(200, array('msg' => 'ok',));
		return true;
	}

	public function action_post(){
		$app = $this->app;
		if(isset($this->data->data) && isset($this->data->data->action)){
			$action = $this->data->data->action;
			if(is_numeric($action)){
				//Always use negative for outside value, Positives value are used to follow history
				if($action>0){
					$action = -$action;
				}
				Action::record($action);
			}
		}
		$app->render(200, array('msg' => 'ok',));
		return true;
	}

	public function action_get($users_id){
		$app = $this->app;
		if(!Users::amIadmin()){
			return false;
		}
		ob_clean();
		flush();
		$app->response->headers->set('Content-Type', 'content="text/html; charset=UTF-8');
		echo '<div style=\'font-family:monospace;\'>';
		if($user = Users::find($users_id)){
			echo $user->username.' ['.$users_id.']'."<br />\n<br />\n";
			if($items = Action::Where('users_id', $users_id)->orderBy('created_at', 'asc')->get(array('created_at', 'action'))){
				$prev_day = false;
				$prev_created_at = false;
				$lap = 0;
				foreach ($items as $item) {
					$created_at = $item->created_at;
					$day = date('M d, Y', $created_at);
					$time = date('G : i : s', $created_at);
					if($day != $prev_day){
						echo $day."<br />\n";
						$prev_day = $day;
					}
					if(!$prev_created_at){
						$prev_created_at = $created_at;
					}
					$gap = $created_at - $prev_created_at;
					if($gap<=0){
						$gap = '&nbsp;&nbsp;0';
					} else if($gap<10){
						$gap = '&nbsp;&nbsp;'.$gap;
					} else if($gap<100){
						$gap = '&nbsp;'.$gap;
					} else if($gap>=1000){
						$gap = '+++';
					}
					
					echo $time.' ['.$gap.'] => '.Action::action($item->action)."<br />\n";
				}
			}
		}
		echo '</div>';
		return exit(0);
	}

}
