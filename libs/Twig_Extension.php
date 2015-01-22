<?php

//http://twig.sensiolabs.org/doc/advanced.html#id2

namespace libs;

class Twig_Extension extends \Slim\Views\TwigExtension {

	public function getName(){
		return 'Lincko';
	}

	public function get_Debug($data = NULL){
		$app = \Slim\Slim::getInstance();
		include($app->lincko->path.'/error/debug.php');
		return NULL;
	}

	public function get_Filelatest($file){
		if(is_file($_SERVER["DOCUMENT_ROOT"].$file)){
			return $file.'?'.filemtime($_SERVER["DOCUMENT_ROOT"].$file);
		} else {
			return $file.'?'.time();
		}
	}

	//Get the translation
	public function get_Trans($bundle, $category, $phrase, $type = NULL, array $data = array()){
		$app = \Slim\Slim::getInstance();
		if(mb_strtolower($type)=='input'){
			return $app->trans->getINPUT($bundle, $category, $phrase, $data);
		} else if(mb_strtolower($type)=='textarea'){
			return $app->trans->getTEXTAREA($bundle, $category, $phrase, $data);
		} else if(mb_strtolower($type)=='js'){
			return $app->trans->getJS($bundle, $category, $phrase, $data);
		} else if(mb_strtolower($type)=='html'){
			return $app->trans->getHTML($bundle, $category, $phrase, $data);
		} else if(mb_strtolower($type)=='json'){
			return $app->trans->getJSON($bundle, $category, $phrase, $data);
		} else if(mb_strtolower($type)=='brut'){
			return $app->trans->getBRUT($bundle, $category, $phrase, $data);
		}
		return $app->trans->getHTML($bundle, $category, $phrase, $data);
	}

	public function get_Languages($bundle = NULL){
		$app = \Slim\Slim::getInstance();
		return $app->trans->getLanguages($bundle);
	}

	public function get_Language(){
		$app = \Slim\Slim::getInstance();
		return $app->trans->getClientLanguage();
	}

	public function get_Route(){
		$app = \Slim\Slim::getInstance();
		return $app->request->getPath();
	}

	public function get_TranslationUri(){
		$app = \Slim\Slim::getInstance();
		return '/language'.$app->request->getPath();
	}

	public function getFunctions(){
		return array_merge(
			parent::getFunctions(),
			array(
				new \Twig_SimpleFunction('_debug', array($this, 'get_Debug'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_trans', array($this, 'get_Trans'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_filelatest', array($this, 'get_Filelatest'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_languages', array($this, 'get_Languages'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_language', array($this, 'get_Language'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_route', array($this, 'get_Route'), array('is_safe' => array('html'))),
				new \Twig_SimpleFunction('_language_uri', array($this, 'get_TranslationUri'), array('is_safe' => array('html'))),
			)
		);
	}

}
