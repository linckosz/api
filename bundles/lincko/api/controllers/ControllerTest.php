<?php

namespace bundles\lincko\api\controllers;

use \libs\Controller;
use \libs\Email;
use \libs\Folders;
use \libs\Json;
use \libs\STR;
use \libs\Network;
use \libs\Datassl;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\History;
use \bundles\lincko\api\models\libs\PivotUsersRoles;
use \bundles\lincko\api\models\libs\PivotUsers;
use \bundles\lincko\api\models\libs\Invitation;
use \bundles\lincko\api\models\libs\Tree;
use \bundles\lincko\api\models\libs\Models;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\libs\Updates;
use \bundles\lincko\api\models\UsersLog;
use \bundles\lincko\api\models\Onboarding;
use \bundles\lincko\api\models\Notif;
use \bundles\lincko\api\models\data\Chats;
use \bundles\lincko\api\models\data\Workspaces;
use \bundles\lincko\api\models\data\Projects;
use \bundles\lincko\api\models\data\Users;
use \bundles\lincko\api\models\data\Tasks;
use \bundles\lincko\api\models\data\Roles;
use \bundles\lincko\api\models\data\Comments;
use \bundles\lincko\api\models\data\Notes;
use \bundles\lincko\api\models\data\Files;
use \bundles\lincko\api\models\data\Settings;
use \bundles\lincko\api\models\data\Spaces;
use \bundles\lincko\api\models\data\Messages;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Builder as Schema;
use Illuminate\Database\Eloquent\Relations\BelongsTo as BelongsTo;
use Carbon\Carbon;
use \libs\Translation;
use JPush\Client as JPush;

use Illuminate\Database\Eloquent\Relations\Pivot;

