<?php


namespace bundles\lincko\api\models\libs;

use Illuminate\Database\Eloquent\Model;
use \bundles\lincko\api\models\libs\Data;
use \bundles\lincko\api\models\libs\ModelLincko;
use \bundles\lincko\api\models\data\Users;

class Action extends Model {

	protected $connection = 'default'; //Keep all records on Lincko server

	protected $table = 'action';
	protected $morphClass = 'action';

	protected $primaryKey = 'id';

	public $timestamps = false;

	protected $visible = array();

	protected static $convert_models = false;
	protected static $convert = array(
		-1 => 'Logged',
		-2 => 'Start onboarding',
		-3 => 'Next onborading step',
		-4 => 'Finish onboarding',
		-5 => 'Skip onboarding',
		-6 => 'Invite by email',
		-7 => 'Invite by internal scan',
		-8 => 'Invite by external scan / paste url',
		-9 => 'Accept invitation by url code',
		-10 => 'Accept invitation',
		-11 => 'Reject invitation',
	);
	
////////////////////////////////////////////

	//Add these functions to insure that nobody can make them disappear
	public function delete(){ return false; }
	public function restore(){ return false; }

	public function save(array $options = array()){
		if(isset($this->id)){
			//Only allow creation
			return false;
		}
		$return = parent::save($options);
		usleep(30000); //30ms
		return $return;
	}

	public static function record($action, $info=null){
		if(!is_numeric($action)){
			return false;
		}
		$user = Users::getUser();
		$created_at = $user->created_at->getTimestamp();
		$app = ModelLincko::getApp();
		$item = new Action;
		$item->users_id = $app->lincko->data['uid'];
		$item->created_at = time();
		$item->action = intval($action);
		if(!is_null($info)){
			$item->info = $info;
		}
		return $item->save();
	}

	public static function action(int $action, $username=' '){
		if(!self::$convert_models){
			$app = ModelLincko::getApp();
			self::$convert_models = true;
			if($models = Data::getModels()){
				foreach ($models as $table => $class) {
					$archive = $class::getArchive();
					if(!empty($archive)){
						foreach ($archive as $code) {
							if($content = $app->trans->getBRUT('data', 1, $code[1], array(), 'en')){
								self::$convert[intval($code[1])] = str_replace('[{un}]', $username, $content);
							}
						}
					}
				}
			}

		}
		if(isset(self::$convert[$action])){
			$result = self::$convert[$action];
		} else {
			$result = '('.$action.') Unknown';
		}
		return $result;
	}

}
