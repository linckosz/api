<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Email;
use \libs\Json;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\data\Chats;
use \bundles\lincko\api\models\data\ChatsComments;
use \bundles\lincko\api\models\data\Companies;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Roles;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder as Schema;

use Illuminate\Database\Eloquent\Relations\Pivot;

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
		if($app->config('mode') != 'development'){
			$app->render(200, array('msg' => 'Unauthorized access',));
			return true;
		}
		$msg = $app->trans->getBRUT('api', 8888, 0); //The application is reading.
		Capsule::connection('data')->enableQueryLog();

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
		//$tp = Tasks::find(14);
		//$tp = Users::getUser()->posts_a;
		//$tp = Users::getUser()->with('posts')->get();



		//\libs\Watch::php($tp->toJson(),'$tp',__FILE__);
		//\libs\Watch::php($tp,'$tp',__FILE__);

		//\libs\Watch::php( json_decode($tp->toJson()) ,'tp', __FILE__, false, false, true);

		//$user_log = UsersLog::where('email', '=', mb_strtolower($form->email))->first()

		//OK
		//$tp = Users::getUser()->chats

		//$lastvisit = 0;
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

		//$tp = Projects::with('userAccess')->where('users_x_projects.access', 1)->get();

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
		/*
		$tp = Users::getUser()->username;
		\libs\Watch::php( $tp ,'$tp', __FILE__, false, true);
*/

		//$tp = Users::getUser()->self()->where('users_x_users.access', 1)->get();
		//$tp = Companies::getLinked()->find(2)->users;
		//$tp = Companies::getLinked()->find(2)->users()->where('users_x_companies.companies_id','<>','1')->get();
		//\libs\Watch::php( $tp ,'$tp', __FILE__, false, true);

		//$tp = Tasks::find(7)->count();
		//$tp = Tasks::find(7)->hasColumn('created_by');
		//\libs\Watch::php( $tp ,'$tp', __FILE__, false, true);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp', __FILE__, false, true);
		//\error\sendMsg();

		//$tp = Tasks::find(4);
		//$tp->comment = "Un autre truc a faire";
		//$tp->save();

		//$tp = Projects::find(8);
		//$tp->title = "Test "rand(1, 9);
		//$tp->save();
	
		/*
		$tp = new Projects();
		//$tp = Projects::find(5);
		$tp->description = "Un truc a faire";
		$tp->title = 'OK DOKI '.rand();
		$tp->companies_id = 0;
		$tp->save();
		*/

		/*
		$tp = new Tasks();
		$tp->comment = "Un truc a faire";
		$tp->title = 'OK DOKI '.rand();
		$tp->projects_id = 1;
		$tp->save();
		*/

		/*
		$tp = new Chats();
		$tp->title = 'OK DOKI '.rand();
		$tp->save();
		*/
		/*
		$tp = new ChatsComments();
		$tp->comment = 'OK DOKI '.rand();
		$tp->chats_id = 1;
		$tp->save();
		*/

		//$tp = Users::getUser()->force_schema;
		//$data = new Data();
		//$tp = $data->getForceSchema();
		

		//\libs\Watch::php($tp, '$tp', __FILE__, false, false, true);

		//$tp = Users::getUser()->getUsersContacts();

		//$tp = Projects::find(5)->users()->theUser()->first()->pivot;
		//$tp = Projects::find(5)->getUsersPivot();
		//$tp = Projects::find(5)->with('users');//->get();

		//$tp = Projects::load('users')->get();

		//$tp = Chats::find(2)->setForceSchema();

		//$tp = Projects::find(5)->setUserAccess(2, 1);
		//$tp = Users::whereId([1,2,3]);
		//$tp->update(array('force_schema' => 0));
		//$tp->saveMany();

		//$tp = Users::where('force_schema', '=', 1)->update(['force_schema' => '0']);
		//$tp = Users::whereIn('id', [1, 3])->update(['force_schema' => '1']);
		//$tp = Users::whereIn('id', [1, 2, 3, 4]);
		//$tp->getQuery()->update(['force_schema' => '1']);
		//$tp->timestamps = false;
		//$tp->update(['force_schema' => '0']);
		
		




		//$tp = Projects::withTrashed()->find(5)->delete();
		//$tp = Projects::withTrashed()->find(5)->restore();
		//$tp = Projects::getLinked()->get();;
		
		//$tp = Projects::find(5)->getUsersContacts();
		//$tp = Tasks::find(4)->getUsersContacts();
		
		//$tp = Projects::find(5)->users()->attach(3);
		//$tp = Projects::find(5)->setUserAccess(3, 1);
		//$tp = Projects::find(5)->users()->detach(3);

		//$tp = Users::find(1)->getUsersContacts();

		//$tp = Users::find(1)->users()->get();
		//$tp = Users::find(1)->users()->first();
		//$tp = $tp->pivot->access;

		//$tp = Projects::find(5)->$temp;
		//$tp = Companies::find(1)->getUsersContacts();

		//$tp = Projects::find(5)->users()->find(2)->pivot;//->access;
		//$tp = Users::getUser()->force_schema;

		//$tp->setUserAccess(0);
		//$tp = Projects::find(5)->getUserAccess();
		//Projects::find(5)->users()->save($role, array('expires' => $expires));
		//$tp = Projects::find(5)->users()->find(1);
		//$tp = Projects::find(5)->users()->theUser()->first();
		//$tp = Projects::find(5)->users()->find(10);
		//$tp = Users::find($app->lincko->data['uid'])->getUsersContacts();



