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
		if(is_file($_SERVER["DOCUMENT_ROOT"].$file)){
			return $file.'?'.filemtime($_SERVER["DOCUMENT_ROOT"].$file);
		} else {
			return $file.'?'.time();
		}
	}

}
