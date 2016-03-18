<?php
//你好 Léo & Luka

namespace libs;

use Illuminate\Database\Eloquent\Model;

class Email extends \PHPMailer {

	protected $param = NULL;

	//Construct and setup basic parameters
	public function __construct(){
		parent::__construct(true); //"true" will throw exceptions on errors
		$app = \Slim\Slim::getInstance();
		$param = $this->param = $app->lincko->email;
		$this->IsSendmail(true);
		$this->CharSet = $param->CharSet;
		$this->Abuse = $param->Abuse;
		$this->Sender = $param->Sender;
		$this->From = $param->From;
		$this->FromName = $param->FromName;
		$this->Timeout = 6;
		$this->IsHTML(true);
	}

	//Convert special HTML entities back to characters, email subject does not recognize special HTML entities
	public function setSubject($subject, $decode=true){
		if($decode){
			$this->Subject = htmlspecialchars_decode($subject);
		} else {
			$this->Subject = $subject;
		}
	}

	//Keep a record of the Mail object to be sent later after rendering
	public function sendLater(){
		$param = $this->param;
		$param->List[] = $this;
		return true;
	}

}