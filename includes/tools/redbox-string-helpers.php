<?


function suppr_accents($str)
{
  $avant = array('À','Á','Â','Ã','Ä','Å','Ā','Ă','Ą','Ǎ','Ǻ','Æ','Ǽ',
'Ç','Ć','Ĉ','Ċ','Č','Ð','Ď','Đ',
'É','È','Ê','Ë','Ē','Ĕ','Ė','Ę','Ě','Ĝ','Ğ','Ġ','Ģ',
'Ĥ','Ħ','Ì','Í','Î','Ï','Ĩ','Ī','Ĭ','Į','İ','ĺ','ļ','ľ','ŀ','ł','Ǐ','Ĳ','Ĵ','Ķ','Ĺ','Ļ','Ľ','Ŀ','Ł',
'Ń','Ņ','Ň','Ñ','Ò','Ó','Ô','Õ','Ö','Ō','Ŏ','Ő','Ơ','Ǒ','Ø','Ǿ','Œ','Ŕ','Ŗ','Ř',
'Ś','Ŝ','Ş','Š','Ţ','Ť','Ŧ','Ũ','Ù','Ú','Û','Ü','Ū','Ŭ','Ů','Ű','Ų','Ư','Ǔ','Ǖ','Ǘ','Ǚ','Ǜ',
'Ŵ','Ý','Ŷ','Ÿ','Ź','Ż','Ž',
'à','á','â','ã','ä','å','ā','ă','ą','ǎ','ǻ','æ','ǽ','ç','ć','ĉ','ċ','č','ď','đ',
'è','é','ê','ë','ē','ĕ','ė','ę','ě','ĝ','ğ','ġ','ģ','ĥ','ħ',
'ì','í','î','ï','ĩ','ī','ĭ','į','ı','ǐ','ĳ','ĵ','ķ',
'ñ','ń','ņ','ň','ŉ','ò','ó','ô','õ','ö','ō','ŏ','ő','ơ','ǒ','ø','ǿ','œ',
'ŕ','ŗ','ř','ś','ŝ','ş','š','ß','ţ','ť','ŧ',
'ù','ú','û','ü','ũ','ū','ŭ','ů','ű','ų','ǔ','ǖ','ǘ','ǚ','ǜ','ư','ŵ','ý','ÿ','ŷ','ź','ż','ž','ƒ','ſ');
  $apres = array('A','A','A','A','A','A','A','A','A','A','A','AE','AE',
'C','C','C','C','C','D','D','D',
'E','E','E','E','E','E','E','E','E','G','G','G','G',
'H','H','I','I','I','I','I','I','I','I','I','I','I','I','I','I','I','IJ','J','K','L','L','L','L','L',
'N','N','N','N','O','O','O','O','O','O','O','O','O','O','O','O','OE','R','R','R',
'S','S','S','S','T','T','T','U','U','U','U','U','U','U','U','U','U','U','U','U','U','U','U',
'W','Y','Y','Y','Z','Z','Z',
'a','a','a','a','a','a','a','a','a','a','a','ae','ae','c','c','c','c','c','d','d',
'e','e','e','e','e','e','e','e','e','g','g','g','g','h','h',
'i','i','i','i','i','i','i','i','i','i','ij','j','k',
'n','n','n','n','n',
'o','o','o','o','o','o','o','o','o','o','o','o','oe',
'r','r','r','s','s','s','s','s','t','t','t',
'u','u','u','u','u','u','u','u','u','u','u','u','u','u','u','u','w','y','y','y','z','z','z','f','s');
  return str_replace($avant, $apres, $str);
}

function suppr_specialchar($str,$remplacement='-')
	{
	return preg_replace('/([^.a-z0-9]+)/i', $remplacement, $str);
	}
	

function processString($s) {
	return preg_replace('/https?:\/\/[\w\-\.!~?&=#+\*\'"(),\/]+/','<a href="$0" target="_blank">$0</a>',$s);
}
	
/**
 * Convert BR tags to nl
 *
 * @param string The string to convert
 * @return string The converted string
 */
function br2nl($string)
{
    return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}

//
//    utf8 encoding validation developed based on Wikipedia entry at:
//    http://en.wikipedia.org/wiki/UTF-8
//
//    Implemented as a recursive descent parser based on a simple state machine
//    copyright 2005 Maarten Meijer
//
//    This cries out for a C-implementation to be included in PHP core
//
    function valid_1byte($char) {
        if(!is_int($char)) return false;
        return ($char & 0x80) == 0x00;
    }
   
    function valid_2byte($char) {
        if(!is_int($char)) return false;
        return ($char & 0xE0) == 0xC0;
    }

    function valid_3byte($char) {
        if(!is_int($char)) return false;
        return ($char & 0xF0) == 0xE0;
    }

    function valid_4byte($char) {
        if(!is_int($char)) return false;
        return ($char & 0xF8) == 0xF0;
    }
   
    function valid_nextbyte($char) {
        if(!is_int($char)) return false;
        return ($char & 0xC0) == 0x80;
    }
   
    function valid_utf8($string) {
        $len = strlen($string);
        $i = 0;   
        while( $i < $len ) {
            $char = ord(substr($string, $i++, 1));
            if(valid_1byte($char)) {    // continue
                continue;
            } else if(valid_2byte($char)) { // check 1 byte
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
            } else if(valid_3byte($char)) { // check 2 bytes
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
            } else if(valid_4byte($char)) { // check 3 bytes
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
                if(!valid_nextbyte(ord(substr($string, $i++, 1))))
                    return false;
            } else {
           return false; // 10xxxxxx occuring alone
         } // goto next char
        }
        return true; // done
    }

// Returns true if $string is valid UTF-8 and false otherwise.
function is_utf8($string) {
   
    // From http://w3.org/International/questions/qa-forms-utf-8.html
    return preg_match('%^(?:
          [\x09\x0A\x0D\x20-\x7E]            # ASCII
        | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
        |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
        | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
        |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
        |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
        | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
        |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
    )*$%xs', $string);
   
} // function is_utf8

?>
