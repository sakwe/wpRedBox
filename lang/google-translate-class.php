<?php 


class GoogleTranslate {
	public $lastResult = "";
	private $langFrom;
	private $langTo;
	private static $urlFormat = "http://translate.google.com/translate_a/t?client=t&text=%s&hl=en&sl=%s&tl=%s&ie=UTF-8&oe=UTF-8&multires=1&otf=1&pc=1&trs=1&ssel=3&tsel=6&sc=1";

	public function setLangFrom($lang) {
	$this->langFrom = $lang;
	return $this;
	}
	public function setLangTo($lang) {
	$this->langTo = $lang;
	return $this;
	}

	public function __construct($from = "en", $to = "fr") {
	$this->setLangFrom($from)->setLangTo($to);
	}
	
	public static final function makeCurl($url, array $params = array(), $cookieSet = false) {
		if (!$cookieSet) {
			$cookie = tempnam("/tmp", "CURLCOOKIE");
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_COOKIEJAR, $cookie);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_exec($curl);
		}
		$queryString = http_build_query($params);
		$curl = curl_init($url . "?" . $queryString);
		curl_setopt($curl, CURLOPT_COOKIEFILE, $cookie);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		$output = curl_exec($curl);   return $output;
	}

	public function translate($string) {
		$url = sprintf(self::$urlFormat, rawurlencode($string), $this->langFrom, $this->langTo);
		$result = preg_replace('!,+!', ',', self::makeCurl($url)); // remove repeated commas (causing JSON syntax error)
		$resultArray = json_decode($result, true);
		 return $this->lastResult = $resultArray[0][0][0];
	}
}
?>
