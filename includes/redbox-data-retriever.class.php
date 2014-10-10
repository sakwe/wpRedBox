<?
//require 'facebook/facebook.php';

/* The RedboxDataRetriever class
 *
 * This PHP class can retrieve base datas from an url and return a datas array objects with : 
 *
 * - url	: the base url we enter
 * - source	: the final source used by the class
 * - type	: url/article/picture/video/gallery
 * - category	: categorised as Links/Articles/Pictures/Short videos/Documentary/Pictures gallery
 * - title	: The title of the document
 * - description: Description found in the HTML document 
 * - message	: From Facebook
 * - created 	: The datetime of the created post if found or with Facebook API
 * - origin 	: The main domain of the source (ex: www.scoop.it)
 * - author 	: from facebook or from a blog meta (author_name, author_url, author_picture object)
 * - pictures	:	- url -> The picture found for the document (can retrieve Facebook galleries, so retrieves all pictures)
 *			- description -> generally empty, it contains the "title" tags of the img if found in html or the Facebook description with API
 *			- width 
 *			- height
 * - videos_datas	- type -> youtube/daylimotion/vimeo/google/canalplus
 *			- id -> video unique ID for video provider
 *			- title -> video title
 *			- description -> video description
 *			- img -> url for the image thumbnail
 *			- code -> html code for a classe embed video object
 *			- embed -> html code for iframe embed video
 *			- shortcode -> WordPress shorcode as example [youtube VIDEO_URL]
 *			- keywords -> founded keywords from the video provider
 *			- views -> views on the video provider site
 *			- duration -> duration of the video
.*
 **/


/* Hard configuration for this class
 *
 **/
define ("MAX_REDIR",3);
define ("MAX_WORDS_FOR_TITLE_GENERATED",8);
define ("MIN_WORDS_FOR_DESCRIPTION_IN_HTML_TAGS",25);
define ("NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML",3);
define ("NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML_FOR_GALLERIES",25);
define ("MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML",160);
define ("MIN_HEIGHT_OF_PICTURES_IF_SEEKED_IN_HTML",160);
define ("MAX_SECONDS_FOR_SHORT_VIDEO_CATEGORY",900);
/*/


/* Example for use it width Facebook API capabilites and customised categories
 *
 **
	$categories = array(	'link'=>'Liens',
				'article'=>'Articles',
				'picture'=>'Photos',
				'gallery'=>'Galleries',
				'video'=>'Vidéos',
				'short_video'=>'Courtes vidéos',
				'documentary'=>'Documentaires'
				);
	$fb_config = array('primary_id'=>'YOUR_FACEBOOK_ID','app_id'=>'YOUR_FACEBOOK_APP_ID','app_secret'=>'YOUR_FACEBOOK_APP_SECRET')	
	$dr = new RedboxDataRetriever($categories,$fb_config);	
	$datas = $dr->get_datas($_GET['url']);
	
*****/


// CLASS RedboxDataRetriever
class RedboxDataRetriever{

	public $categories,$fb_config;

	public function __construct($categories=null,$fb_config=null,&$redbox=null){
		// connect to the global redbox instance
		$this->redbox = $redbox;

		// default configuration for types/categories
		if ($categories==null) {
			$categories = array(	'link'=>'Links',
						'article'=>'Articles',
						'picture'=>'Pictures',
						'gallery'=>'Pictures galleries',
						'video'=>'Videos',
						'short_video'=>'Short videos',
						'documentary'=>'Documentaries',
						);
		}
		$this->categories = $categories;
		$this->fb_config = array();
		$this->fb_config = $fb_config;
		$this->contentType = "utf8";
		$this->redbox->fallBack=false;
	}

	public function get_datas($fetched,$quick=false){
		$urls = $fetched;
		// if we have a string with urls, make an array with it
		$message=null;
		if (!is_array($urls)){
			$tmp_message = $urls;
			preg_match_all('!https?://[\S]+!', $urls, $match);
			$urls=$match[0];
			foreach($urls as $url){
				$tmp_message = str_replace($url,'',$tmp_message);
			}
			if (trim($tmp_message)!='') $message = $tmp_message;
		}
		if (count($urls)==0){
			$urls[]= $fetched;
			$message = null;
		}
		// get datas for all urls
		$this->list_datas = array();
		$urls = array_reverse($urls);
		foreach ($urls as $url){
			$url=trim($url);
			if ($url!=''){
				// first, check if it's a direct facebook id for API
				if (stripos($url,"http")===false){
					$this->get_datas_from_facebook($url,$quick);
				}
				else{		
					//check if we get a facebook link for API
					if ($fb_id=$this->get_facebook_url_id($url)){
						$this->get_datas_from_facebook($fb_id,$quick);
					}
					else{
						$this->get_datas_from_url($url,$quick);
					}
				}
			}
		}
		// clean descriptions (get the bests)
		$this->clean_descriptions();
		// reorder pictures by width (higher first)
		$this->reorder_pictures();
		// add the message if we have it
		if(count($this->list_datas)>0 && $message){
			$this->list_datas[0]->message = $message ."\n". $this->list_datas[0]->message;
		}
		return array_reverse($this->list_datas);
	}
	