/*
		$tp = Chats::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Chats', __FILE__, false, false, true);

		$tp = ChatsComments::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$ChatsComments', __FILE__, false, false, true);

		$tp = Companies::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Companies', __FILE__, false, false, true);

		$tp = Projects::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Projects', __FILE__, false, false, true);
*/
		//$tp = Tasks::find(8)->users()->get();
		//\libs\Watch::php( $tp ,'$Tasks', __FILE__, false, false, true);
		//\libs\Watch::php( Tasks::getLinked()->find($app->lincko->data['uid']) ,'$Tasks', __FILE__, false, false, true);
		//$tp = Tasks::getLinked()->get();
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$Tasks', __FILE__, false, false, true);
/*
		$tp = Users::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Users', __FILE__, false, false, true);
*/
		//$tp = Projects::find(5)->getRelations();
		//\libs\Watch::php( $tp ,'$Projects', __FILE__, false, false, true);

		//$tp = Projects::find(5);
		//\libs\Watch::php( $tp->getTable() ,'Object', __FILE__, false, false, true);

		/*
		$tp = array(
			0 => 'A',
			1 => 'B',
			3 => 'C',
			4 => 'D',
		);

		$tp = array_merge($tp);

		\libs\Watch::php( $tp ,'tp', __FILE__, false, false, true);
		*/

		//\libs\Watch::php( Tasks::$relations_keys ,'models ', __FILE__, false, false, true);
		//\libs\Watch::php( Tasks::getRelations() ,'models ', __FILE__, false, false, true);
		//\libs\Watch::php( Tasks::$relations_keys ,'models ', __FILE__, false, false, true);

		/*
		$tp1 = new Projects();
		$tp2 = new Tasks();
		$tp2->getRelations();
		\libs\Watch::php( $tp1::$relations_keys_list ,'Projects', __FILE__, false, false, true);
		\libs\Watch::php( $tp2::$relations_keys_list ,'Tasks', __FILE__, false, false, true);
		\libs\Watch::php( $tp1::$relations_keys_list ,'Projects', __FILE__, false, false, true);
		*/
		//\libs\Watch::php( Projects::getTableStatic()  ,'Projects', __FILE__, false, false, true);
		//\libs\Watch::php( Users::getTableStatic()  ,'Users', __FILE__, false, false, true);


		//$tp = new Data();
		//\libs\Watch::php( $tp->toto() ,'toto', __FILE__, false, false, true);

		//$tp = ChatsComments::find(7)->users()->first();

		//\libs\Watch::php($tp, '$tp', __FILE__, false, false, true);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp', __FILE__, false, false, true);

		//$tp = new Projects();
		//$tp = Projects::find(5);
		//$tp->title = 'Un titre '.rand();
		//$tp->description = 'Une description';
		//$tp->companies_id = 0;
		//$tp->save();

		//$db = Capsule::connection('data');
		//$data = $db->getQueryLog();
		//\libs\Watch::php( $data ,'$data', __FILE__);
		//$msg = time();


		//$tp = Projects::withTrashed()->find(201)->delete();
		//$tp = Projects::withTrashed()->find(201)->restore();

		//$tp = new Tasks();
		//$tp->users()->find($app->lincko->data['uid']);
		//$tp = Projects::find(5);
		//$tp = Users::find(2);
		//$tp1 = ChatsComments::find(7);
		//$tp1 = Tasks::find(76);
		//$tp = (bool) $tp->users()->whereId($app->lincko->data['uid'])->whereAccess(1)->first();
		//$tp = Users::getUser()->self()->where('users_x_users.access', 1)->get();
		//$tp = $tp1->chats()->users();//->first();
		//$tp = $tp1->getUserAccess();
		//$tp = Projects::find(5)->getUserAccess();

		//$tp = Tasks::find(76)->hasNot('users');//->get();
		/*
		$tp = Tasks::whereHas("users", function($query) {
			$app = \Slim\Slim::getInstance();
			$uid = $app->lincko->data['uid'];
			$query->where('users_id', $uid)->where('access', 0);
		}, '<', 1)->get();
		*/

		//$tp = Users::find(2)->getUserAccess();
		//$tp = Users::find(2)->users()->whereId($app->lincko->data['uid'])->whereAccess(1)->first();

		//$tp = Users::find(22);
		//$tp = Projects::find(8);
		//$tp->setUserPivotValue(2, 'access', 1, true);
		/*
		$tp = Users::getUser();
		$tp->setUserPivotValue(22, 'access', 1, true);
		*/

		//$tp = Tasks::getItemsAccess();
		//$tp = Tasks::find(4)->getUsersContacts();
		//$tp = Tasks::find(4)->projects()->get();
		//$tp = Users::find(2)->getHistory(true);
		//$tp = Users::find(2)->getItems();
		//$tp = $tp->projects()->first();
		//$tp->projects()->whereId($this->projects_id)->whereAccess(1)->first()
		//\libs\Watch::php( $tp , $app->lincko->data['uid'], __FILE__, false, false, true);

		//$tp = History::whereType('projects')->whereTypeId([8, 201])->get();
		//$tp = History::whereType('projects')->whereIn('type_id', [8, 201])->get();
		//$tp = Projects::getHistories([8, 201, 180]);
		//$tp = Projects::find(8)->getHistory();
		//$tp = Tasks::getUsersContactsID([54, 77]);
		//$tp = Companies::getUsersContactsID([0]);

		//$tp = Tasks::whereIn('id', [77])->get()->toArray();

		//$tp = Companies::find(2);
		//$tp->setUserPivotValue(1, 'access', 1, true);

		//$tp = Companies::getLinked()->get()->toArray();

		//$tp = Tasks::find(16);
		//$tp = $tp->users()->whereId($app->lincko->data['uid'])->whereAccess(1)->get()->toArray();
		//$tp = $tp->projects()->whereId($tp->projects_id)->whereAccess(1)->get()->toArray();
		//$tp = $tp->projects()->whereId($tp->projects_id)->get()->toArray();
		//$tp = $tp->users()->whereAccess(1)->get()->toArray();

		//$tp = $tp->getUsersContacts();
		//$tp = $tp->theUser()->users()->get()->toArray();
		/*
		$tp = Projects::where('personal_private', null)->whereHas('users', function ($query){
			$query->where('access', 1);
		})->get(['id'])->toArray();
		*/

		//$tp = (bool) Chats::getLinked()->get();
		//$tp = Chats::getLinked()->count();

		//$tp = Tasks::find(16);
		//$tp->setUserPivotValue(1, 'access', 0, false);
		//$tp = $tp->checkAccess();
		
		//$tp = Users::find(1);
		//$tp = $tp->roles()->get(['id'])->toArray();
		/*
		$tp = Roles::
			where('users_id', $app->lincko->data['uid'])
			->orWhere('companies_id', $app->lincko->data['company_id'])
			->orWhere('companies_id', null)
			->get(['id'])->toArray();
		*/
		//$tp = Users::getUser()->roles()->where('users_x_companies.companies_id', $app->lincko->data['company_id'])->first();//->toArray();
		/*
		$tp = Users::getUser()->whereHas('roles', function ($query){
			$app = \Slim\Slim::getInstance();
			$query->where('users_x_companies.companies_id', $app->lincko->data['company_id']);
		})->get()->toArray();
		*/
		
		//$tp = Users::getUser()->with('roles')->get()->toArray();

		//$tp = Roles::getItems()->toArray();
		//$tp = Roles::whereIn('companies_id', [$app->lincko->data['company_id'], null])->get()->toArray();
		//$tp = Roles::where('companies_id', null)->get()->toArray();
		
		//$tp = Roles::with('users')->find(6);
		//$tp = Roles::getUsersContactsID([1, 5, 6]);

		//$tp = (new Roles)->whereIn('id', ['1,4,6'])->with('users')->get();

		//$tp = Roles::whereIn('id', [1, 6])->with('users')->get();

		//$tp = Users::getItems();
		//$tp = Users::find(1);
		//$tp = $tp->toJson();

		//$tp = Companies::with('projects.tasks')->find(3)->toJson();

		//$tp = Companies::find(1)->roles()->get();

		//$tp = Companies::find(1)->roles()->get()->toArray();
		//$tp = Tasks::getDependencies([4]);
		
		//$tp = Projects::find(1)->roles()->get()->toArray();