//http://stackoverflow.com/questions/3684463/php-foreach-with-nested-array


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
		$db = Capsule::connection('data');
		$db->enableQueryLog();
		$tp = null;

		//\libs\Watch::php(Users::getUser()->toJson(),'$user', __FILE__, __LINE__);
		
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



		//\libs\Watch::php($tp->toJson(),'$tp',__FILE__, __LINE__);
		//\libs\Watch::php($tp,'$tp',__FILE__, __LINE__);

		//\libs\Watch::php( json_decode($tp->toJson()) ,'tp', __FILE__, __LINE__, false, false, true);

		//$user_log = UsersLog::where('email', '=', mb_strtolower($form->email))->first()

		//OK
		//$tp = Users::getUser()->chats

		//$lastvisit = 0;
		//$lastvisit = (new \DateTime())->format('Y-m-d H:i:s');

		//$tp = Users::getLinked()->get();
		//$tp = Users::toto()->get();
		//$tp = ChatsComments::getLinked()->get();
		//$tp = Users::getUser()->users;
		//$tp = Workspaces::getLinked()->where('updated_at', '>=', $lastvisit)->get();

		//$tp = Users::getLinked()->get();
		//$tp = Tasks::getLinked()->get();
		//$tp = Users::getUser()->workspaces;
		//$tp = Users::toto();
		//$tp = Users::getUser()->users();
		//$tp = Users::getUser()->usersInvert();
		//\libs\Watch::php( $tp ,'$tp',__FILE__, __LINE__);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp',__FILE__, __LINE__);

		//$tp = Tasks::getLinked()->get();
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$get',__FILE__, __LINE__);
/*
		$tp = Tasks::getLinked()->get()->each(function($result){
			//\libs\Watch::php( $result ,'$tp',__FILE__);
			$tt = $result->tasks;
			\libs\Watch::php( json_decode($tt->toJson()) ,'$tt',__FILE__, __LINE__);
		});
*/
/*
		$tp = Tasks::getLinked()->get()->each(function($result){
			//\libs\Watch::php( $result ,'$tp',__FILE__, __LINE__);
			$tp = $result->tasks->each(function($result){
				//\libs\Watch::php( $result ,'$tp',__FILE__, __LINE__);
				$tp = $result->id;
				\libs\Watch::php( $tp ,'$tp',__FILE__, __LINE__);
			});
		});
*/
			
		//$tp = Tasks::all()->first()->getWorkspaceID();
		//\libs\Watch::php( $tp ,'$tp',__FILE__, __LINE__);

		//$tp = Projects::with('userAccess')->where('users_x_projects.access', 1)->get();

		//$tp = Projects::whereWorkspacesId(0)->count();
		/*
		$tp = Projects::with(['userAccess' => function ($query){
			$query->where('access', 1);
			\libs\Watch::php( $query ,'$tp', __FILE__, __LINE__, false, true);
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
		\libs\Watch::php( $tp ,'$tp', __FILE__, __LINE__, false, true);
*/

		//$tp = Users::getUser()->self()->where('users_x_users.access', 1)->get();
		//$tp = Workspaces::getLinked()->find(2)->users;
		//$tp = Workspaces::getLinked()->find(2)->users()->where('users_x_workspaces.workspaces_id','<>','1')->get();
		//\libs\Watch::php( $tp ,'$tp', __FILE__, __LINE__, false, true);

		//$tp = Tasks::find(7)->count();
		//$tp = Tasks::find(7)->hasColumn('created_by');
		//\libs\Watch::php( $tp ,'$tp', __FILE__, __LINE__, false, true);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp', __FILE__, __LINE__, false, true);
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
		$tp->parent_id = 0;
		$tp->save();
		*/

		/*
		$tp = new Tasks();
		$tp->comment = "Un truc a faire";
		$tp->title = 'OK DOKI '.rand();
		$tp->parent_id = 1;
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
		

		//\libs\Watch::php($tp, '$tp', __FILE__, __LINE__, false, false, true);

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
		//$tp = Workspaces::find(1)->getUsersContacts();

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
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Chats', __FILE__, __LINE__, false, false, true);

		$tp = ChatsComments::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$ChatsComments', __FILE__, __LINE__, false, false, true);

		$tp = Workspaces::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Workspaces', __FILE__, __LINE__, false, false, true);

		$tp = Projects::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Projects', __FILE__, __LINE__, false, false, true);
*/
		//$tp = Tasks::find(8)->users()->get();
		//\libs\Watch::php( $tp ,'$Tasks', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( Tasks::getLinked()->find($app->lincko->data['uid']) ,'$Tasks', __FILE__, __LINE__, false, false, true);
		//$tp = Tasks::getLinked()->get();
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$Tasks', __FILE__, __LINE__, false, false, true);
/*
		$tp = Users::getLinked()->get();
		\libs\Watch::php( json_decode($tp->toJson()) ,'$Users', __FILE__, __LINE__, false, false, true);
*/
		//$tp = Projects::find(5)->getRelations();
		//\libs\Watch::php( $tp ,'$Projects', __FILE__, __LINE__, false, false, true);

		//$tp = Projects::find(5);
		//\libs\Watch::php( $tp->getTable() ,'Object', __FILE__, __LINE__, false, false, true);

		/*
		$tp = array(
			0 => 'A',
			1 => 'B',
			3 => 'C',
			4 => 'D',
		);

		$tp = array_merge($tp);

		\libs\Watch::php( $tp ,'tp', __FILE__, __LINE__, false, false, true);
		*/

		//\libs\Watch::php( Tasks::$relations_keys ,'models ', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( Tasks::getRelations() ,'models ', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( Tasks::$relations_keys ,'models ', __FILE__, __LINE__, false, false, true);

		/*
		$tp1 = new Projects();
		$tp2 = new Tasks();
		$tp2->getRelations();
		\libs\Watch::php( $tp1::$relations_keys_list ,'Projects', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp2::$relations_keys_list ,'Tasks', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp1::$relations_keys_list ,'Projects', __FILE__, __LINE__, false, false, true);
		*/
		//\libs\Watch::php( Projects::getTableStatic()  ,'Projects', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( Users::getTableStatic()  ,'Users', __FILE__, __LINE__, false, false, true);


		//$tp = new Data();
		//\libs\Watch::php( $tp->toto() ,'toto', __FILE__, __LINE__, false, false, true);

		//$tp = ChatsComments::find(7)->users()->first();

		//\libs\Watch::php($tp, '$tp', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( json_decode($tp->toJson()) ,'$tp', __FILE__, __LINE__, false, false, true);

		//$tp = new Projects();
		//$tp = Projects::find(5);
		//$tp->title = 'Un titre '.rand();
		//$tp->description = 'Une description';
		//$tp->parent_id = 0;
		//$tp->save();

		//$db = Capsule::connection('data');
		//$data = $db->getQueryLog();
		//\libs\Watch::php( $data ,'$data', __FILE__, __LINE__);
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
		//$tp->projects()->whereId($this->parent_id)->whereAccess(1)->first()
		//\libs\Watch::php( $tp , $app->lincko->data['uid'], __FILE__, __LINE__, false, false, true);

		//$tp = History::whereParentType('projects')->whereParentId([8, 201])->get();
		//$tp = History::whereParentType('projects')->whereIn('parent_id', [8, 201])->get();
		//$tp = Projects::getHistories([8, 201, 180]);
		//$tp = Projects::find(8)->getHistory();
		//$tp = Tasks::getUsersContactsID([54, 77]);
		//$tp = Workspaces::getUsersContactsID([0]);

		//$tp = Tasks::whereIn('id', [77])->get()->toArray();

		//$tp = Workspaces::find(2);
		//$tp->setUserPivotValue(1, 'access', 1, true);

		//$tp = Workspaces::getLinked()->get()->toArray();

		//$tp = Tasks::find(16);
		//$tp = $tp->users()->whereId($app->lincko->data['uid'])->whereAccess(1)->get()->toArray();
		//$tp = $tp->projects()->whereId($tp->parent_id)->whereAccess(1)->get()->toArray();
		//$tp = $tp->projects()->whereId($tp->parent_id)->get()->toArray();
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
			->orWhere('parent_id', $app->lincko->data['workspace_id'])
			->orWhere('parent_id', null)
			->get(['id'])->toArray();
		*/
		//$tp = Users::getUser()->roles()->where('users_x_workspaces.workspaces_id', $app->lincko->data['workspace_id'])->first();//->toArray();
		/*
		$tp = Users::getUser()->whereHas('roles', function ($query){
			$app = \Slim\Slim::getInstance();
			$query->where('users_x_workspaces.workspaces_id', $app->lincko->data['workspace_id']);
		})->get()->toArray();
		*/
		
		//$tp = Users::getUser()->with('roles')->get()->toArray();

		//$tp = Roles::getItems()->toArray();
		//$tp = Roles::whereIn('parent_id', [$app->lincko->data['workspace_id'], null])->get()->toArray();
		//$tp = Roles::where('parent_id', null)->get()->toArray();
		
		//$tp = Roles::with('users')->find(6);
		//$tp = Roles::getUsersContactsID([1, 5, 6]);

		//$tp = (new Roles)->whereIn('id', ['1,4,6'])->with('users')->get();

		//$tp = Roles::whereIn('id', [1, 6])->with('users')->get();

		//$tp = Users::getItems();
		//$tp = Users::find(1);
		//$tp = $tp->toJson();

		//$tp = Workspaces::with('projects.tasks')->find(3)->toJson();

		//$tp = Workspaces::find(1)->roles()->get();

		//$tp = Workspaces::find(1)->roles()->get()->toArray();
		//$tp = Tasks::getDependencies([4]);
		


		//$tp = Tasks::getDependencies(['tasks'=>[11443, 11449]], array(Tasks::getClass()));

		//$tp = Tasks::find(11498)->getDependency();



		//$tp = Chats::find(830)->setPerm();
		/*
		$theuser = Users::find($app->lincko->data['uid']);
		$theuser::setDebugMode(true);

		$tp = Tasks::find(13316);
		\libs\Watch::php( $tp->locked_by, '13316 $toVisible', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( json_decode($tp->toJson()), '13316 $toJson', __FILE__, __LINE__, false, false, true);
		*/
		//\libs\Watch::php( $tp->toVisible(), '13316 $toVisible', __FILE__, __LINE__, false, false, true);

		//$tp = Tasks::find(13316);
		//\libs\Watch::php( json_decode($tp->toJson()), '13316 $toJson', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( $tp->toVisible(), '13316 $toVisible', __FILE__, __LINE__, false, false, true);

		/*
		$tp = Comments::find(39211);
		\libs\Watch::php( $tp->toArray(), '39211 $toArray', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toJson(), '39211 $toJson', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toVisible(), '39211 $toVisible', __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(39215);
		\libs\Watch::php( $tp->toArray(), '39215 $toArray', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toJson(), '39215 $toJson', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toVisible(), '39215 $toVisible', __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(39538);
		\libs\Watch::php( $tp->toArray(), '39538 $toArray', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toJson(), '39538 $toJson', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp->toVisible(), '39538 $toVisible', __FILE__, __LINE__, false, false, true);
		*/
		/*
		$time = (new Tasks)->freshTimestamp();
		$time->second = $time->second - 10;

		Tasks::whereIn('id', [14509, 14512, 14516])->update(['locked_by' => $app->lincko->data['uid'],'locked_at' => $time]);
		*/
		//Data::unLockAll();
		/*
		$user_code = '120';
		\libs\Watch::php( $user_code, '$user_code', __FILE__, __LINE__, false, false, true);
		$tp = base64_encode($user_code);
		\libs\Watch::php( $tp, '$tp', __FILE__, __LINE__, false, false, true);
		$tp = Datassl::encrypt($user_code, 'invitation');
		\libs\Watch::php( $tp, '$tp', __FILE__, __LINE__, false, false, true);
		$tp = Datassl::encrypt_smp($user_code);
		*/

		//$tp = Projects::find(877);
		//$tp = Users::where('username_sha1', 'LIKE', '660609b17%')->first();

		//$tp = sha1('oN_U0wPB2VLXkYzTyAB_spjgOQfk');


		//$tp = Projects::find(1973)->clone();

