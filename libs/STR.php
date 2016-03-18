<?php
//你好 Léo & Luka

namespace libs;

class Ord_table {
	// Hold an instance of the class
	private static $instance;

	private static $table = array();
 
	// The singleton method
	public static function singleton() {
		if (!isset(self::$instance)) {
			self::$instance = self::create_table();
		}
		return self::$instance;
	}

	private static function create_table() {
		static $tab = array();
		$entities = get_html_translation_table(HTML_SPECIALCHARS, ENT_HTML5 | ENT_QUOTES, 'UTF-8');
		foreach( $entities as $k => $v ){
			$tab[$k] = '&#' . ord($k) . ';';
		}
		return $tab;
	}
	
}

class STR {

	//Convert a text to HTML entities, readable by HTML, INPUT, TEXTAREA
	public static function sql_to_html($text) {
		//Cannot use htmlspecialchars because Android 2.3 does not recognizes "named entity" ($quote;), but only "numerical entity" ($#39;)
		//$text = htmlspecialchars($text, ENT_HTML5 | ENT_QUOTES);
		$text = self::name_to_numerical($text);
		$text = nl2br($text);
		$text = self::break_line_conv($text,'');
		return $text;
	}

	//Convert a text to JS entities, readable by JS
	//Note, use quotes "..." around the JS variable while displaying
	public static function sql_to_js($text) {
		$text = json_encode($text);
		$text = str_replace("\\r\\n", "\\n", $text);
		//Cancel the quote " added by json_encode
		$text = preg_replace("/^\"|\"$/u", '', $text);
		return $text;
	}

	//Delete any line return
	public static function break_line_conv($text, $replace) {
		return str_replace(array("\r\n", "\r", "\n", CHR(10), CHR(13)), $replace, $text); 
	}


	private static function name_to_numerical($string) {
		$tab = Ord_table::singleton();
		return strtr($string, $tab);
	}

	//Convert "any_SHORT description " to "AnyShortDescription"
	public static function textToFirstUC($text){
		$text = str_replace('_', ' ', $text);
		$text = ucwords(strtolower($text));
		$text = str_replace(' ', '', $text);
		return $text;
	}




















	//Encode in HTML text recordable in the DB without loosing information or symbole conflict
	//http://www.ascii.cl/htmlcodes.htm?
	public static function texttosql_old($text){
		$text = htmlspecialchars($text, ENT_QUOTES);
		$text = str_replace("\\", "&#92;", $text);
		return $text;
	}

	//Decode in exact text
	public static function sqltotext_old($text){
		$text = htmlspecialchars_decode($text, ENT_QUOTES);
		$text = str_replace("&#92;", "\\", $text);
		return $text;
	}

	//Format the text to be well displayed into an email
	public static function texttoemail_old($text){
		$text = texttosql($text);
		$text = str_replace(" ", "&nbsp;", $text);
		return $text;
	}

	//Decode in exact text
	public static function sqltodownload_old($text){
		$text = str_replace("&#039;", "-", $text);
		$text = str_replace("&quot;", "-", $text);
		$text = str_replace("&amp;", "-", $text);
		$text = str_replace("&lt;", "-", $text);
		$text = str_replace("&gt;", "-", $text);
		$text = str_replace("/", "-", $text);
		$text = str_replace("&#92;", "-", $text);
		$text = str_replace(";", "-", $text);
		$text = str_replace("$", "-", $text);
		$text = str_replace(":", "-", $text);
		$text = str_replace("*", "-", $text);
		$text = str_replace("?", "-", $text);
		$text = str_replace("|", "-", $text);
		return $text;
	}

	//Format the text to be used in JS without havin any trouble with for example </script>
	//IMPORTANT: The variable must be between ".", if not it does not work
	public static function sqltojs_old($text){
		$text = sqltotext($text);
		$text = addslashes($text);
		$text = str_replace('<', '"+"<"+"', $text);
		$text = str_replace('<', '&lt;', $text);
		$text = str_replace('>', '"+">"+"', $text);
		$text = str_replace('>', '&gt;', $text);
		$text = str_replace('/', '"+"/"+"', $text);
		return $text;
	}

	//Format the text to be used in JS without havin any trouble with for example </script>
	//IMPORTANT: The variable must be between ".", if not it does not work
	//Simplify JS converter used in templates.php variable
	public static function sqltojsSimple_old($text){
		$text = sqltotext($text);
		$text = addslashes($text);
		$text = str_replace('<', '"+"<"+"', $text);
		$text = str_replace('>', '"+">"+"', $text);
		$text = str_replace('/', '"+"/"+"', $text);
		return $text;
	}

