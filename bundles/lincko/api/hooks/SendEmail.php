<?php
//你好 Léo & Luka

namespace bundles\lincko\api\hooks;

use \bundles\lincko\api\models\Onboarding;

//Special functions to manage errors
function SendEmail(){
	$app = \Slim\Slim::getInstance();
	$List = $app->lincko->email->List;
	foreach($List as $i => $email){
		return $email->send();
	}
}
