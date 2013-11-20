<?php

include WP_PLUGIN_DIR.'/redbox/lang/google-translate-class.php';

/**
 * This class manage the languages detection, integration and translation
 */
class redBoxServerLanguage{

	
	public $all_languages,$supported_languages,$default_language;

	public function __construct(){
	
		/**
		 * Default langauge that will be used for default and for google translation too		 
		 */
		$this->default_language='fr';
		
		/**
		 * All languages that could exists... 
		 * if not in supported language, we have to create a file and a directory for it translation
		 */
		$this->all_languages = array(
		'auto',//auto detection
		'af', // afrikaans.
		'ar', // arabic.
		'bg', // bulgarian.
		'ca', // catalan.
		'cs', // czech.
		'da', // danish.
		'de', // german.
		'el', // greek.
		'en', // english.
		'es', // spanish.
		'et', // estonian.
		'fi', // finnish.
		'fr', // french.
		'gl', // galician.
		'he', // hebrew.
		'hi', // hindi.
		'hr', // croatian.
		'hu', // hungarian.
		'id', // indonesian.
		'it', // italian.
		'ja', // japanese.
		'ko', // korean.
		'ka', // georgian.
		'lt', // lithuanian.
		'lv', // latvian.
		'ms', // malay.
		'nl', // dutch.
		'no', // norwegian.
		'pl', // polish.
		'pt', // portuguese.
		'ro', // romanian.
		'ru', // russian.
		'sk', // slovak.
		'sl', // slovenian.
		'sq', // albanian.
		'sr', // serbian.
		'sv', // swedish.
		'th', // thai.
		'tr', // turkish.
		'uk', // ukrainian.
		'zh', // chinese.
		'debug' // debug language file.
		);
		$this->getSupportedLanguages();

		/**
		 * All languages we can manage 
		 */
		$this->getAllLanguages();
		
		/**
		 * Current config language 
		 */
		$this->current_language= $this->getCurrentLanguage();
	}
	
	/**
	 * Load all the languages
	 */
	public function getAllLanguages(){
		$_SESSION['supported_languages']='auto,';
		foreach($this->all_languages as $lang_name){
			if ($lang_name!='debug'&&$lang_name!='auto') $_SESSION['supported_languages'].= $lang_name.',';
		}	
		$_SESSION['supported_languages'].= 'debug';
	}


	/**
	 * What languages do we support ?
	 */
	public function getSupportedLanguages(){
		$supported_languages = glob(WP_PLUGIN_DIR.'/redbox'.'/lang/*', GLOB_BRACE);
		foreach($supported_languages as $a_lang) {						
			$pat[0]= WP_PLUGIN_DIR.'/redbox'.'/lang/';
			$rep[0]= '';
			// get the single lang name
			$lang_name = basename($a_lang);
			$lang_name = str_replace($pat,$rep,$lang_name);
			if (in_array($lang_name,$this->all_languages)) {
				$this->supported_languages[]=$lang_name;
			}
		}
	}

	/**
	 * Get the current language from the config or auto
	 */
	 public function getCurrentLanguage(){
		if(REDBOX_LANGUAGE == 'auto'){ 
			// detect our language from browser
			$_SESSION['lang'] = $this->getBrowserLanguage();
		}
		else{
			if (in_array(REDBOX_LANGUAGE,$this->all_languages)){
				$_SESSION['lang']= REDBOX_LANGUAGE;
			}
			else{
				$_SESSION['lang']= $this->default_language;
			}
		}
		if (!in_array($_SESSION['lang'],$this->supported_languages)&&$_SESSION['lang']!='auto'&&$_SESSION['lang']!='debug') {
			if (REDBOX_LANGUAGE == $_SESSION['lang']){
				$this->translateToLanguage($_SESSION['lang']);
			}
		}
		return $_SESSION['lang'];
	}
	
	/**
	 * Get the language from the client browser
	 */
	public function getBrowserLanguage(){
		// Detect HTTP_ACCEPT_LANGUAGE & HTTP_USER_AGENT.
		getenv('HTTP_ACCEPT_LANGUAGE');
		getenv('HTTP_USER_AGENT');
		$_AL=strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
		$_UA=strtolower($_SERVER['HTTP_USER_AGENT']);
		// Try to detect Primary language if several languages are accepted.
		foreach($this->all_languages as $a_lang){
			if(strpos($_AL, $a_lang)===0)return $a_lang;
		}
		// Try to detect any language if not yet detected.
		foreach($this->all_languages as $a_lang){
			if(strpos($_AL, $a_lang)!==false)return $a_lang;
		}
		// Return default language if language is not yet detected.
		return $this->default_language;
	}
	
	/**
	 * Try to generate a file with google translate
	 */
	public function translateToLanguage($translate_to){
		// Create the directory language
		if (!file_exists(WP_PLUGIN_DIR.'/redbox'.'/lang/'.$translate_to)){
			mkdir(WP_PLUGIN_DIR.'/redbox'.'/lang/'.$translate_to);
		}
		// Get all the language files in the directory
		$language_files = glob(WP_PLUGIN_DIR.'/redbox'.'/lang/'.$this->default_language.
							'/*.'.$this->default_language.'.php', GLOB_BRACE);
		foreach($language_files as $a_lang_file) {
			// create the new lan file
			$def[0]=$this->default_language;
			$new[0]=$translate_to;
			$new_lang_file_name = str_replace($def,$new,$a_lang_file);
			if ($a_lang_file != $new_lang_file_name){
				$new_lang_file = fopen($new_lang_file_name,'w');
				fwrite($new_lang_file,'<?php'."\n");
				$lines = file($a_lang_file);
				if ($lines) {
					// first, try to catch a "define" line
					foreach($lines as $line ){
						$re1='(define)';
						$re2='(\\()';
						$re3='(")';
						$re4='(.*?)';
						$re5='(")';
						$re6='(,)';
						$re7='(")';
						$re8='(.*?)';
						$re9='(")';
						$re10='(\\))';
						$re11='(;)';

						if ($c=preg_match_all ("/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9.$re10.$re11."/is", $line, $matches)){
							foreach($matches[4] as $m){
								$to_define = $m;
							}				
							foreach($matches[8] as $m){					
								$t = new GoogleTranslate($this->default_language, $translate_to);
								$define_value = $t->translate(stripslashes($m));
							}
							fwrite($new_lang_file,'define("'.$to_define.'","'.addslashes($define_value).'");'."\n");
						}
					}
					
					foreach($lines as $line ){
						$re1='(\\$)';
						$re2='(.*?)';
						$re3='([ ]+|[\t]+)';
						$re4='(=)';
						$re5='([ ]+|[\t]+)';
						$re6='(")';
						$re7='(.*?)';
						$re8='(")';
						$re9='(;)';
						if ($c=preg_match_all ("/".$re1.$re2.$re3.$re4.$re5.$re6.$re7.$re8.$re9."/is", $line, $matches)){
							foreach($matches[2] as $m){
								$to_define = $m;
							}				
							foreach($matches[7] as $m){					
								$t = new GoogleTranslate($this->default_language, $translate_to);
								$define_value = $t->translate(stripslashes($m));
							}
							fwrite($new_lang_file,'$'.$to_define.' = "'.addslashes($define_value).'";'."\n");
						}
					}
					
				fwrite($new_lang_file,'?>');
				fclose($new_lang_file);
				}
			}
		}
	}
}


$redBoxServerLanguage = new redBoxServerLanguage();

include_once(WP_PLUGIN_DIR.'/redbox/lang/'.$redBoxServerLanguage->current_language.'/lang.'.$redBoxServerLanguage->current_language.'.php');


?>