	public static function sqltojsAlert_old($text){
		$text = sqltotext($text);
		$retchar = array( CHR(10) => " " );
		$text = strtr($text,$retchar);
		return $text;
	}

	//Return a Unix date if the fiel IPTC has been filled
	public static function UnixIPTCDdate_old($filetp){
		$unixDate = false;
		if(is_file($filetp) && filesize($filetp)>=12){
			$size = getimagesize($filetp, $info);
			if (!empty($filetp) && is_file($filetp) && $size[2]==2 && isset($info['APP13'])){ //$size[2]==2 is JPEG
				if($iptc = iptcparse($info['APP13'])){
					if(isset($iptc['2#055'][0]) && isset($iptc['2#060'][0])){
						$YMD = $iptc['2#055'][0];
						$HMS = $iptc['2#060'][0];
						$unixDate = mktime(mb_substr($HMS,0,2),mb_substr($HMS,2,2),mb_substr($HMS,4,2),mb_substr($YMD,4,2),mb_substr($YMD,6,2),mb_substr($YMD,0,4));
					}
				}
			}
		}
		return $unixDate;
	}

	//Return UNIX timestamp date
	public static function convertDate_old($filetp){
		if(is_file($filetp) && filesize($filetp)>=12){
			$size = getimagesize($filetp, $info);
			if($unixIPTC=UnixIPTCDdate($filetp)){
				return $unixIPTC;
			}	else if($size[2]==2 && $exif=@exif_read_data($filetp)){
				if (isset($exif['EXIF']['DateTimeOriginal'])){
					$tpdate = $exif['EXIF']['DateTimeOriginal'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTimeOriginal'])){
					$tpdate = $exif['DateTimeOriginal'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['EXIF']['DateTimeDigitized'])){
					$tpdate = $exif['EXIF']['DateTimeDigitized'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTimeDigitized'])){
					$tpdate = $exif['DateTimeDigitized'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['IFD0']['DateTime'])){
					$tpdate = $exif['IFD0']['DateTime'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				} else if (isset($exif['DateTime'])){
					$tpdate = $exif['DateTime'];
					$tpdate = mktime(mb_substr($tpdate,11,2),mb_substr($tpdate,14,2),mb_substr($tpdate,17,2),mb_substr($tpdate,5,2),mb_substr($tpdate,8,2),mb_substr($tpdate,0,4));
					return $tpdate;
				}	else if (filectime($filetp)) { //File creation date
					return filectime($filetp);
				}	else if (isset($exif['FILE']['FileDateTime'])){
					return $exif['FILE']['FileDateTime']; //File modification date
				}	else if (isset($exif['FileDateTime'])){
					return $exif['FileDateTime']; //File modification date
				} else {
					return time();
				}
			} else if (filectime($filetp)) {
				return filectime($filetp);
			} else {
				return time();
			}
		} else {
			return time();
		}
	}

	//Return a date array of YYYYMMDD + HHMMSS
	public static function convertDateIPTC_old($filetp){
		$IPTCtab = array();
		$dateU = convertDate($filetp);
		$IPTCtab['2#055'] = date('Ymd',$dateU); //YYYYMMDD
		$IPTCtab['2#060'] = date('His',$dateU); //HHMMSS
		return $IPTCtab;
	}

	//Because Windows limitation for folder name lengh, limit the file size to 120 characters max (actually 144 still ok)
	public static function NameToAscii_old($string){
		$string = espace($string);
		$extension = mb_strrchr($string,'.');
		$string=mb_substr($string, 0, (mb_strlen($string)-mb_strlen($extension)));
		$string = mb_encode_numericentity($string, array (0x0, 0xffff, 0, 0xffff), 'UTF-8');
		$ascii = "";
		$alphabet=0; //Tell if we converted in ascii
		for ($i = 0; $i < mb_strlen($string); $i++){
			$temp="";
			if($string[$i]=="&"){
				while($string[$i]!=";"){
					if($string[$i]!="&" && $string[$i]!="#" && $string[$i]!=";") {
						$temp=$temp.$string[$i];
					}
					$i++;
				}
			}
			$tp = mb_decode_numericentity("&#".$temp.";", array (0x0, 0xffff, 0, 0xffff), 'UTF-8');
			if (preg_match("/[a-z0-9]/ui",$tp)){
				//If we previously converted in ascii (or the begining of the string), we add "-" to indicate that all next characters are just alphanumrics without encoding
				if($alphabet==0) {$ascii = $ascii."-";}
				if(mb_strlen($ascii)<115) { $ascii = $ascii.$tp; } else { $i = mb_strlen($string); }
				$alphabet=1;
			} else {
				if(mb_strlen($ascii)<115) { $ascii = $ascii."_".$temp; } else { $i = mb_strlen($string); }
				$alphabet=0;
			}
		}
		return "~".$ascii.$extension;
	}

	public static function AsciiToName_old($string){
		if(mb_strpos($string,"~")!==false && mb_strpos($string,".")>mb_strpos($string,"~")){
			$string = mb_substr(mb_strstr($string, '~'), 1); //Get all the text behind "~" which significate the begining of the string
			$extension = mb_strrchr($string,'.');
			$extension = mb_substr($extension,1); //Help to keep the "dot" to know what is the end of the string
			$string=mb_substr($string, 0, (mb_strlen($string)-mb_strlen($extension)));
			$nom="";
			for ($i = 0; $i < mb_strlen($string); $i++){
				if($string[$i]=="-"){
					//Do nothing because it's the begining of a normal string
				} else if ($string[$i]=="_"){
					$i++;
					$tp="";
					while($string[$i]!="_" && $string[$i]!="-" && $string[$i]!="."){
						$tp=$tp.$string[$i];
						$i++;
					}
					$tp = mb_decode_numericentity("&#".$tp.";", array (0x0, 0xffff, 0, 0xffff), 'UTF-8');
					$nom=$nom.$tp;
					$i--;
				} else if ($string[$i]=="."){
					//End of the string, we force out
					$nom=$nom.$string[$i];
					$i=mb_strlen($string);
				} else {
					//In the case of a normal character
					$nom=$nom.$string[$i];
				}
			}
			return($nom.$extension);
		} else {
			return "unknown";
		}
	}

	public static function NameToFolder_old($string){
		$string = str_replace('.', 'p', $string);
		$string = NameToAscii($string);
		$string = str_replace('~', '', $string);
		return $string;
	}

	//Enable to display the file size with a round value
	public static function taille_old($fichier){
		$taille=filesize($fichier);
		$taille=tailletxt($taille);
		return $taille;
	}

	public static function tailletxt_old($taille){
		if ($taille >= 1073741824) 
		{$taille = round($taille / 1073741824 * 100) / 100; $taille = round($taille,2) . " GB";}
		else if ($taille >= 104857600) 
		{$taille = round($taille / 1048576 * 100) / 100; $taille = round($taille,0) . " MB";}
		else if ($taille >= 10485760) 
		{$taille = round($taille / 1048576 * 100) / 100; $taille = round($taille,1) . " MB";}
		else if ($taille >= 1048576) 
		{$taille = round($taille / 1048576 * 100) / 100; $taille = round($taille,2) . " MB";}
		else if ($taille >= 1024) 
		{$taille = round($taille / 1024 * 100) / 100; $taille = round($taille,0) . " KB";}
		else if ($taille > 0) 
		{$taille = $taille . " B";} 
		else
		{$taille="-";}
		
		return $taille;
	}


	public static function espace_old($text){
		//Delte space at the begining, at the end, and any double space
		$text = trim($text);
		$text = str_replace("  ", " ", $text);
		if($text!=""){
			While(substr_compare($text, " ", 0, 1)==0){
				$text = substr_replace($text, "", 0, 1);
			}
		}
		if($text!=""){
			While(substr_compare($text, " ", -1, 1)==0){
				$text = substr_replace($text, "", -1, 1);
			}
		}
		return $text;
	}

	public static function cryptage_old($string,$code){
		if($code>0) {$code=$code;} else {$code=1;}
		$string = mb_encode_numericentity($string, array (0x0, 0xffff, 0, 0xffff), 'UTF-8');
		$ascii = "";
		$j = 0;
		for ($i = 0; $i < mb_strlen($string); $i++){
			$temp="";
			if($string[$i]=="&"){
				while($string[$i]!=";"){
					if($string[$i]!="&" && $string[$i]!="#" && $string[$i]!=";") {
						$temp=$temp.$string[$i];
					}
					$i++;
				}
			}
			$temp = $temp + $code + $j;
			$ascii = $ascii."_".$temp;
			$j++;
		}
		return($ascii);
	}

	//Add bcrypt hashage
	public static function cryptageB_old($string,$code){
		$bcrypt = new Bcrypt(7);
		return $bcrypt->hash(cryptage($string,$code));
	}

	public static function decryptage_old($string,$code){
		if($code>0) {$code=$code;} else {$code=1;}
		$nom="";
		$string=$string."."; //The dot helps to know if it's the end of teh string
		$j = 0;
		for ($i = 0; $i < mb_strlen($string); $i++){
			if ($string[$i]=="_"){
				$i++;
				$tp="";
				while($string[$i]!="_" && $string[$i]!="."){
					$tp=$tp.$string[$i];
					$i++;
				}
				$tp = $tp - $code - $j;
				$j++;
				if($tp<0) {$tp=0;}
				$tp = mb_decode_numericentity("&#".$tp.";", array (0x0, 0xffff, 0, 0xffff), 'UTF-8');
				$nom=$nom.$tp;
				$i--;
			}
		}
		return($nom);
	}

	//Check if the value is pair or odd, return true if yes
	public static function Odd_old($value) {
	   return ($value & 1)==1; //=1 if odd
	}

	//Get an hazard alphanumeric mix of lengh X
	public static function genRandomString_old($x) {
		$characters = '123456789abcdefghijklmnopqrstuvwxyz';
		$string=NULL;
		for($p=0;$p<$x;$p++) {
			$string.=$characters[mt_rand(0,mb_strlen($characters)-1)];
		}
		return "".$string; //To be sure it will return a string
	}

	//Idem as genRandomString($x) but we can play with words instead of alphanumerics
	//Generate a hazard word, is used to avoid hacking
	public static function mot_aleatoire_old($taille) {
		$alpha = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
		$code="";
		//Code generating
		for($i=0;$i<$taille;$i++){
			$code = $code.$alpha[rand(0, count($alpha)-1)];
		}
		return "".$code; //To be sure it will return a string
	}

	//Return a unique string for file name
	public static function microtime_name_old() {
		usleep(1);
		list($usec, $sec) = explode(' ', microtime());
		$usec = str_replace('0.','',$usec);
		return $sec.$usec.genRandomString(10);
	}

	public static function hex_encode_email_old($str) {
		$encoded = bin2hex($str);
		$encoded = chunk_split($encoded, 2, '%');
		$encoded = '%'.mb_substr($encoded, 0, mb_strlen($encoded) - 1);
		return $encoded;
	}

	//Replace " and ' in their HTML code to avoic any code conflict
	public static function quote_old($str) {
		$quot = array("'", '"');
		$spec   = array("&#039;", "&quot;");
		return str_replace($quot, $spec, $str);
	}

	//Same as nl2br, but without the line return in the HTML code by PHP (if not trouble for a alert)
	public static function nl2br2_old($str) { 
		return str_replace(array("\r\n", "\n\r", "\r", "\n"), "<br />", $str); 
	}

	public static function change_chariots_old($tx) {
		$retchar = array( CHR(10) => "", CHR(13) => "" ); 
		$tx = strtr($tx,$retchar); 
		return $tx;
	}

	public static function change_chariots_space_old($tx) {
		$retchar = array( CHR(10) => "&nbsp;", CHR(13) => "&nbsp;" ); 
		$tx = strtr($tx,$retchar); 
		return $tx;
	}

	//Check the validy of a company code, return true if valid
	public static function checkcode_old($str) {
		$strtonum = array("a" => 0, "c" => 1, "e" => 2, "g" => 3, "i" => 4, "k" => 5, "m" => 6, "o" => 7, "q" => 8, "s" => 9);
		$temp = str_split($str);
		$expire = "";
		foreach ($temp as $i => $value) {
		  if($i==0){
			if($temp[$i]!=5 && $temp[$i]!=6){
				break;
			}
		  } if(Odd($i) && array_key_exists($temp[$i],$strtonum)){
			$expire = $expire.$strtonum[$temp[$i]];
		  }
		}
		$expire = intval($expire);
		return ($expire>time());
	}

	//Convert 'F6E534' into R[0] G[1] B[1]
	public static function html2rgb_old($color){
		//By default use a green color
		$r = '9E';
		$g = 'F5';
		$b = 'BD';
		if($color[0]=='#'){
			$color = mb_substr($color, 1);
		}
		if(mb_strlen($color)==6){
			list($r, $g, $b) = array($color[0].$color[1], $color[2].$color[3], $color[4].$color[5]);
		} else if(mb_strlen($color)==3){
			list($r, $g, $b) = array($color[0].$color[0], $color[1].$color[1], $color[2].$color[2]);
		} else {
			return false;
		}
		$r = hexdec($r);
		$g = hexdec($g);
		$b = hexdec($b);
		
		return array($r, $g, $b);
	}



}