	// get facebook ids from the url
	private function get_facebook_url_id($url){
		$parsed =  parse_url($url);
		$dns = $parsed['host'];
		$dns = str_replace('www.','',$dns);
		if ($dns=="facebook.com"){
			$fb_id = "";
			$fb_unique_id = null;
			$page_id = null;
			// this look for a common id in the url
			$tmp_url = explode("?",$url);
			$args_url = $tmp_url[1];
			parse_str($args_url,$parsed_url);

			if ($parsed_url['story_fbid'] && $parsed_url['story_fbid']!=''){
				$fb_unique_id = $parsed_url['story_fbid'];
			}
			if ($parsed_url['fbid'] && $parsed_url['fbid']!=''){
				$fb_unique_id = $parsed_url['fbid'];
			}
			if ($parsed_url['set'] && $parsed_url['set']!=''){
				if ($parts = explode('.',$parsed_url['set'])){
					$page_id = $parts[(count($parts)-1)];
				}
			}
			if ($parsed_url['id'] && $parsed_url['id']!=''){
				$page_id = $parsed_url['id'];
			}
			// we got an unique id, lets pick the origin id
			if ($fb_unique_id && $page_id){
				$fb_id = $fb_unique_id;
			}

			// this analyse an url for a fb gallery or photo theater 
			if ($fb_id==""){
				preg_match_all("/.*?(\\d+).*?\\d+.*?[.](\\d+)/is",$url,$matches);
				$gallery_id=$matches[1][0];
				$author_id=$matches[2][0];
				if ($gallery_id>0 && $author_id>0) $fb_id = $gallery_id;
			}
			
			if ($fb_id==""){
				// this analyse an url for a unique id fb
				$tmp_url = $url;
				$tmp_url = str_replace("http://","",$tmp_url);
				$tmp_url = str_replace("https://","",$tmp_url);
				$parts = explode("/",$tmp_url);
				$page_id = $parts[1];
				$authToken = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$this->fb_config['app_id']}&client_secret={$this->fb_config['app_secret']}");	
				$json_object = $this->fetchUrl("https://graph.facebook.com/".$page_id."?{$authToken}");
				$feed_data = json_decode($json_object);
				$page_id = $feed_data->id;
				foreach ($parts as $part){
					preg_match("/(\\d+)/is",$part,$matches);
					if ($matches[0]>0){
						$post_id = $matches[0];
						break;
					}
				}
				
				$fb_id = $page_id;
				if($post_id) $fb_id.= "_" . $post_id;
			}
			return trim($fb_id);
		}
		else{
			return false;
		}
	}
	
	// get datas from facebook api with our ids
	private function get_datas_from_facebook($id_fb,$quick=false){
		$id_fb = trim($id_fb);
		
		// instantiate a data container that we will add to the list_data
		$datas = new RedboxDataContainer();
		$datas->author_picture = new RedboxPictureDataContainer();
		
		// get the data flow from facebook open graph api
		$authToken = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$this->fb_config['app_id']}&client_secret={$this->fb_config['app_secret']}");	
		$json_object = $this->fetchUrl("https://graph.facebook.com/".$id_fb."?{$authToken}");
		$feed_data = json_decode($json_object);

		if ($feed_data->type=='status'){
			return false;
		}

		// set basic datas
		$datas->url = "https://www.facebook.com/".$id_fb;
		$datas->fb_id = $id_fb;
		$datas->pictures = array();
		$datas->video_datas = new RedboxVideoDataContainer();		
		$datas->message = $feed_data->message;
		$ts = $feed_data->created_time;
		$dt = new DateTime($ts);
		$datas->created = $dt->format('Y-m-d H:i:s');
		if ($datas->message==""){
			$datas->message = $feed_data->name;
		}
		else{
			$datas->title = $feed_data->name;
		}
		
		$this->fb_id_author = $feed_data->from->id;
		$datas->author_name = $feed_data->from->name;
		$datas->author_url = "https://www.facebook.com/".$feed_data->from->id;

		$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->from->id."/photos?fields=source,images&{$authToken}");
		$author_data = json_decode($json_object);
		if ($author_data->data){
			//$author_data->data = array_reverse($author_data->data);
			foreach ($author_data->data as $author_picture){
				$author_picture->images = array_reverse($author_picture->images);
				foreach ($author_picture->images as $author_image){
					$datas->author_picture = new RedboxPictureDataContainer($author_image->source);
					$datas->author_picture->title = $datas->author_name;
					break;
				}
				break;
			}
		}
		// if we found a picture gallery, let's go !
		if ($feed_data->cover_photo){
			// set type and category
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			$datas->title = $feed_data->name;
			$datas->message = $feed_data->description;
			$datas->description = '';
			$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->cover_photo."?{$authToken}");			
			$cover_data = json_decode($json_object);
			// Get the cover picture for first picture in our object
			$datas->pictures[] = new RedboxPictureDataContainer($cover_data->source);
			$pict_idx = (count($datas->pictures)-1);
			$datas->pictures[$pict_idx]->title = $cover_data->name;
			// if we not ask us a quick check, let's pick all the pictures
			if (!$quick){
				$url = "https://graph.facebook.com/".$id_fb."/photos?fields=images,name&{$authToken}";
				while($url){
					$json_object = $this->redbox->retriever->fetchUrl($url);
					$gallery_data = json_decode($json_object);
					foreach ($gallery_data->data as $pictures_data){
						foreach ($pictures_data->images as $picture_data){
							$datas->pictures[] = new RedboxPictureDataContainer($picture_data->source);
							$pict_idx = (count($datas->pictures)-1);
							$datas->pictures[$pict_idx]->title=$pictures_data->name;
							break;
						}
					}
					$url = $gallery_data->paging->next;
				}
				
			}
		}
		// it's website link, lets go to complete our datas from the source url 
		elseif ($feed_data->type == "link"){
			$datas->type = "article";
			$datas->category = $this->categories["article"];
			$datas->source = $feed_data->link;
			if ($feed_data->picture){
				$url_parts = explode("&url=",$feed_data->picture);
				if (count($url_parts) > 1){
					$feed_data->picture = urldecode($url_parts[1]);
				}
				$datas->pictures[] = new RedboxPictureDataContainer($feed_data->picture);
			}
			$this->get_datas_from_url($this->cleanUrl($feed_data->link));
		}
		// it's video provider link, lets go to complete our datas from the source url 
		elseif ($feed_data->type == "video"){
			$datas->type = "video";
			$datas->source = $feed_data->link;
			if ($feed_data->picture){
				$url_parts = explode("&url=",$feed_data->picture);
				if (count($url_parts) > 1){
					$feed_data->picture = urldecode($url_parts[1]);
				}
				$datas->pictures[] = new RedboxPictureDataContainer($feed_data->picture);
			}
			$list_datas = $this->get_datas_from_url($this->cleanUrl($feed_data->link));
			$datas->category = $list_datas->category;
		}
		// it's photo gallery post lets go to complete our datas from the source url 
		elseif ($feed_data->type == "photo" && $feed_data->picture != "" && $feed_data->link != "" ){
			$datas->type = "picture";
			$datas->source = $feed_data->link;			
			// set type and category
			$datas->category = $this->categories["picture"];
			$datas->title = $feed_data->name;
			$datas->message = $feed_data->message;
			$datas->description = $feed_data->message;
			/*
			$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->object_id."?{$authToken}");			
			$cover_data = json_decode($json_object);
			 Get the cover picture for first picture in our object
			if ($cover_data->source && trim($cover_data->source)!='') {
				$datas->pictures[] = new RedboxPictureDataContainer($cover_data->source);
			} else {
				foreach ($cover_data->images as $cover) {
					$datas->pictures[] = new RedboxPictureDataContainer($cover->source);
					break;
				}
			}
			if ($cover_data->name) $datas->pictures[$pict_idx]->title = $cover_data->name;
			*/			
			$facebook = new Facebook(array(
			'appId' => $this->fb_config['app_id'],
			'secret' => $this->fb_config['app_secret'],
			'cookie' => true,
			));
			
			$fql = 'SELECT attachment FROM stream WHERE post_id="'.$id_fb.'"';
			 
			$response = $facebook->api(array(
			'method' => 'fql.query',
			'query' =>$fql,
			));

			foreach ($response[0]["attachment"]["media"] as $media_data){
				$url = "https://graph.facebook.com/".$media_data["photo"]["fbid"]."/?fields=source,name&{$authToken}";
				$json_object = $this->fetchUrl($url);
				$picture_data = json_decode($json_object);
				$datas->pictures[] = new RedboxPictureDataContainer($picture_data->source);
				$pict_idx = (count($datas->pictures)-1);
				$datas->pictures[$pict_idx]->title=$media_data["alt"];
			}
			
			//$facebook->clearPersistentData();
		}
		// it's a facebook picture item, let's pick it
		elseif ($feed_data->picture != ""){
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			// if we have no source for the picture, recheck API datas from the link
			if (!$feed_data->source){
				// pass this datas and get datas from the picture source instead of post source
				$pass=true;
				$datas = $this->get_datas_from_facebook($feed_data->object_id,$quick);
				// keep the original ID and not the object_id
				$datas->fb_id = $id_fb;
			}
			else{
				$datas->source = $feed_data->source;
				$datas->pictures[] = new RedboxPictureDataContainer($feed_data->source);
				$pict_idx = (count($datas->pictures)-1);
			}
		}
		else{
			$datas->type = $feed_data->type;
			$datas->category = $this->categories["link"];
		}
		// still no description (from website or video provider, or else?), take what we have
		if (trim($datas->description)==""){
			$datas->description = $feed_data->description;
		}
		// still no title (from website or video provider, or else?), generate it !
		if (trim($datas->title)=="" && (trim($datas->message)!=''||trim($datas->description)!='')){
			$base='';
			if (trim($datas->message)!="") $base = $datas->message;
			if ($base=="") $base = $datas->description;
			preg_match('/^(?>\S+\s*){1,'.MAX_WORDS_FOR_TITLE_GENERATED.'}/', $base, $match);
			if ($match){
				$datas->title = $match[0]."...";
			}
			else{
				$datas->title = $base;
			}
			$titles = explode("\n",$base);
			if ($titles && trim($titles[0])!= "") {
				$datas->title = $titles[0];
				$datas->message = str_replace($titles[0]."\n",'',$datas->message);
				$datas->description = str_replace($titles[0]."\n",'',$datas->description);
			}
		}
		// still no origin for the source?
		if (trim($datas->origin)=="") $datas->origin = "facebook.com";
		
		//if ($datas->type != "gallery"){
		// maybe we have a posted website links into the message ?
		// let's complete data urls found in it !
			preg_match_all('!https?://[\S]+!', $datas->message, $match);
			foreach ($match[0] as $an_url) {
				$this->get_datas_from_url($this->cleanUrl($an_url));
			}
		//}

		if (!$pass && ($datas->message !='' || $datas->title !='' || $datas->description !='')){			
			// feed the list_data and return it
			if ((($datas->type=='video' || $datas->type=='article') && count($this->list_datas)>0) 
			|| ($datas->type!='video' && $datas->type!='article')){
				$this->list_datas[] = $datas;
				return $datas;
			}
			else{
				return null;
			}
		}
		else{
			return false;
		}
	}
	
	public function cleanUrl($url){
		// clean the url with no needed args
		foreach($this->redbox->configuration->to_clean_in_urls as $replace){
			$url  = str_replace($replace,'',$url);
		}

		if (substr($url,(strlen($url)-1),(strlen($url)))=='#') $url = substr($url,0,(strlen($url)-1));
		if (substr($url,(strlen($url)-1),(strlen($url)))=='.') $url = substr($url,0,(strlen($url)-1));
		//if (substr($url,(strlen($url)-1),(strlen($url)))=='/') $url = substr($url,0,(strlen($url)-1));
		return $url;
	}
	
	public function cleanTitle($title){
		// clean the url with no needed args
		foreach($this->redbox->configuration->to_clean_in_titles as $replace){
			$title  = str_replace($replace,'',$title);
		}
		return $title;
	}
	public function cleanText($text){
		// clean the url with no needed args
		foreach($this->redbox->configuration->to_clean_in_texts as $replace){
			$text  = str_replace($replace,'',$text);
		}
		return $text;
	}

	public function convertedString($string){
		$debug= $string."<br />";
		$string=str_replace("’","'",$string);
		$tmp_string = $string;
		// check the caracter coding for html
		$string = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;",urldecode($string));
		$debug.= "urldecode<br />";
		$debug.= $string."<br />\n";
		if (valid_utf8($string) || strpos($this->contentType,'utf-8')>0 || strpos($this->contentType,'utf8')>0 || strpos($this->contentType,'UTF-8')>0 || strpos($this->contentType,'UTF8')>0){
			if (valid_utf8($string)){
				$debug.= "detect and iconv : ".$this->contentType."<br />";
				$string = iconv(mb_detect_encoding($string, mb_detect_order(), true), "UTF-8", $string);
				$debug.= $string."<br />\n";
				//$string = html_entity_decode($string,null,'UTF-8');
				//$tmp_string = $string;
				$string = $tmp_string;
				$debug.= "decode : <br />";
				$string_decoded = utf8_decode($string);
				$debug.= $string_decoded."<br />\n";
				if (strlen($string_decoded) < strlen($string)){
					$debug.= "decode is best : <br />";
					$string = $string_decoded;
					$debug.= $string."<br />\n";
				}
				
				$string_decoded=str_replace("’","'",$tmp_string);
				$string_decoded=str_replace("Â«","\"",$string_decoded);
				$string_decoded=str_replace("Â»","\"",$string_decoded);
				$string_decoded=str_replace("«","\"",$string_decoded);
				$string_decoded=str_replace("»","\"",$string_decoded);
				$string_decoded=str_replace("Ã©","é",$string_decoded);
				$string_decoded=str_replace("Ã®","î",$string_decoded);
				$string_decoded=str_replace("—","-",$string_decoded);
				$string_decoded = Encoding::fixUTF8($string_decoded);
				if (strlen($string_decoded) <= strlen($string) ){
					//$string = $string_decoded;
					$debug.= "fixed+ : ".$this->contentType."<br />";
					$debug.= $string_decoded."<br />\n";
				}
				
				if (!valid_utf8($string)){
					$string = $tmp_string;
					$debug.= "cancel<br />";
					$debug.= $string."<br />\n";
					
					if (!valid_utf8($string)){
						$string = $tmp_string;
						$debug.= "convert to uft8 : <br />";
						$string = mb_convert_encoding($string,'utf-8');
						$debug.= $string."<br />\n";
					}
				}
				
				if (!valid_utf8($string)){
					$string = $tmp_string;
					$debug.= "decode : <br />";
					$string = utf8_decode($string);
					$debug.= $string."<br />\n";
				}
				
			}
			
			if (!valid_utf8($string)){
				$debug.= "convert to utf8 : <br />";
				$string = mb_convert_encoding($string,'utf-8');
				$debug.= $string."<br />\n";
			}
		}	
		else{
			$tmp_string = $string;
			$debug.= "decode non-utf8<br />";
			$string = utf8_decode($string);
			$debug.= $string."<br />\n";
			if (!valid_utf8($string)){
				$string = $tmp_string;
				$debug.= "convert to utf8 : <br />";
				$string = mb_convert_encoding($string,'utf-8');
				$debug.= $string."<br />\n";
			}
			$string_decoded = utf8_encode($tmp_string);
			if (strlen($string_decoded) < strlen($string)){
				$string = $string_decoded;
				$debug.= "encode from : ".$this->contentType."<br />";
				$debug.= $string."<br />\n";
			}
		}
		if (substr_count($string_decoded,'?')<substr_count($string,'?')){
			$string = $string_decoded;
			$debug.= "fixed+ is best : (entry:".$this->contentType.")<br />";
			$debug.= $string."<br />\n";
		}
		//$string = html_entity_decode($string,ENT_QUOTES);
		$debug.= "result : <br />".$string."<br />\n<br /><hr />";
		//echo $debug;
		//exit;
		return $string;
	}

	// get datas inside the html we get from the url
	private function get_datas_from_url($url,$redir=0){
		$base_url='';
		
		// we don't know what is behind the url ..
		$forced_type=null;
		$url_exists=false;
		$is_image_url = false;
		$with_gallery=false;
		// check if we have REDBOX arg from the url -> forced_type
		foreach ($this->categories as $type => $category){
			if (stripos($url,'#'.$type)>0){
				$forced_type=$type;
				$url = str_replace('#'.$type,'',$url);
				break;
			}
		}
		// check if we need a gallery pictures
		if (stripos($url,'#with_gallery')>0){
			$with_gallery=true;
			$url = str_replace('#with_gallery','',$url);
		}
		
		$url = $this->cleanUrl($url);
		// modify tiny urls from youtube links
		$url = str_replace("http://youtu.be/","https://www.youtube.com/watch?v=",$url);
		$url = str_replace("youtu.be","youtube.com/watch?v=",$url);

		// modify tiny urls from dailymotion links
		$url = str_replace("dai.ly","www.dailymotion.com/video",$url);
				
		foreach($this->list_datas as $d){
			if (trim($d->url) == trim($url)){
				$url_exists=true;
				return false;
				break;
			}
		}
		
		// instantiate a data container that we will add to the list_data
		$datas = new RedboxDataContainer();
		$datas->url = $url;
		$datas->author_picture = new RedboxPictureDataContainer();
				
		// if still no origin, get the origin url
		if (trim($datas->origin)==""){
			$parsed =  parse_url($url);
			$base_url=$parsed['host'];
			$datas->origin = str_replace('www.','',$parsed['host']);
		}
		
		//first, check if a picture is in the url
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG|image|IMAGE)/', $url, $matches);
		if ($matches) {
			$datas->pictures[] =  new RedboxPictureDataContainer($matches[0]);
			$pict_idx = (count($datas->pictures)-1);
			$is_image_url = true;
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			$datas->source = $url;
		}
		
		//////////////////////////////////////////////////////////////
		/// FIX IT !
		// This is a quick fix for CURL error on HTTPS with Vimeo
			preg_match('/[^\?]+(vimeo.com)[^\?]/', $url, $matches);
			if ($matches) {
				$url = str_replace("https://","http://",$url);
			}
		/////////////////////////////////////////////////////////////

		// if it's not a simple picture, let's go deeper in HTML code exploration
		if (!$is_image_url){
			// get the HTML response from the url (HTML code and DOMdoc)			
			libxml_use_internal_errors(true);
			$doc = new DomDocument();
			$html = $this->fetchUrl($url);
			if (!$html) return null;
		}
		if (!$is_image_url && $html){
		
			if ($forced_type){
				$datas->type = $forced_type;
				$datas->category = $this->categories[$forced_type];
			}
			
			$doc->loadHTML($html);
						
			$xpath = new DOMXPath($doc);

			// get the icon for the author picture if no exists
			if (trim($datas->author_picture->url)==""){
				$metas = $doc->getElementsByTagName('link');
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if($meta->getAttribute('rel') == 'shortcut icon'){
						$fav_icon = $meta->getAttribute('href');
						if (strstr(trim($fav_icon),"http")){
							$fav_icon = $fav_icon;
						}else{
							$fav_icon = 'http://'.$base_url.'/'.$fav_icon;
						}
						$datas->author_picture = new RedboxPictureDataContainer($fav_icon);
						break;
					}
				}
			}

			// if we don't have title, take ifr from the title HTML TAG
			if (trim($datas->title) ==""){
				$nodes = $doc->getElementsByTagName( "title" );
				$datas->title =  $this->convertedString($nodes->item(0)->nodeValue);
			}

			// look for title from the website's OpenGraph meta data
			if (trim($datas->title) ==""){
				$query = '//*/meta[starts-with(@property, \'og:title\')]';
				$metas = $xpath->query($query);
				foreach ($metas as $meta) {
					if ($meta->getAttribute('property') == "og:title"){
						$datas->title = $this->convertedString($meta->getAttribute('content'));
						break;
					}
				}
			}
			
			// check if we have a redirected content
			preg_match('!https?://[\S]+!', $datas->title, $match);
			if ($match && count($match)>0 && $redir<=MAX_REDIR) 
				return $this->get_datas_from_url($match[0],$redir++);

			// let's look for pictures !
			$picture_url = "";
			$picture_title = "";
			$og_pictures = array();
			$html_pictures = array();
			
			// look for picture from the website's OpenGraph meta data
			$query = '//*/meta[starts-with(@property, \'og:image\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:image"){
					$picture_url = $meta->getAttribute('content');
					$tmp_url = explode("?",$picture_url);
					$picture_url = $tmp_url[0];
					$dimensions = null;
					$dimensions = $this->redbox_getimagesize( $picture_url );
					if ($dimensions[0] >= MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML 
					&& $dimensions[1] >= MIN_HEIGHT_OF_PICTURES_IF_SEEKED_IN_HTML){
						$og_pictures[] = new RedboxPictureDataContainer($picture_url,$dimensions);
						$pict_idx = (count($og_pictures)-1);			
						if ($og_pictures[$pict_idx]) 
							$og_pictures[$pict_idx]->title = $datas->title . " " .count($og_pictures);
					}
				}
			}

			if (count($og_pictures) ==0){
				// look for picture from the website's twitter meta data
				$query = '//*/meta[starts-with(@property, \'twitter:image:src\')]';
				$metas = $xpath->query($query);
				foreach ($metas as $meta) {
					if ($meta->getAttribute('property') == "twitter:image:src"){
						$picture_url = $meta->getAttribute('content');
						$tmp_url = explode("?",$picture_url);
						$picture_url = $tmp_url[0];
						$dimensions = null;
						$dimensions = $this->redbox_getimagesize($picture_url);
						if ($dimensions[0] >= MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML 
						&& $dimensions[1] >= MIN_HEIGHT_OF_PICTURES_IF_SEEKED_IN_HTML){
							$og_pictures[] = new RedboxPictureDataContainer($picture_url,$dimensions);
							$pict_idx = (count($og_pictures)-1);			
							if ($og_pictures[$pict_idx]->title)  
								$og_pictures[$pict_idx]->title = $datas->title . " " .count($og_pictures);
						}
					}
				}
			}
			
			if (count($og_pictures) ==0 || $datas->type=='gallery' || $with_gallery){
				// get the "big" pictures in html (width>400px)
				$metas = $doc->getElementsByTagName('img');
				$nb_pict = 0;
				$rejected_pict = array();
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if (trim($meta->getAttribute('src')) !=''){
						$picture_url = trim($meta->getAttribute('src'));
						$picture_url = str_replace('../','',$picture_url);
						if (strpos($picture_url,"http")==0 && strstr($picture_url,"http")!=''){
							$picture_url = $picture_url;
						}
						else{
							if (strpos($picture_url,"//")==0 && strpos($picture_url,"//")!==false){
								$picture_url = 'http:'.$picture_url;
							}
							else{
								$picture_url = 'http://'.$base_url.'/'.$picture_url;
							}
						}
					
						preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG|image|IMAGE)/', $picture_url, $matches);
					
						// check if it's not a double
						$exists = false;
						foreach($datas->pictures as $exists_pict){
							if ($exists_pict->url == $picture_url) {
								$exists = true;
								break;
							}
						}
						foreach($rejected_pict as $exists_pict){
							if ($exists_pict == $picture_url) {
								$exists = true;
								break;
							}
						}
						// add the picture url to our list
						if (!$exists && $matches){
							$dimensions = null;
							$dimensions = $this->redbox_getimagesize( $picture_url );
							if ($dimensions[0] >= MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML 
							&& $dimensions[1] >= MIN_HEIGHT_OF_PICTURES_IF_SEEKED_IN_HTML){
								$picture_title = $meta->getAttribute('title');
								if (trim($picture_title)=='')  
									$picture_title = $datas->title . " " .(count($og_pictures) + count($html_pictures));
								
								$nb_pict++;
								$html_pictures[] = new RedboxPictureDataContainer($picture_url,$dimensions);
								$pict_idx = (count($html_pictures)-1);
								$html_pictures[$pict_idx]->title = $picture_title;

								if ($datas->type=='gallery' || $with_gallery){
									if ($nb_pict == NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML_FOR_GALLERIES) break;
								}
								else{
									if ($nb_pict == NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML) break;
								}
							}
							else{
								$rejected_pict[]= $picture_url;
							}
						}
					}
				}
			}
			
			// add founded pictures in our data container
			foreach ($og_pictures as $picture){
				$picture->in_gallery = $with_gallery;
				$datas->pictures[] = $picture;
			}
			foreach ($html_pictures as $picture){
				$picture->in_gallery = $with_gallery;
				$datas->pictures[] = $picture;
			}
			
			// check for embed link for a video in the OpenGraph meta data
			$query = '//*/meta[starts-with(@property, \'og:video\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:video"){
					$datas->type = "video";
					$datas->video_datas = $this->get_video_data_from_provider($url);
					if (trim($datas->video_datas->duration) ==""){
						$query = '//*/meta[starts-with(@property, \'video:duration\')]';
						$metas = $xpath->query($query);
						foreach ($metas as $meta) {
							if ($meta->getAttribute('property') == "video:duration"){
								$datas->video_datas->duration = $meta->getAttribute('content');
								break;
							}
						}
					}
					if ($datas->video_datas->duration > MAX_SECONDS_FOR_SHORT_VIDEO_CATEGORY){
						$datas->category = $this->categories["documentary"];
					}
					else{
						$datas->category = $this->categories["short_video"];
					}
					if (trim($datas->origin)==""){
						$datas->origin = $datas->video_datas->type;
					}
					if (trim($datas->video_datas->title)!=""&&trim($datas->title)==""){
						$datas->title = $datas->video_datas->title;
					}
				}
				else{
				
				}
				if (trim($datas->author_url) ==""){
					$query = '//*/meta[starts-with(@property, \'og:video:director\')]';
					$metas = $xpath->query($query);
					foreach ($metas as $meta) {
						if ($meta->getAttribute('property') == "og:video:director"){
							$datas->author_url = $meta->getAttribute('content');
							break;
						}
					}
				}
			}

			// check for description in the OpenGraph meta data
			$query = '//*/meta[starts-with(@property, \'og:description\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:description"){
					$tmp_description = $this->convertedString($meta->getAttribute('content'));
					break;
				}
			}
			
			// check for description in the Twitter meta data
			if (trim($tmp_description) ==""){
				// check for description in the OpenGraph meta data
				$query = '//*/meta[starts-with(@property, \'twitter:description\')]';
				$metas = $xpath->query($query);
				foreach ($metas as $meta) {
					if ($meta->getAttribute('property') == "twitter:description"){
						$tmp_description = $this->convertedString($meta->getAttribute('content'));
						break;
					}
				}
			}
			
			// check for description in the classic meta data
			if (trim($tmp_description) ==""){
			$metas = $doc->getElementsByTagName('meta');
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if($meta->getAttribute('name') == 'description' || $meta->getAttribute('name') == 'Description')
						$tmp_description = $this->convertedString($meta->getAttribute('content'));
				}
			}
			
			// check in the html content tags for the first "big" string (more than 20 words)
			$p_tag_description = "";
			$metas = $doc->getElementsByTagName('p');
			for ($i = 0; $i < $metas->length; $i++)	{
				$meta = $metas->item($i);
				$text = $meta->textContent;
				if (str_word_count($text) > MIN_WORDS_FOR_DESCRIPTION_IN_HTML_TAGS){
					if ($datas->origin=='youtube.com'){
						$p_tag_description = $this->convertedString(strip_tags(br2nl($meta->content)));
					}
					else{
						$p_tag_description = $this->convertedString($text);
					}
					break;
				}
			}
			
			// if still no description, get the video default description
			if (trim($datas->video_datas->description)!=""&&trim($tmp_description)==""){
				$tmp_description = $datas->video_datas->description;
			}
			
			if (substr($tmp_description,-3)=="..."){
				$tmp_description = substr($tmp_description,0,(strlen($tmp_description)-4));
			}
			
			if (substr($datas->description,-3)=="..."){
				$datas->description = substr($datas->description,0,(strlen($datas->description)-4));
			}
			
			// check if we had a description before this function execution (ex: facebook give us his description)
			if (((stripos($datas->description,$tmp_description)>=0) && (strlen($tmp_description) > strlen($datas->description) )) 
			|| trim($datas->description) == ""){
				$datas->description = $tmp_description;
			}
			
			// check if the video description from provider is the same but better (same first words but more words)
			if (((stripos($datas->video_datas->description,$tmp_description)>=0) && (strlen($datas->video_datas->description) > strlen($tmp_description) )) 
			|| trim($datas->description) == ""){
				$datas->description = $datas->video_datas->description;
				$datas->video_datas->description="";
			}
			
			// get description if still none
			if (trim($datas->description)==''){
				$datas->description = $tmp_description;
			}
			
			if (((stripos($p_tag_description,$tmp_description)>=0) && (strlen($p_tag_description) > strlen($tmp_description) )) 
			|| trim($datas->description) == ""){
				$datas->description = $p_tag_description;
			}
			
			// clean video description if it is the same than master description (no need too much text in datas ;-))
			if($datas->video_datas->description==$datas->description) 
				$datas->video_datas->description = "";
			
			// get a title if still none
			if (trim($datas->title)=="" && trim($datas->description)!=''){
				preg_match('/^(?>\S+\s*){1,MAX_WORDS_FOR_TITLE_GENERATED}/', $datas->description, $match);
				if (trim($match[0])!='') $datas->title = $match[0]."...";
			}
			
			// get a title if still none
			if (trim($datas->title)=="" && trim($datas->message)!=''){
				preg_match('/^(?>\S+\s*){1,MAX_WORDS_FOR_TITLE_GENERATED}/', $datas->message, $match);
				if (trim($match[0])!='') $datas->title = $match[0]."...";
			}
			
			// clean the title from some basic sites datas
			$tmp_url = explode("|",$datas->title);
			$datas->title = $tmp_url[0];
			
			// check for author_name in the classic meta data
			if (trim($datas->author_name) ==""){
			$metas = $doc->getElementsByTagName('meta');
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if($meta->getAttribute('name') == 'author')
						$datas->author_name = $meta->getAttribute('content');
				}
			}
			
			// check for author_name in OG meta datas
			if (trim($datas->author_name) ==""){
				$query = '//*/meta[starts-with(@property, \'og:site_name\')]';
				$metas = $xpath->query($query);
				foreach ($metas as $meta) {
					if ($meta->getAttribute('property') == "og:site_name"){
						$datas->author_name = $meta->getAttribute('content');
						$datas->author_picture->title = $datas->author_name;
						break;
					}
				}
			}
			if (trim($datas->author_picture->title) ==""){
				$datas->author_picture->title = $datas->author_name;
			}
			if (trim($datas->video_datas->url) == '' && !$forced_type){
				$datas->type = "article";
				$datas->category = $this->categories["article"];
			}
			
			foreach($this->redbox->configuration->crowdfundings as $replace){
				if (strstr($url,$replace)){
					$datas->type = "crowdfunding";
					$datas->category = "Crowdfunding";
				}
			}
			
			$datas->source = $url;
			
			// if we have a video rejected/blocked by Youtube, return null... it will be trashed
			if ($datas->type == "article" && trim($datas->title)=="YouTube"){
				return null;
			}
		}
				
		// feed the list_data and return it
		$this->list_datas[]= $datas;
		return $datas;
	}

	private function get_video_data_from_provider($url,$video_datas=null){
		
		if (!$video_datas) $video_datas = new RedboxVideoDataContainer();
		$id="";
		$title="";
		$description="";
		$keywords="";

		$embed=$url;
	
		// get type for the video we actually support in this function
		if(mb_eregi("youtube",$url))			$type="youtube";
		else if(mb_eregi("dailymotion",$url))	$type="dailymotion";
		else if(mb_eregi("google",$url))		$type="google";
		else if(mb_eregi("vimeo",$url))		$type="vimeo";
		else if(mb_eregi("canalplus",$url))		$type="canalplus";
		else return false;
	
		//Get the unique ID of the video :
		if($type=="youtube"){
			$debut_id = explode("v=",$url,2);
			if (array_key_exists(1,$debut_id)){
				$id_et_fin_url = explode("&",$debut_id[1],2);
				$id = $id_et_fin_url[0];
			}
			if ($id==""){
				$debut_id = explode("/v/",$url,2);
				$id_et_fin_url = explode("&",$debut_id[1],2);
				$id = $id_et_fin_url[0];
			}
			$tmp_id = explode("?",$id);
			$id = $tmp_id[0];
		}
		else if($type=="dailymotion"){
			$debut_id = explode("/video/",$url,2);
			$id_et_fin_url = explode("_",$debut_id[1],2);
			$id = $id_et_fin_url[0];
			$tmp_id = explode("?",$id);
			$id = $tmp_id[0];
		}
		else if($type=="google"){
			$debut_id =  explode("docid=",$url,2);
			$id_et_fin_url = explode("&",$debut_id[1],2);
			$id = $id_et_fin_url[0];
		}
		else if($type=="vimeo"){
			$l_id= eregi("([0-9]+)$",$url,$lid);
			$id = $lid[0];
		}
		else if($type=="canalplus"){
			$debut_id =  explode("vid=",$url,2);		
			$id_et_fin_url = explode("&",$debut_id[1],2);
			$id = $id_et_fin_url[0];
		}
	
		// get datas we can retrieve from provider page
		if($type=="youtube"){
			$xml = @file_get_contents("http://gdata.youtube.com/feeds/api/videos/".$id);		
			//title
			preg_match('#<title(.*?)>(.*)<\/title>#is',$xml,$resultTitre);
			$titre = $resultTitre[count($resultTitre)-1];
			//description
			preg_match('#<content(.*?)>(.*)<\/content>#is',$xml,$resultDescription);
			if (array_key_exists((count($resultDescription)-1),$resultDescription)){
				$description = $resultDescription[count($resultDescription)-1];
			}
			// vues
			preg_match('#viewCount=\'(.*)\'#is',$xml,$resultviewCount);
			$vues = $resultviewCount[count($resultviewCount)-1];
			// duree
			//preg_match('#duration=\'(.*)\'',$xml,$resultduration);
			$doc = new DOMDocument;
			$doc->load("http://gdata.youtube.com/feeds/api/videos/".$id);
			if ($doc->getElementsByTagName('duration')->item(0))
				$duration = $doc->getElementsByTagName('duration')->item(0)->getAttribute('seconds');		
			// keywords
			preg_match('#<media:keywords>(.*)</media:keywords>#is',$xml,$resultKeywords);
			if (array_key_exists((count($resultKeywords)-1),$resultKeywords))
			$keywords= $resultKeywords[count($resultKeywords)-1];
			//Image
			$img = "http://img.youtube.com/vi/".$id."/1.jpg";
			//Code HTML
			$embed="http://www.youtube.com/embed/".$id;
			$code = 
				'<object ><param name="movie"' .
				' value="http://www.youtube.com/v/'.$id.
				'&hl=fr"></param><param name="wmode" value="transparent"></param><embed' .
				' src="http://www.youtube.com/v/'.$id.
				'&hl=fr" type="application/x-shockwave-flash" wmode="transparent"></embed></object>';
		}
		else if ($type=="dailymotion"){
			$viewsRegexp = '#<b class="video_views_value">(.*)</b>#U';
			$viewsCapture = preg_match( $viewsRegexp, @file_get_contents( "http://www.dailymotion.com/video/".$id ), $viewsBuffer );
			$viewsCapture = (int) str_replace( ' ', '', $viewsBuffer[1] );  
			$vues = $viewsCapture;
			$tags = get_meta_tags("http://www.dailymotion.com/video/".$id);	
			//titre
			$titre = htmlspecialchars(trim(str_replace("Dailymotion -","",$tags["title"])));
			//description
			$description = $tags["description"];
			//mots clés
			$keywords = $tags["keywords"];
			//image 
			$img = "http://www.dailymotion.com/thumbnail/160x120/video/".$id;
			$embed = "http://www.dailymotion.com/embed/video/".$id;
			// code HTML
			$code = 
				'<div><object width="420" height="357"><param name="movie"' .
				' value="http://www.dailymotion.com/swf/'.$id.
				'&v3=1&related=1"></param><param name="allowFullScreen"' .
				' value="true"></param><param name="allowScriptAccess" value="always"></param>' .
				'<embed src="http://www.dailymotion.com/swf/'.$id.
				'&v3=1&related=1" type="application/x-shockwave-flash" width="420"' .
				' height="357" allowFullScreen="true" allowScriptAccess="always"></embed></obj' .
				'ect></div>';
		}
		else if ($type=="google"){
			$xml_string = @file_get_contents(
			"http://video.google.com/videofeed?docid=".$id);
			//echo htmlspecialchars($xml_string);
			//titre
			$xml_title_debut = explode("<title>",$xml_string,2);
			$xml_title_fin = explode("</title>",$xml_title_debut[1],2);
			$titre = $xml_title_fin[0];
			//description
			$xml_description_debut = explode("<description>",$xml_string,2);
			$xml_description_fin = explode("</description>",$xml_description_debut[1],2);
			$description = $xml_description_fin[0];
			//image
			$xml_image_debut = explode('&lt;img src="',$xml_string,2);
			$xml_image_fin = explode('" width="',$xml_image_debut[1],2);
			$img = $xml_image_fin[0];
			//code HTML 
			$code = 
			'<embed style="width:400px; height:326px;" id="VideoPlayback"' .
			' type="application/x-shockwave-flash" src="http://video.google.com/googleplay' .
			'er.swf?docId='.$id.'&hl=fr" flashvars=""> </embed>';
		}
		else if ($type=="vimeo"){
			$xml_string = @file_get_contents("http://vimeo.com/api/v2/video/".$id.".xml");
			//titre
			$xml_title_debut = explode("<title>",$xml_string,2);
			$xml_title_fin = explode("</title>",$xml_title_debut[1],2);
			$titre = $xml_title_fin[0];
			//description
			$xml_description_debut = explode("<caption>",$xml_string,2);
			$xml_description_fin = explode("</caption>",$xml_description_debut[1],2);
			$description = $xml_description_fin[0];
			// mots clés
			$xml_tags_debut = explode("<tags>",$xml_string,2);
				$xml_tags_fin = explode("</tags>",$xml_tags_debut[1],2);
			$keywords = $xml_tags_fin[0];
			// nombres de vues
			$xml_vues_debut = explode("<stats_number_of_plays>",$xml_string,2);
				$xml_vues_fin = explode("</stats_number_of_plays>",$xml_vues_debut[1],2);
			$vues = $xml_vues_fin[0];
			//image
			$xml_image_debut = explode("<thumbnail_large>",$xml_string,2);
			$xml_image_fin = explode("</thumbnail_large>",$xml_image_debut[1],2);
			$img = $xml_image_fin[0];
		
			$embed="http://player.vimeo.com/video/".$id;		
			//code HTML
			$xml_code = @file_get_contents("http://vimeo.com/api/oembed.xml?url=http%3A//vimeo.com/".$id);
			$xml_code_debut = explode("<html>",$xml_code,2);
			$xml_code_fin = explode("</html>",$xml_code_debut[1],2);
			$code =  html_entity_decode(str_replace("<![CDATA[","",str_replace("]]>","",$xml_code_fin[0])));
		}	
		else if ($type=="canalplus"){
			$embed="http://player.canalplus.fr/embed/flash/player.swf?videoId=".$id;
		}
		
		// try last catch in OG tags...
		
		
		switch ($type){
			case "youtube" :
				$shortcode = "[".$type."=".$url."]";
				break;
			case "dailymotion" : 
				$shortcode = "[".$type." id=".$id."]";
				break;
			case "vimeo" :
				$shortcode = "[".$type." ".$id."]";
				break;
			case "soundcloud" :
				$shortcode = "[".$type." url=".$url."]";
				break;
		}
	
		$tmp_url = explode("?",$embed);
		$embed = $tmp_url[0];
		if (trim($embed!=""))	$embed = '<iframe src="'.$embed.'" frameborder="0" allowfullscreen=""></iframe>';
		
		// feed and return our video container object
		$video_datas->url = $url;	
		$video_datas->id = $id;
		$video_datas->type = $type;
		$video_datas->title=$titre;
		$video_datas->description=$description;
		$video_datas->img= new RedboxPictureDataContainer($img);
		$video_datas->code=$code;
		$video_datas->views=$vues;
		$video_datas->keywords=$keywords;
		$video_datas->duration=$duration;
		$video_datas->embed=$embed;
		$video_datas->shortcode=$shortcode;
		return $video_datas;
	}
	
	// get a single data container with an automatic proposition for import
	public function get_proposed_import($list_datas=null){
		if ($list_datas!=null) $this->list_datas = $list_datas;
		
		if (!$this->list_datas || count($this->list_datas) == 0) return null;
		
		$proposed = new RedboxDataContainer();
		$titles = array();
		$content="";
		if ($this->list_datas==null || count($this->list_datas)<=0) {
			$proposed->short_description = REDBOX_INVALID_PROPOSITION;
			return $proposed;
		}
		$proposed->short_description=null;
		$proposed->message='';
		// check if we have messages 
		foreach($this->list_datas as $datas){
			if ($datas->message != ''){
				if (!(stripos($datas->description,"<!--more-->") > 0)){
					$description = $datas->message;
					$phrases = explode("\n",$description);
					if (count($phrases)>0){
						$afterMore = "";
						if (str_word_count($phrases[0]) < 40) {
							$beforeMore = "";
							foreach($phrases as $phrase) {
								if (str_word_count($beforeMore) < 40) {
									$beforeMore.= $phrase."\n";
								} else {
									$afterMore.= $phrase."\n";
								}
							} 
						} else { 
							$beforeMore = $phrases[0];
							$afterMore = str_replace($beforeMore,"",$description);
						}
						$description = $beforeMore."\n<!--more-->\n".$afterMore;
					} else {
						$description = $description."\n<!--more-->\n";
					}
					$content.= processString($description);
				}
				else{
					$content.= processString($datas->message) ."<br />";
				}
				if (trim($datas->fb_id) != '')
					$content.= $this->get_human_link($datas);
				$proposed->message.= $datas->message."<br /><br />";
				
				if (trim($datas->fb_id) != ''){
					$proposed->title = $datas->title;
				}
			}
		}
				
		$to_check = array('gallery','video','article','picture','crowdfunding');
		// check in order to put "in front" the best datas
		foreach($to_check as $check){
			foreach($this->list_datas as $datas){
				if ($datas->type == $check && (trim($datas->fb_id) == '' || (trim($datas->fb_id) != '' && $datas->type == 'gallery'))){
					if ($datas->type != 'video' || ($datas->type == 'video' && trim($datas->video_datas->embed) !='')){
						$proposed->type = $datas->type;
						$proposed->category = $datas->category;
						$proposed->source = $datas->source;
						$proposed->url = $datas->url;
						$proposed->origin = $datas->origin;
						if (!$proposed->title || trim($proposed->title)=='')
							$proposed->title = $datas->title;
						if (!$proposed->short_description)  $proposed->short_description = $datas->short_description;
						$proposed->created = $datas->created;
						$proposed->author_name = $datas->author_name;
						$proposed->author_url = $datas->author_url;
						$proposed->author_picture = $datas->author_picture;
						$proposed->video_datas = $datas->video_datas;
						$proposed->video_datas->shortcode = $datas->video_datas->shortcode;
						$proposed->video_datas->views = $datas->video_datas->views;
						$proposed->video_datas->duration = $datas->video_datas->duration;
						$proposed->video_datas->embed = $datas->video_datas->embed;
						if ($proposed->video_datas->img->width != 0)
							$proposed->pictures[] = $proposed->video_datas->img;
						if (trim($datas->description)!='')
							$content.= "<blockquote>".processString($datas->description)."</blockquote>";
						if (trim($datas->fb_id) == '')
							$content.= $this->get_human_link($datas);
						break;
					}
				}
				if ($proposed->type!='') break;
			}
			if ($proposed->type!='') break;
		}
		// get a title if none
		if (trim($proposed->title)==''){
			foreach($this->list_datas as $datas){
				if (trim($datas->title)!=''){
					$proposed->title=$datas->title;
					break;
				}
			}
		}
		
		if (trim($this->list_datas[0]->fb_id) != ''){
			$proposed->title = $this->list_datas[0]->title;
		}
		
		// add all secondary datas to the proposal
		foreach($this->list_datas as $datas){
			if ($proposed->source!=$datas->source && $datas->fb_id==''){
				if (trim($datas->description)!='') {
					if (trim($datas->title)!='') $content.= "<h5>".$datas->title."</h5><br />";
					$content.= "<blockquote>".processString($datas->description)."</blockquote><br />";
				}
				$content.= $this->get_human_link($datas);
			}
			// we'll add picture type after all to put it "in front"
			if ($datas->type != 'picture' && $datas->type != 'gallery'){
				$proposed->pictures = array_merge($proposed->pictures,$datas->pictures);
			}
		}
		// check if we have a picture in sources for main picture and put it in front
		$i=0;
		foreach($this->list_datas as $datas){
			$i++;
			if (($datas->type == 'picture') || ($datas->type == 'gallery' && $i==1)){ //only the first gallery
				$proposed->pictures = array_merge($datas->pictures,$proposed->pictures);
				$skip_reorder = true;
			}
		}
		// delete clones
		for($i=0;$i<count($proposed->pictures);$i++){
			for($j=($i+1);$j<count($proposed->pictures);$j++){
				if($proposed->pictures[$i]->url==$proposed->pictures[$j]->url) {
					unset($proposed->pictures[$j]);
				}
			}
		}
		
		if (!$skip_reorder) $proposed = $this->reorder_pictures_in_datas($proposed);
		
		$proposed->description = $content;
		// last check for facebook datas
		$i=0;
		foreach($this->list_datas as $datas){
			$proposed->title = str_replace(" - ".$datas->author_name,'',$proposed->title);
			if (trim($datas->fb_id) != ''){
				$proposed->fb_id = $datas->fb_id;
				$proposed->created = $datas->created;
				$proposed->fb_id_author = $datas->fb_id_author;
				$proposed->author_name = $datas->author_name;
				$proposed->author_url = $datas->author_url;
				$proposed->author_picture = $datas->author_picture;
				$checkdate = getdate(strtotime(stripslashes($datas->created)));
				if ($checkdate['hours'] >= 20 && $checkdate['hours'] <= 23 && $proposed->type=='video'){
					$proposed->type = "video";
					$proposed->category = "Culture";
					for($i=0;$i<count($this->list_datas);$i++){
						$this->list_datas[$i]->type = $proposed->type;
						$this->list_datas[$i]->category = $proposed->category;
					}
				}
				break;
			}
			$i++;
		}
		$proposed->short_description = $this->cleanText($proposed->short_description);
		$proposed->description = $this->cleanText($proposed->description);
		$proposed->message = $this->cleanText($proposed->message);
		// clean title
		$proposed->title = $this->cleanTitle($proposed->title);
		$proposed->title = str_replace(" - ".$proposed->origin,'',$proposed->title);
		$proposed->title = str_replace(" sur ".$proposed->origin,'',$proposed->title);
		$proposed->title = str_replace(" on ".$proposed->origin,'',$proposed->title);
		$proposed->title = str_replace(" | ".$proposed->origin,'',$proposed->title);
		$proposed->title = str_replace(" - ".$proposed->author_name,'',$proposed->title);
		$proposed->title = str_replace(" sur ".$proposed->author_name,'',$proposed->title);
		$proposed->title = str_replace(" on ".$proposed->author_name,'',$proposed->title);
		$proposed->title = str_replace(" | ".$proposed->author_name,'',$proposed->title);
		return $proposed;
	}

	private function get_human_link($datas){
		
		
		if (trim($datas->fb_id) != '' && trim($datas->author_name) != ''){
			$url = $datas->url;
			if (trim($datas->type)!='gallery')
				$message = REDBOX_REED_FACEBOOK_POST;
			else 
				$message = REDBOX_WATCH_FACEBOOK_GALLERY;
		}
		else{
			$url = $datas->source;
			switch ($datas->type){
				case 'video':
					$message = REDBOX_WATCH_VIDEO;
					break;
				case 'article':
					$message = REDBOX_REED_ARTICLE;
					break;
				case 'picture':
					$message = REDBOX_SEE_PICTURE;
					break;
				case 'gallery':
					$message = REDBOX_WATCH_GALLERY;
					break;
				case 'crowdfunding':
					$message = REDBOX_REED_CROWDFUNDING;
					break;
				default:
					$message = REDBOX_REED;
					break;
			}
			if (trim($datas->description)=='') {
				if (trim($datas->title)!='') $message.= ' "'.$datas->title.'" ';
			}
		}
		
		if(trim($datas->author_name) != '' && trim($datas->author_name) != trim($datas->origin) && $datas->type!="crowdfunding") {
			$title= $datas->author_name;
			$message.= ' '._OF;
		}
		else{
			$title= $datas->origin;
			$message.= ' '._ON;
		}
		
		if (trim($datas->author_picture->url)!=''){
			$image = '<img src="'.$datas->author_picture->url.'" />';
		}
		else{
			if ($datas->type=='video') {
				$icon = 'icon-film';
			}
			elseif ($datas->type=='article') {
				$icon = 'icon-file-alt';
			}
			elseif ($datas->type=='picture'||$datas->type=='gallery') {
				$icon = 'icon-picture';
			}
			else{
				$icon = 'icon-file-alt';
			}
			$image = '<i class="'.$icon.'"></i>&nbsp;';
		}
		$link = $message . " <a target='_blank' href='".$url."' >". $title ."</a>";
		$link = '<span class="redbox_human_link">'.$image.$link.'</span><br /><br />';
		return $link;
	}

	private function clean_descriptions(){
		// first, clean for video description that could be sames
		for($i=0;$i<count($this->list_datas);$i++){
			if ($better = $this->get_better_description($this->list_datas[$i]->description,$this->list_datas[$i]->video_datas->description)){
				$this->list_datas[$i]->description = $better;
				$this->list_datas[$i]->video_datas->description = "";
			}
		}
		// recheck all principals descriptions 
		for($i=0;$i<count($this->list_datas);$i++){
			for($j=($i+1);$j<count($this->list_datas);$j++){
				if ($better = $this->get_better_description($this->list_datas[$i]->description,$this->list_datas[$j]->description)){
					$this->list_datas[$i]->description = $better;
					$this->list_datas[$j]->description = "";
				}
			}
		}

		// clean clones
		for($i=0;$i<count($this->list_datas);$i++){
			for($j=($i+1);$j<count($this->list_datas);$j++){
				if ($this->list_datas[$i]->description == $this->list_datas[$j]->description){
					$this->list_datas[$j]->description = "";
				}
				if ($this->list_datas[$i]->message == $this->list_datas[$j]->message){
					$this->list_datas[$j]->message = "";
				}
			}
		}

		// clean clones message = description
		for($i=0;$i<count($this->list_datas);$i++){
			for($j=0;$j<count($this->list_datas);$j++){
				if ($this->list_datas[$j]->description == $this->list_datas[$i]->message){
					$this->list_datas[$j]->description = "";
				}
			}
		}

		// remove url from text if we found it in the text
		for($i=0;$i<count($this->list_datas);$i++){
			for($j=0;$j<count($this->list_datas);$j++){
				if (trim($this->list_datas[$j]->source)!=''){
					foreach($this->redbox->configuration->sub_replace_source as $replace){
						$to_replace = $replace.$this->list_datas[$j]->source;
						$this->list_datas[$i]->message  = str_replace($to_replace,'',$this->list_datas[$i]->message);
						$this->list_datas[$i]->description  = str_replace($to_replace,'',$this->list_datas[$i]->description);
					}
				}
			}
			foreach($this->redbox->configuration->to_clean_in_urls as $replace){
				$this->list_datas[$i]->message  = str_replace($replace,'',$this->list_datas[$i]->message);
				$this->list_datas[$i]->description  = str_replace($replace,'',$this->list_datas[$i]->description);
			}
		}
		for($i=0;$i<count($this->list_datas);$i++){
			for($j=0;$j<count($this->list_datas);$j++){
				foreach($this->redbox->configuration->sub_replace_source as $replace){
					if ($replace != "\n"){
						$this->list_datas[$i]->message  = str_replace($replace,'',$this->list_datas[$i]->message);
						$this->list_datas[$i]->description  = str_replace($replace,'',$this->list_datas[$i]->description);
					}
				}
			}
		}
		// get a short description
		for($i=0;$i<count($this->list_datas);$i++){
			if (trim($this->list_datas[$i]->description)!=''){
				preg_match('/^(?>\S+\s*){1,30}/', $this->list_datas[$i]->description, $match);
				$this->list_datas[$i]->short_description = $match[0]."...";
			}
		}
	}
	
	// get the berrer description if they are the sames but one is longer
	private function get_better_description($description_a,$description_b){
		if ((stripos($description_a,$description_b)>=0) && (strlen($description_a) > strlen($description_b) )) {
			return $description_a;
		}
		if ((stripos($description_b,$description_a)>=0) && (strlen($description_b) > strlen($description_a) )) {
			return $description_b;
		}
		return null;
	}

	// reorder pictures for datas from the higher width to the lower
	private function reorder_pictures(){
		for($i=0;$i<count($this->list_datas);$i++){
			if ($this->list_datas[$i]->type!='gallery'){
				for($j=0;$j<count($this->list_datas[$i]->pictures);$j++){
					for($k=($j+1);$k<count($this->list_datas[$i]->pictures);$k++){
						if ($this->list_datas[$i]->pictures[$k]->width > $this->list_datas[$i]->pictures[$j]->width){
							$tmp_pict = $this->list_datas[$i]->pictures[$j];
							$this->list_datas[$i]->pictures[$j] = $this->list_datas[$i]->pictures[$k];
							$this->list_datas[$i]->pictures[$k] = $tmp_pict;
						}
					}
				}
			}
		}
	}
	
	// reorder pictures for datas from the higher width to the lower
	private function reorder_pictures_in_datas($datas){
		if ($datas->type!='gallery'){
			for($j=0;$j<count($datas->pictures);$j++){
				for($k=($j+1);$k<count($datas->pictures);$k++){
					if ($datas->pictures[$k]->width > $datas->pictures[$j]->width){
						$tmp_pict = $datas->pictures[$j];
						$datas->pictures[$j] = $datas->pictures[$k];
						$datas->pictures[$k] = $tmp_pict;
					}
				}
			}
		}
		return $datas;
	}
		
	
	// curl fetch an url ...
	public function fetchUrl($url){
		//$url.='/';
		$ch = curl_init();
		
		$this->redbox->fallBack=false;
		
		curl_setopt($ch, CURLOPT_URL, $url);
		//curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		//You may need to add the line below
		curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_ENCODING ,"");
		curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
		
		// TODO CHECK FOR REDIRECT
		$feedData = curl_exec($ch);
		//$feedData = file_get_contents($url);
		
		if (trim($feedData)!=''){
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);		
			//echo $url . " -> " . $httpCode . "<br />";
			if($httpCode == 404) {
				$feedData = null;
			}
			else{
				$this->contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
			}
		}
		// let's try in fallback mode 
		elseif ($this->redbox->configuration->fallBackUrl){
			$this->redbox->fallBack=true;
			$post = 'url='.$url.'&fb_config='.serialize($this->redbox->configuration->fb_config).'&categories='.serialize($this->redbox->configuration->categories);
			$curl = curl_init();
			curl_setopt($curl, CURLOPT_URL, $this->redbox->configuration->fallBackUrl);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_FAILONERROR, true);
			curl_setopt($curl, CURLOPT_MAXREDIRS, 5);
			curl_setopt($curl, CURLOPT_TIMEOUT, 5);
			curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
			$feedData = curl_exec($curl);
			curl_close($curl);
			$list_datas = unserialize($feedData);
			foreach($list_datas as $datas) {
				$datas->fallBack = true;
				$this->list_datas[]=$datas;
			}
			$feedData = null;
		}
		else{
			$feedData = null;
		}
		curl_close($ch); 
		return $feedData;
	}
	
	public function redbox_getimagesize($url){
		//$image = new FastImage($url);
		//$dim = $image->getSize();
		$dim = getimagesize($url);
		//echo $url.' - '.$dim[0].' - '.$dim[1].'<br />';
		//echo '<img src="'.$url.'" /><br />';
		return $dim;
	}

} // END CLASS RedboxDataRetriever