/*
		$tp = Users::getUser()->roles()->get(); //This is not optimized because need jointure
		\libs\Watch::php( $tp->toArray() ,'$tp', __FILE__, false, false, true);
		$list_roles = array();
		foreach ($tp as $value){
			$type = $value->pivot->relation_type;
			$id = $value->pivot->relation_id;
			if( !isset($list_roles[$type]) ){ $list_roles[$type] = array(); }
			if( !isset($list_roles[$type][$id]) ){ $list_roles[$type][$id] = array(); }
			$list_roles[$type][$id] = array(
				'role' => $value->pivot->roles_id,
				'edit' => $value->pivot->single,
			);
		}

		$tp = $list_roles;
*/



		/*
		$tp = PivotUsersRoles::getLinked()->get()->toArray();

		$roles = PivotUsersRoles::getLinked()->get();
		$roles_list = array();
		//Get list of all role rules
		foreach($roles as $value) {
			if(!isset($roles_list[$value->relation_type])){ $roles_list[$value->relation_type] = array(); }
			$roles_list[$value->relation_type][$value->relation_id] = array(
				'roles_id' => $value->roles_id,
				'single' => $value->single,
			);
		}
		//Clean the list according to what is allowed
		foreach($roles_list as $value) {

		}
		$tp = $roles_list;
		*/


		/*
		//$tp = Users::find(1);
		//$tp1 = Tasks::find(4);
		$tp1 = Projects::find(5);
		$tp = $tp1->getRolePivotValue($app->lincko->data['uid']);
		\libs\Watch::php( $tp ,'$getRolePivotValue', __FILE__, false, false, true);
		$tp = $tp1->checkRole(1);
		*/
		//$tp = Projects::find(5)->getRolePivotValue($app->lincko->data['uid']);

		//$tp = Companies::find(17);
		//$tp = $tp->getCompanyGrant();

		//$tp = Companies::find(1)->roles;
		//$tp = Companies::with('rolesMany')->get();
		//$tp = Companies::with('rolesPoly')->get();

		//$tp = Tasks::find(4);
		//$tp = isset($tp->created_byy);
		//$tp = new Tasks();
		//$tp = Tasks::getColumns();
		//$tp = (new Schema((new Tasks())->connexion))->getColumnListing(Tasks::getClass());
		//$tp = Projects::find(5);

		//$tp = Companies::find(3);
		//$tp = Companies::find(3)->roles()->first()->perm_grant;
		//$tp = chatsComments::find(7);
		//$tp = Chats::find(1);

		//$tp = $tp->setRolePivotValue(40, 3, 3);
		//$tp = Companies::find(1);//->roles()->first()->perm_grant;

		//$tp = Projects::find(8)->users();
		//Block
		//$tp->updateExistingPivot(1, array('access' => 0));
		//$tp->updateExistingPivot(1, array('access' => 1));

		//$tp = Tasks::find(4);
		//$tp = $tp->rolesUsers()->where('pivot_users_id', 40)->first();
		//$tp = $tp->rolesUsers()->wherePivot('users_id', '=', 40)->get();
		//$tp = $tp->getRolePivotValue(1);
		//$tp = $tp->newExistingPivot();
		//$tp->setRolePivotValue(40, 2);
		//$tp->attach(40, array('users_id' => 40, 'roles_id' => 4, 'single' => null, 'relation_type' => 'tasks', 'relation_id' => 4));

		$tp = 'ok';
		
		$projects = Projects::all();
		
		foreach ($projects as $key => $value) {
			$value->toto();
			break;
		}
		

		//$tp = new Pivot($tp, array('users_id' => 1), 'users_x_roles_x', true);

		\libs\Watch::php( $tp , '$tp', __FILE__, false, false, true);

		//----------------------------------------

		//Display mysql requests
		\libs\Watch::php( Capsule::connection('data')->getQueryLog() , 'QueryLog', __FILE__, false, false, true);

		

		//----------------------------------------
	
		//Add new project
		/*
		$tp = new Projects();
		$tp->description = "Something to following";
		$tp->title = 'toto '.rand();
		$tp->companies_id = $app->lincko->data['company_id'];
		$tp->save();
		*/
		
		//Modif Project title
		/*
		$tp = Projects::find(5);
		$tp->title = '['.rand(1, 99).'] Design '.rand();
		$tp->save();
		*/
		/*
		$tp = Projects::find(8);
		$tp->title = 'Test '.rand(1, 9);
		$tp->save();
		*/

		//Project access
		/*
		$tp = Projects::find(8);
		//Block
		//$tp->setUserPivotValue(1, 'access', 0, true);
		//Authorize
		$tp->setUserPivotValue(1, 'access', 1, true);
		*/

		//Add new task
		
		/*
		$tp = new Tasks();
		$tp->comment = "Something to do quickly";
		$tp->title = 'A task name '.rand();
		$tp->projects_id = 5;
		$tp->save();
		*/

		//Add new task (with missing argument)
		/*
		$tp = new Tasks();
		$tp->comment = "Something to do quickly";
		//$tp->title = 'B';
		$tp->projects_id = 5;
		$tp->save();
		*/

		//Move a task to another project
		/*
		$tp = Tasks::find(4);
		if($tp->projects_id==8){
			$tp->projects_id = 5;
		} else {
			$tp->projects_id = 8;
		}
		$tp->save();
		*/

		//--------------------------------------

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
		$msg = $app->trans->getBRUT('api', 8888, 1).' => '.json_encode($this->data->data); //The application is saving data.
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

	// Test role rules
	// Must log as an adminsitrator in perso workspace (invite at least a manager and a viewer)
	public function role_get(){
		$app = $this->app;
		Capsule::connection('data')->enableQueryLog();
		$theuser = Users::find($app->lincko->data['uid']);
		$theuser::setDebugMode(true);

		$models = new \stdClass;
		$models->companies = Companies::whereId($app->lincko->data['company_id'])->where('created_by', $app->lincko->data['uid'])->first();
		$models->roles = (new Roles)->getLinked()->where('shared', 0)->where('created_by', $app->lincko->data['uid'])->where('companies_id', $app->lincko->data['company_id'])->first();
		$models->chats = $theuser->chats()->where('created_by', $app->lincko->data['uid'])->first();
		$models->chats_comments = $models->chats->chatsComments()->where('created_by', $app->lincko->data['uid'])->first();
		$models->projects = $theuser->projects()->where('created_by', $app->lincko->data['uid'])->first();
		$models->tasks = $models->projects->tasks()->where('created_by', $app->lincko->data['uid'])->first();

		//Clean if some model doesn't exists
		foreach ($models as $key => $model) {
			if(!$model){
				unset($models->$key);
			}
		}

		$pivots = (new PivotUsersRoles)->sameCompany()->get();

		$users = array();
		foreach ($pivots as $pivot) {
			//Allow only to work with standard roles for debugging purpose, their ID must be 1, 2, 3
			$roles_id = intval($pivot->roles_id);
			if($roles_id>=1 && $roles_id<=3){
				$users[$roles_id] = $pivot->users_id;
				foreach ($models as $model) {
					if($model){
						$model->setUserPivotValue($pivot->users_id, 'access', 1, false);
						if($model->getTable() != 'companies'){
							$model->setRolePivotValue($pivot->users_id, null, null, false);
						}
					}
				}
				//Role initialization for companies
				if($pivot->users_id != $app->lincko->data['uid']){
					//Be care full manager must be 2, and viewer must be 3
					$role_id = 2;
					if(Users::find($pivot->users_id)->username == 'viewer'){ $role_id = 3; }
					$models->companies->setRolePivotValue($pivot->users_id, $role_id, null, false);
				}
			}
		}

		//Set the outsider user, he should be rejected by everything
		$users[0] = Users::whereHas('companies', function ($query){
			$app = \Slim\Slim::getInstance();
			$query->where('companies_id', $app->lincko->data['company_id'])->where('access', 1);
		}, '<', 1)->first()->id;
		

		ksort($users);

		/*
		Roles
		[
			-1:owner	=> [ read , edit , delete , create ]
			0 :outsider (don't share anything)
			1 :admin (share smae company, same chat room)
			2 :manager (share smae company, same chat room)
			3 :viewer (share smae company, same chat room)
		]
		*/
		$accept = array(
			/*
				 0: cannot
				 1: can
				-1: can only if creator
			*/
			'companies' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 1 ), //outsider
				1	=> array( 1 , 1 , 0 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 1 ), //manager
				3	=> array( 1 , 0 , 0 , 1 ), //viewer
			),
			'roles' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 0 ), //outsider
				1	=> array( 1 , 1 , 1 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 0 ), //manager
				3	=> array( 1 , 0 , 0 , 0 ), //viewer
			),
			'chats' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 1 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 1 ), //outsider
				1	=> array( 1 , 0 , 0 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 1 ), //manager
				3	=> array( 1 , 0 , 0 , 1 ), //viewer
			),
			'chats_comments' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 1 ), //outsider
				1	=> array( 1 , 0 , 0 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 1 ), //manager
				3	=> array( 1 , 0 , 0 , 1 ), //viewer
			),
			'projects' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 0 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 0 ), //outsider
				1	=> array( 1 , 1 , 1 , 1 ), //administrator
				2	=> array( 1 , 0 , 0 , 0 ), //manager
				3	=> array( 1 , 0 , 0 , 0 ), //viewer
			),
			'tasks' => array( //[ read , edit , delete , create ]
				-1	=> array( 1 , 0 , 1 , 0 ), //owner
				0	=> array( 0 , 0 , 0 , 0 ), //outsider
				1	=> array( 1 , 1 , 1 , 1 ), //administrator
				2	=> array( 1 ,-1 , 0 , 1 ), //manager (can edit only if creator)
				3	=> array( 1 , 0 , 0 , 1 ), //viewer
			),
		);
		
		//Role 1 is also the owner, so we regroup owner+administrator
		foreach ($accept as $i => $tab_i) {
			foreach ($accept[$i][1] as $j => $tab_j) {
				$accept[$i][1][$j] = intval($accept[$i][-1][$j] || $accept[$i][1][$j]);
			}
		}

		\libs\Watch::php( '!!!!!!!!!! START !!!!!!!!!!' , 'Permissions', __FILE__, false, false, true);

		foreach ($users as $role_id => $user_id) {
			$app->lincko->data['uid'] = $user_id;

			foreach ($accept as $key => $table) {	
				if(!isset($models->{$key})){
					continue;
				}
				$model = $models->{$key};
				$new_model = $model->newinstance();

				if($key == 'companies'){
					$new_model->name = $model->name = '_Company '.rand();
					$model->allowCompanyCreation();
					$new_model->allowCompanyCreation();
				} else if($key == 'roles'){
					$new_model->name = $model->name = '_Role '.rand();
				}  else if($key == 'chats'){
					$new_model->title = $model->title = '_Chat '.rand();
				} else if($key == 'chats_comments'){
					$new_model->chats_id = $model->chats_id;
					$new_model->comment = $model->comment = '_Comment '.rand();
				} else if($key == 'projects'){
					$new_model->title = $model->title = '_Project '.rand();
				} else if($key == 'tasks'){
					$new_model->projects_id = $model->projects_id;
					$new_model->title = $model->title = '_Task '.rand();
				}

				//Reinitialize variables
				$model->checkUser();
				$new_model->checkUser();

				//Read
				if( $a=intval($model->checkAccess()) xor $b=intval($accept[ $key ][ $role_id ][ 0 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkRole('read'));
						\libs\Watch::php( $a.'...BUG READ...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->access() => User: '.$user_id.' | role: '.$role_id, __FILE__, false, false, true);
					}
				}

				//Edit
				if( $a=intval($model->save()) xor $b=intval($accept[ $key ][ $role_id ][ 1 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkRole('edit'));
						\libs\Watch::php( $a.'...BUG EDIT...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->edit() => User: '.$user_id.' | role: '.$role_id, __FILE__, false, false, true);
					}
				}

				//Delete
				if( $a=intval($model->delete()) xor $b=intval($accept[ $key ][ $role_id ][ 2 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkRole('delete'));
						\libs\Watch::php( $a.'...BUG DELETE...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->delete() => User: '.$user_id.' | role: '.$role_id, __FILE__, false, false, true);
					}
				}

				//Creation
				if( $a=intval($new_model->save()) xor $b=intval($accept[ $key ][ $role_id ][ 3 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($new_model->checkAccess());
						$d = intval($new_model->checkRole('edit'));
						\libs\Watch::php( $a.'...BUG CREATION...'.$b.': '.$c.'|'.$d , '[*] '.$new_model->getTable().'->create() => User: '.$user_id.' | role: '.$role_id, __FILE__, false, false, true);
					}
				}

			}

		}

		\libs\Watch::php( '!!!!!!!!!!  END  !!!!!!!!!!' , 'Permissions', __FILE__, false, false, true);
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() , 'QueryLog', __FILE__, false, false, true);
		$json = new Json('OK', false);
		$json->render();
		return true;
	}

}
