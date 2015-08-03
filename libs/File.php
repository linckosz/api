<?php

namespace libs;

class File {

	protected $file = false;

	public function __construct($file = false){
		return $this->file = $file;
	}

	public function getFile(){
		return $this->file;
	}

	static function getLocalFile($file){
		if(is_file($_SERVER["DOCUMENT_ROOT"].$file)){
			return $_SERVER["DOCUMENT_ROOT"].$file;
		}
		return false;
	}

	static function getLatest($file){
		//For static files, we do no need to use a subdomain to disable the cache because those files will be loaded from browser cache (code 200) directly,
		//they don't need to ask the server (slower respond time, code 304), use a subdomain through ajax always return a 304 code.
		if(is_file($_SERVER["DOCUMENT_ROOT"].$file)){
			return $file.'?'.filemtime($_SERVER["DOCUMENT_ROOT"].$file);
		} else {
			return $file.'?'.time();
		}
	}

}