// CLASS RedboxDataContainer
class RedboxDataContainer{
	public function __construct(){
		$this->url = "";
		$this->fallBack = false;
		$this->fb_id = "";
		$this->type = "";		
		$this->category = "";
		$this->source = "";
		$this->title = "";
		$this->origin = "";
		$this->message = "";
		$this->created = "";
		$this->fb_id_author = "";
		$this->author_name = "";
		$this->author_url = "";
		$this->author_picture = array();
		$this->description = "";
		$this->short_description = "";
		$this->pictures = array();
		$this->video_datas = new RedboxVideoDataContainer();
		return $this;
	}
} // END CLASS RedboxDataContainer


// CLASS RedboxVideoDataContainer
class RedboxVideoDataContainer{
	public function __construct(){
		$this->url = "";
		$this->type = "";
		$this->id = "";
		$this->title = "";
		$this->description = "";
		$this->img = new RedboxPictureDataContainer();
		$this->code = "";
		$this->embed = "";
		$this->shortcode = "";
		$this->keywords = "";
		$this->views = "";
		$this->duration = "";
	}
} // END CLASS RedboxVideoDataContainer


///////////////////////////
// description -> generally empty, it contains the "title" tags of the img if found or the Facebook description
// CLASS RedboxPictureDataContainer
class RedboxPictureDataContainer{
	public $url,$title,$width,$height;

	public function __construct($url=null,$dimensions = null,$title=''){
		$this->url = $url;
		$this->ext = "";
		$this->title = $title;
		$this->in_gallery = false;
		if (trim($this->url)!="" && !$dimensions){
			$this->get_size();
		}
		else{ 
			if ($dimensions){
				$this->width = $dimensions[0];
				$this->height = $dimensions[1];
			}
			else{
				$this->width = 0;
				$this->height = 0;			
			}
		}
		return $this;
	}
	
	public function get_size(){
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG|image|IMAGE)/', $this->url, $matches);
		if ($matches) {
			$dimensions = null;
			$dimensions =  RedboxDataRetriever::redbox_getimagesize( $this->url );
			$this->width = $dimensions[0];
			$this->height = $dimensions[1];
		}
		return $this;
	}
} // END CLASS RedboxPictureDataContainer


?>
