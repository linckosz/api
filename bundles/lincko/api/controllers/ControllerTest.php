<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Email;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\data\Chats;
use \bundles\lincko\api\models\data\ChatsComments;
use \bundles\lincko\api\models\data\Companies;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Tasks;
use Illuminate\Database\Capsule\Manager as Capsule;

class ControllerTest extends Controller {

	protected $app = NULL;
	protected $data = NULL;

	public function __construct(){
		$app = $this->app = \Slim\Slim::getInstance();
		$this->data = json_decode($app->request->getBody());
		return true;
	}

	public function _get(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 0); //The application is reading.

		//\libs\Watch::php(Users::getUser()->toJson(),'$user',__FILE__);
		
		//$tp = Users::where('email', '=', mb_strtolower('bruno@lincko.net'))->first()->usersLog;
		//$user = 1;
		//$user = Users::find(1)->users_log;
		//$user = UsersLog::find(1)->users;

		//$user = UsersLog::find(1)->users;

		//$tp = Users::getUser()->chatsComments;
		//$tp = Chats::find(1)->chatsComments;
		//$tp = ChatsComments::find(7)->chats;
		//$tp = Users::getUser();
		//$tp = Chats::find(1);
		//$tp = Users::getUser()->posts_a;
		//$tp = Users::getUser()->with('posts')->get();



		//\libs\Watch::php($tp->toJson(),'$tp',__FILE__);
		//\libs\Watch::php($tp,'$tp',__FILE__);

		//$user_log = UsersLog::where('email', '=', mb_strtolower($form->email))->first()

		//OK
		//$tp = Users::getUser()->chats

		$lastvisit = 0;
		//$lastvisit = (new \DateTime())->format('Y-m-d H:i:s');

		//$tp = Users::getLinked()->get();
		//$tp = Users::toto()->get();
		//$tp = ChatsComments::getLinked()->get();
		//$tp = Users::getUser()->users;
		//$tp = Companies::getLinked()->where('updated_at', '>=', $lastvisit)->get();

		//$tp = Users::getLinked()->get();
		//$tp = Tasks::getLinked()->get();
		//$tp = Users::getUser()->companies;
		//$tp = Users::toto();
		//$tp = Users::getUser()->users();
		//$tp = Users::getUser()->usersInvert();
		//\libs\Watch::php( $tp ,'$tp',__FILE__);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp',__FILE__);

		//$tp = Tasks::getLinked()->get();
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$get',__FILE__);
/*
		$tp = Tasks::getLinked()->get()->each(function($result){
			//\libs\Watch::php( $result ,'$tp',__FILE__);
			$tt = $result->tasks;
			\libs\Watch::php( json_decode($tt->toJson()) ,'$tt',__FILE__);
		});
*/
/*
		$tp = Tasks::getLinked()->get()->each(function($result){
			//\libs\Watch::php( $result ,'$tp',__FILE__);
			$tp = $result->tasks->each(function($result){
				//\libs\Watch::php( $result ,'$tp',__FILE__);
				$tp = $result->id;
				\libs\Watch::php( $tp ,'$tp',__FILE__);
			});
		});
*/
			
		//$tp = Tasks::all()->first()->getCompany();
		//\libs\Watch::php( $tp ,'$tp',__FILE__);

		//$tp = Projects::with('userAccess')->where('projects_x_users_access.access', 1)->get();

		//$tp = Projects::whereCompaniesId(0)->count();
		/*
		$tp = Projects::with(['userAccess' => function ($query){
			$query->where('access', 1);
			\libs\Watch::php( $query ,'$tp', __FILE__, false, true);
		}]);
		*/
		//$tp = Projects::with('userAccess')->first();
/*
		$tp = Projects::where('personal_private', 0)->with(['userAccess' => function ($query){
			$query->where('access', 1);
		}])->first();

		$tp = Projects::where('personal_private', 0)->whereHas('userAccess', function ($query){
			$query->where('access', 1);
		})->first();
*/
/*
		$tp = Projects::with(['userAccess' => function ($query){
			$query->where('access', 1);
		}])->first();
*/
/*
		$tp = Projects::whereHas('userAccess', function ($query){
			$query->where('access', 1);
		})->get();
*/

		$tp = Users::getUser()->usersContacts()->where('users_x_users.access', 1)->get();
		//$tp = Companies::getLinked()->find(2)->users;
		//$tp = Companies::getLinked()->find(2)->users()->where('users_x_companies.companies_id','<>','1')->get();
		//\libs\Watch::php( $tp ,'$tp', __FILE__, false, true);
		\libs\Watch::php( json_decode($tp->toJson()) ,'$tp', __FILE__, false, true);

		//$db = Capsule::connection('data');
		//$data = $db->getQueryLog();
		//\libs\Watch::php( $data ,'$data', __FILE__);
		//$msg = time();
		$app->render(200, array('msg' => $msg,));
		return true;
	}

	public function user_get(){
		$app = $this->app;
		if($user = Users::all()){	
			\libs\Watch::php($user->toJson(),'$user',__FILE__);
		}
		return true;
	}

	public function _post(){
		$app = $this->app;
		$msg = $app->trans->getBRUT('api', 8888, 1); //The application is saving data.
		$app->render(200, array('msg' => $msg,));
		return true;
	}

	public function email_get(){
		$app = $this->app;
		$mail = new Email();
		$mail->addAddress('someone@lincko.net', $app->trans->getBRUT('api', 8888, 2)); //Someone
		$mail->setSubject($app->trans->getBRUT('api', 8888, 3)); //PHPMailer test
		$mail->msgHTML('<html><body>'.$app->trans->getHTML('api', 8888, 4).'</body></html>'); //This is a HTML message test.
		if($mail->sendLater()){
			$msg = $app->trans->getBRUT('api', 8888, 5); //Email sent.
			$app->render(200, array('msg' => $msg,));
			return true;
		} else {
			$msg = $app->trans->getBRUT('api', 8888, 6); //Error: Could not send email.
			$app->render(400, array('msg' => $msg, 'error' => true,));
			return true;
		}
	}

}