/*
		//test2016122104@lincko.cafe
		$links = array();
		//$tp = Projects::find(324)->clone(false, array(), $links);
		$tp = Projects::find(2300)->clone(false, array(), $links);
		\libs\Watch::php( $links, '$links', __FILE__, __LINE__, false, false, true);
*/

		//$tp = Tasks::find(16616)->extraDecode();
		//$tp = Files::find(1034);
		//$tp = $tp->getPermissionMax();

		//$tp = Comments::find(46117)->clone(false, array(), $links);
		//\libs\Watch::php( $links, '$links', __FILE__, __LINE__, false, false, true);

		/*
		$file = file_get_contents('http://wx.qlogo.cn/mmopen/aTLrM3AwibiaSxjNL5aQHkv1eN524CATjrdHOVwwrFYPCtAV2vADeqeMqfeTdzIQevdUkTYUqefa3hZ0DFlOKPP3YZl2JqEuiba/0');
		file_put_contents('/tmp/toto', $file);
		$model = new Files;
		$model->name = 'Martin';
		$model->ori_type = mime_content_type('/tmp/toto');
		$model->tmp_name = '/tmp/toto';
		$model->error = 0;
		$model->size = filesize('/tmp/toto');
		$model->parent_type = 'users';
		$model->parent_id = 3;
		$model->save();

		$user = Users::getUser();
		$user->profile_pic = $model->id;
		$user->save();
		*/

		$arr = array();
		$arr['db_host'] = '/zPmx6DExSFBmbw7qQCoSG4X65L7280G95/vHfavjP0=';
		$arr['db_port'] = 'eH8AuNBOU7ZuuA5rikE4YXSJGIS+S9nfzWKQ0+1Wz/g=';
		$arr['db_pwd'] = 'DD1TbckBp95SwnXOZ6RM2A8wUz4qoaPexslr+tRO+k0=';
		$arr['sftp_host'] = '/zPmx6DExSFBmbw7qQCoSG4X65L7280G95/vHfavjP0=';
		$arr['sftp_port'] = 'hgZVk5W45Vwv/bb3qslLaOIQ2VoQA77ARg/H4RnnVqg=';
		$arr['sftp_pwd'] = 'Eg7b6perxEBs31Rj9Kq/s1GWrhssb6Zgnwxo8CoUqjc=';

		
		function encrypt_old($text){
			$salt = '7dujjbs&%9b=#nk!2v8v*1|1';
			return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $salt, $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
		}

		function decrypt_old($text){
			$salt = '7dujjbs&%9b=#nk!2v8v*1|1';
			return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $salt, base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
		}

		function encrypt_smp($text, $password = ''){
			$salt = '7dujjbs&%9b=#nk!2v8v*1|1';
			return trim(base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($salt.$password), $text, MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND))));
		}

		function decrypt_smp($text, $password = ''){
			$salt = '7dujjbs&%9b=#nk!2v8v*1|1';
			return trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($salt.$password), base64_decode($text), MCRYPT_MODE_ECB, mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND)));
		}

		foreach ($arr as $key => $value) {
			$decode = decrypt_old($value);
			\libs\Watch::php( $decode, '[old] '.$key, __FILE__, __LINE__, false, false, true);
			$encode = encrypt_smp($decode);
			\libs\Watch::php( $encode, '[old] '.$key, __FILE__, __LINE__, false, false, true);
		}

		//Display mysql requests
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() , 'QueryLog', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $tp, '$tp', __FILE__, __LINE__, false, false, true);


		/*
		//----------------------------------------
		//Add part_id for email login
		//	=> in UsersLog.php, must switch to primary "id" before being able to use "log"
		//	=> turn log into PRIMARY
		//	=> turn part-party_id into UNIQUE
		$all = Users::all();
		foreach ($all as $user) {
			$username_sha1 = $user->username_sha1;
			if($log = UsersLog::Where('username_sha1', $username_sha1)->where('party', null)->get()->first()){
				//\libs\Watch::php( $log->id, '$log', __FILE__, __LINE__, false, false, true);
				usleep(100000);
				$log->party_id = $user->email;
				$log->log = md5(uniqid());
				$log->save();
			}
		}
		*/

		/*
		//----------------------------------------
		//Modify the links
		set_time_limit(3600); //Set to 1 hour workload at the most

		\time_checkpoint('start notes');
		$items = Notes::withTrashed()->get(array('id', 'comment'));
		foreach ($items as $item) {
			$comment = $item->comment;
			if(preg_match_all("/<img.*?\/([=\d\w]+?)\/(thumbnail|link|download)\/(\d+)\/.*?>/ui", $comment, $matches)){
				foreach ($matches[0] as $key => $value) {
					$shaold = $matches[1][$key];
					$type = $matches[2][$key];
					$fileid = $matches[3][$key];
					usleep(10000);
					$shanew = base64_encode(Datassl::encrypt_smp(Files::withTrashed()->find($fileid)->link));
					$comment = str_replace("/$shaold/$type/$fileid/", "/$shanew/$type/$fileid/", $comment);
				}
				$item::withTrashed()->where('id', $item->id)->getQuery()->update(['comment' => $comment, 'extra' => null]);
				\libs\Watch::php( $item->toArray(), '$notes', __FILE__, __LINE__, false, false, true);
			}
		}
		\time_checkpoint('end notes');

		\time_checkpoint('start tasks');
		$items = Tasks::withTrashed()->get(array('id', 'comment'));
		foreach ($items as $item) {
			$comment = $item->comment;
			if(preg_match_all("/<img.*?\/([=\d\w]+?)\/(thumbnail|link|download)\/(\d+)\/.*?>/ui", $comment, $matches)){
				foreach ($matches[0] as $key => $value) {
					$shaold = $matches[1][$key];
					$type = $matches[2][$key];
					$fileid = $matches[3][$key];
					usleep(10000);
					$shanew = base64_encode(Datassl::encrypt_smp(Files::withTrashed()->find($fileid)->link));
					$comment = str_replace("/$shaold/$type/$fileid/", "/$shanew/$type/$fileid/", $comment);
				}
				$item::withTrashed()->where('id', $item->id)->getQuery()->update(['comment' => $comment, 'extra' => null]);
				\libs\Watch::php( $item->toArray(), '$tasks', __FILE__, __LINE__, false, false, true);
			}
		}
		\time_checkpoint('end tasks');	
		*/

		
		/*
		//----------------------------------------
		//The permission purge
		if(function_exists('proc_nice')){proc_nice(30);}
		set_time_limit(24*3600); //Set to 1 day workload at the most
		\time_checkpoint('start Comments to Messages');
		//First copy/paste Comments_Chats to Messages_Chats
		$sql = 'SELECT
		temp_id, created_at, updated_at, created_by, recalled_by, noticed_by, viewed_by, parent_id, comment
		FROM `comments` WHERE `parent_type` LIKE "chats" ORDER BY `comments`.`id` ASC;';
		if($data = $db->select( $db->raw($sql) )){
			foreach ($data as $item) {
				$model = new Messages;
				foreach ($item as $key => $value) {
					$model->$key = $value;
				}
				$model->brutSave();
			}
		}
		\time_checkpoint('start clean _perm');
		//Reinitialize all permissions
		$models = Data::getModels();
		$time = (new Users)->freshTimestamp();
		foreach ($models as $table => $class) {
			$bindings = false;
			if((is_bool($class::getHasPerm()) && $class::getHasPerm()) || in_array('_perm', $class::getColumns())){
				$bindings = array(
					'updated_at' => $time,
					'_perm' => ''
				);
			}
			//Force to recalculate all extra
			if(in_array('extra', $class::getColumns())){
				$bindings['extra'] = null;
			}
			if($bindings){
				$class::getQuery()->update($bindings); //Dangerous
			}
		}
		\time_checkpoint('start permission');
		$count = array();
		foreach ($models as $table => $class) {
			$count[$table] = 0;
			$all = $class::withTrashed()->get();
			foreach ($all as $model) {
				if(isset($model->_perm) && empty($model->_perm)){ //To run this, make sure that all _perm are empty first
				//if(isset($model->_perm)){ //Force each row, but very slow
					$model->setPerm();
					$count[$table]++;
				}
			}
			\time_checkpoint($table.' => '.$count[$table]);
		}
		\time_checkpoint('end');
		\libs\Watch::php( $count, '$count', __FILE__, __LINE__, false, false, true);
		if(function_exists('proc_nice')){proc_nice(0);}
		*/
		
		
		/*
		//NOT USED
		//The permission purge
		\time_checkpoint('start');
		Tree::unlock(true, 'eI782Ph0sp');
		$count = array();
		$models = Data::getModels();
		foreach ($models as $table => $class) {
			$count[$table] = 0;
			$suffix = $class::getPivotUsersSuffix();
			$all = $class::withTrashed()->get();
			foreach ($all as $model) {
				Tree::TreeUpdateOrCreate($model, array(), true);
				$count[$table]++;
			}
			try {
				if($pivot = (new PivotUsers(array($table)))->withTrashed()->where('access', 0)->get(['users_id', $table.$suffix, 'access'])){
					foreach ($pivot as $model) {
						if($item = $class::find($model->{$table.$suffix})){
							$users_id = $model->users_id;
							$fields = array(
								'access' => 0,
							);
							Tree::TreeUpdateOrCreate($item, array($users_id), false, $fields);
						}
					}
				}
			} catch (\Exception $e) {
				\libs\Watch::php( $e->getFile()."\n".$e->getLine()."\n".$e->getMessage(), $table, __FILE__, __LINE__, false, false, true);
			}
			

			\time_checkpoint($table.' => '.$count[$table]);
		}
		\time_checkpoint('end');
		\libs\Watch::php( $count, '$count', __FILE__, __LINE__, false, false, true);
		*/
		

		//----------------------------------------
	
		//Add new project
		/*
		$tp = new Projects();
		$tp->description = "Something to following";
		$tp->title = 'toto '.rand();
		$tp->parent_id = $app->lincko->data['workspace_id'];
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
		$tp->parent_id = 5;
		$tp->save();
		*/

		//Add new task (with missing argument)
		/*
		$tp = new Tasks();
		$tp->comment = "Something to do quickly";
		//$tp->title = 'B';
		$tp->parent_id = 5;
		$tp->save();
		*/

		//Move a task to another project
		/*
		$tp = Tasks::find(4);
		if($tp->parent_id==8){
			$tp->parent_id = 5;
		} else {
			$tp->parent_id = 8;
		}
		$tp->save();
		*/
		$app->render(200, array('msg' => $msg,));
		return true;
	}

	public function user_get(){
		$app = $this->app;
		if($user = Users::all()){	
			\libs\Watch::php($user->toJson(),'$user',__FILE__, __LINE__);
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

	public function crud_get(){
		$app = $this->app;
		$tp = null;

		function resultTest($tp){
			//return $tp->checkAccess(false); //Test Access
			//return $tp->checkPermissionAllow(0); //Test Read
			//return $tp->checkPermissionAllow(1); //Test Create
			//return $tp->checkPermissionAllow(2); //Test Update
			//return $tp->checkPermissionAllow(3); //Test Delete
			return $tp->checkPermissionAllow(4); //Test Error
		}

		$theuser = Users::find($app->lincko->data['uid']);
		$theuser::setDebugMode(true);

		\libs\Watch::php( $app->lincko->data['workspace'], 'workspace', __FILE__, __LINE__, false, false, true);
		\libs\Watch::php( $app->lincko->data['workspace_id'], 'workspace_id', __FILE__, __LINE__, false, false, true);

		$tp = new Users;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Users::find(3);
		\libs\Watch::php( resultTest($tp), 'Self '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Users::find(5);
		\libs\Watch::php( resultTest($tp), 'Direct '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Users::find(6);
		\libs\Watch::php( resultTest($tp), 'Hidden '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Roles;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Roles::find(2);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Roles::find(4);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Roles::find(7);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Workspaces;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Workspaces::find(1);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Workspaces::find(2);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Projects;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Projects::find(3);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Projects::find(13);
		\libs\Watch::php( resultTest($tp), 'W0 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Projects::find(59);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Projects::find(61);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Tasks;
		$tp->parent_id=3;
		$tp->setParentAttributes();
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Tasks::find(3);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Tasks::find(6);
		\libs\Watch::php( resultTest($tp), 'W0 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Tasks::find(15);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Tasks::find(18);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Notes;
		$tp->parent_id=3;
		$tp->setParentAttributes();
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Notes::find(13);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Notes::find(16);
		\libs\Watch::php( resultTest($tp), 'W0 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Notes::find(19);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Notes::find(22);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Chats;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Chats::find(28);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Chats::find(42);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Chats::find(13);
		\libs\Watch::php( resultTest($tp), 'W0 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Chats::find(24);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Chats::find(25);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		$tp = new Comments;
		\libs\Watch::php( resultTest($tp), 'new '.$tp->getTable(), __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(87);
		\libs\Watch::php( resultTest($tp), 'Users5 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(6);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(28);
		\libs\Watch::php( resultTest($tp), 'Shared '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(1);
		\libs\Watch::php( resultTest($tp), 'W0 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(7);
		\libs\Watch::php( resultTest($tp), 'W1 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);
		$tp = Comments::find(90);
		\libs\Watch::php( resultTest($tp), 'W2 '.$tp->getTable().':'.$tp->id, __FILE__, __LINE__, false, false, true);

		return true;
	}

	// Test role rules
	// Must log as an adminsitrator in perso workspace (invite at least a manager and a viewer)
	public function role_get(){
		$app = $this->app;
		Capsule::connection('data')->enableQueryLog();
		$theuser = Users::find($app->lincko->data['uid']);
		$theuser::setDebugMode(true);

		$models = new \stdClass;
		$models->workspaces = Workspaces::whereId($app->lincko->data['workspace_id'])->where('created_by', $app->lincko->data['uid'])->first();
		$models->roles = (new Roles)->getLinked()->where('shared', 0)->where('created_by', $app->lincko->data['uid'])->where('parent_id', $app->lincko->data['workspace_id'])->first();
		$models->chats = $theuser->chats()->where('created_by', $app->lincko->data['uid'])->first();
		$models->comments = $models->chats->comments()->where('created_by', $app->lincko->data['uid'])->first();
		$models->projects = $theuser->projects()->where('created_by', $app->lincko->data['uid'])->first();
		$models->tasks = $models->projects->tasks()->where('created_by', $app->lincko->data['uid'])->first();

		//Clean if some model doesn't exists
		foreach ($models as $key => $model) {
			if(!$model){
				unset($models->$key);
			}
		}

		$pivots = (new PivotUsersRoles)->sameWorkspace()->get();

		$users = array();
		foreach ($pivots as $pivot) {
			//Allow only to work with standard roles for debugging purpose, their ID must be 1, 2, 3
			$roles_id = intval($pivot->roles_id);
			if($roles_id>=1 && $roles_id<=3){
				$users[$roles_id] = $pivot->users_id;
				foreach ($models as $model) {
					if($model){
						$model->setUserPivotValue($pivot->users_id, 'access', 1, false);
						if($model->getTable() != 'workspaces'){
							$model->setRolePivotValue($pivot->users_id, null, null, false);
						}
					}
				}
				//Role initialization for Workspaces
				if($pivot->users_id != $app->lincko->data['uid']){
					//Be care full manager must be 2, and viewer must be 3
					$role_id = 2;
					if(Users::find($pivot->users_id)->username == 'viewer'){ $role_id = 3; }
					$models->workspaces->setRolePivotValue($pivot->users_id, $role_id, null, false);
				}
			}
		}

		//Set the outsider user, he should be rejected by everything
		$users[0] = Users::whereHas('workspaces', function ($query){
			$app = \Slim\Slim::getInstance();
			$query->where('parent_id', $app->lincko->data['workspace_id'])->where('access', 1);
		}, '<', 1)->first()->id;
		

		ksort($users);

		/*
		Roles
		[
			0 :owner	=> additional feature if you are the owner
			1 :outsider (don't share anything)
			2 :super (share same workspace, same chat room)
			2 :max allow (share same workspace, same chat room)
		]
		*/
		$accept = array(
			'workspaces' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 0 , 0 ), //owner
				1	=> array( 0 , 1 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 1 , 0 ), //super
				3	=> array( 1 , 1 , 0 , 0 ), //max allow
			),
			'roles' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 0 , 0 ), //owner
				1	=> array( 0 , 0 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 1 , 1 ), //v
				3	=> array( 1 , 0 , 0 , 0 ), //max allow
			),
			'chats' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 1 , 0 ), //owner
				1	=> array( 0 , 1 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 0 , 0 ), //super
				3	=> array( 1 , 1 , 0 , 0 ), //max allow
			),
			'projects' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 0 , 0 ), //owner
				1	=> array( 0 , 0 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 1 , 1 ), //super
				3	=> array( 1 , 0 , 0 , 0 ), //max allow
			),
			'tasks' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 1 , 0 ), //owner
				1	=> array( 0 , 0 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 1 , 1 ), //super
				3	=> array( 1 , 1 , 1 , 0 ), //max allow
			),
			'comments' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 0 , 0 ), //owner
				1	=> array( 0 , 0 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 1 , 0 ), //super
				3	=> array( 1 , 1 , 0 , 0 ), //max allow
			),
			'users' => array( //[ read , create , edit , delete ]
				0	=> array( 1 , 0 , 1 , 0 ), //owner
				1	=> array( 0 , 1 , 0 , 0 ), //outsider
				2	=> array( 1 , 1 , 0 , 0 ), //super
				3	=> array( 1 , 1 , 0 , 0 ), //max allow
			),
		);
		
		//Role 1 is also the owner, so we regroup owner+administrator
		foreach ($accept as $i => $tab_i) {
			foreach ($accept[$i][1] as $j => $tab_j) {
				$accept[$i][1][$j] = intval($accept[$i][-1][$j] || $accept[$i][1][$j]);
			}
		}

		\libs\Watch::php( '!!!!!!!!!! START !!!!!!!!!!' , 'Permissions', __FILE__, __LINE__, false, false, true);

		foreach ($users as $role_id => $users_id) {
			$app->lincko->data['uid'] = $users_id;

			foreach ($accept as $key => $table) {	
				if(!isset($models->{$key})){
					continue;
				}
				$model = $models->{$key};
				$new_model = $model->newinstance();

				if($key == 'workspaces'){
					$new_model->name = $model->name = '_Workspace '.rand();
					$model->allowWorkspaceCreation();
					$new_model->allowWorkspaceCreation();
				} else if($key == 'roles'){
					$new_model->name = $model->name = '_Role '.rand();
				}  else if($key == 'chats'){
					$new_model->title = $model->title = '_Chat '.rand();
				} else if($key == 'projects'){
					$new_model->title = $model->title = '_Project '.rand();
				} else if($key == 'tasks'){
					$new_model->parent_id = $model->parent_id;
					$new_model->title = $model->title = '_Task '.rand();
				} else if($key == 'comments'){
					$new_model->parent_type = 'chats';
					$new_model->parent_id = $model->chats->getKey();
					$new_model->comment = $model->comment = '_Comment '.rand();
				}

				//Reinitialize variables
				$model->checkUser();
				$new_model->checkUser();

				//Read
				if( $a=intval($model->checkAccess()) xor $b=intval($accept[ $key ][ $role_id ][ 0 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkPermissionAllow('read'));
						\libs\Watch::php( $a.'...BUG READ...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->access() => User: '.$users_id.' | role: '.$role_id, __FILE__, __LINE__, false, false, true);
					}
				}

				//Edit
				if( $a=intval($model->save()) xor $b=intval($accept[ $key ][ $role_id ][ 1 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkPermissionAllow('edit'));
						\libs\Watch::php( $a.'...BUG EDIT...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->edit() => User: '.$users_id.' | role: '.$role_id, __FILE__, __LINE__, false, false, true);
					}
				}

				//Delete
				if( $a=intval($model->delete()) xor $b=intval($accept[ $key ][ $role_id ][ 2 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($model->checkAccess());
						$d = intval($model->checkPermissionAllow('delete'));
						\libs\Watch::php( $a.'...BUG DELETE...'.$b.': '.$c.'|'.$d , '['.$model->getKey().'] '.$model->getTable().'->delete() => User: '.$users_id.' | role: '.$role_id, __FILE__, __LINE__, false, false, true);
					}
				}

				//Creation
				if( $a=intval($new_model->save()) xor $b=intval($accept[ $key ][ $role_id ][ 3 ]) ){
					if(!($b==-1 && (!isset($model->created_by) || (isset($model->created_by) && $model->created_by != $app->lincko->data['uid'])))){
						$c = intval($new_model->checkAccess());
						$d = intval($new_model->checkPermissionAllow('edit'));
						\libs\Watch::php( $a.'...BUG CREATION...'.$b.': '.$c.'|'.$d , '[*] '.$new_model->getTable().'->create() => User: '.$users_id.' | role: '.$role_id, __FILE__, __LINE__, false, false, true);
					}
				}

			}

		}

		\libs\Watch::php( '!!!!!!!!!!  END  !!!!!!!!!!' , 'Permissions', __FILE__, __LINE__, false, false, true);
		//\libs\Watch::php( Capsule::connection('data')->getQueryLog() , 'QueryLog', __FILE__, __LINE__, false, false, true);
		$json = new Json('OK', false);
		$json->render(200);
		return true;
	}

}
