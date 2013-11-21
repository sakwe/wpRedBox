<?
/* The RedboxDataRetriever class
 *
 * This PHP class can retrieve base datas from an url ab return a datas objects with : 
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
define ("MAX_WORDS_FOR_TITLE_GENERATED",7);
define ("MIN_WORDS_FOR_DESCRIPTION_IN_HTML_TAGS",25);
define ("NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML",3);
define ("MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML",400);
define ("MAX_SECONDS_FOR_SHORT_VIDEO_CATEGORY",900);
/*/


/* Example for use it width Facebook API capabilites and customised categories
 *
 **
	$categories = array(	'link'=>'Liens',
				'article'=>'Articles',
				'picture'=>'Photos',
				'gallery'=>'Galleries',
				'video'=>'Video',
				'short_video'=>'Courtes vidéos',
				'documentary'=>'Documentaires'
				);
	$fb_config = array('primary_id'=>'YOUR_FACEBOOK_ID','app_id'=>'YOUR_FACEBOOK_APP_ID','app_secret'=>'YOUR_FACEBOOK_APP_SECRET')	
	$dr = new RedboxDataRetriever($categories,$fb_config);	
	$datas = $dr->get_datas($_GET['url']);
	
*****/

//require_once ('/redbox-string-helpers');

// CLASS RedboxDataRetriever
class RedboxDataRetriever{

	public $categories,$fb_config;

	public function __construct($categories=null,$fb_config=null){
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
	}

	public function get_datas($urls,$quick=false){	
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
		$this->clean_descriptions();
		return array_reverse($this->list_datas);
	}
	
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
	
	private function get_datas_from_facebook($id_fb,$quick=false){
		$id_fb = trim($id_fb);
		
		// instantiate a data container that we will add to the list_data
		$datas = new RedboxDataContainer();
		$datas->id_fb = $id_fb;
		$datas->author_picture = new RedboxPictureDataContainer();
		
		// get the data flow from facebook open graph api
		$authToken = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$this->fb_config['app_id']}&client_secret={$this->fb_config['app_secret']}");	
		$json_object = $this->fetchUrl("https://graph.facebook.com/".$id_fb."?{$authToken}");
		$feed_data = json_decode($json_object);

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

		$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->from->id."/photos?fields=source&{$authToken}");
		$author_data = json_decode($json_object);
		foreach ($author_data->data as $author_picture){
			$datas->author_picture = new RedboxPictureDataContainer($author_picture->source);
			$datas->author_picture->title = $datas->author_name;
			break;
		}
		
		// if we found a picture gallery, let's go !
		if ($feed_data->cover_photo != ""){
			// set type and category
			$datas->type = "gallery";
			$datas->category = $this->categories["gallery"];
			$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->cover_photo."?{$authToken}");			
			$cover_data = json_decode($json_object);
			// Get the cover picture for first picture in our object
			$datas->pictures[] = new RedboxPictureDataContainer($cover_data->source);
			$pict_idx = (count($datas->pictures)-1);
			$datas->pictures[$pict_idx]->title = $cover_data->name;
			// if we not ask us a quick check, let's pick all the pictures
			if (!$quick){
				$json_object = $this->fetchUrl("https://graph.facebook.com/".$id_fb."/photos?fields=images,name&{$authToken}");
				$gallery_data = json_decode($json_object);
				foreach ($gallery_data->data as $pictures_data){
					foreach ($pictures_data->images as $picture_data){
						$datas->pictures[] = new RedboxPictureDataContainer($picture_data->source);
						$pict_idx = (count($datas->pictures)-1);
						$datas->pictures[$pict_idx]->title=$pictures_data->name;
						break;
					}
				}
			}
		}
		// it's website link, lets go to complete our datas from the source url 
		elseif ($feed_data->type == "link"){
			$datas->type = "article";
			$datas->category = $this->categories["article"];
			$datas->source = $feed_data->link;
			$list_datas = $this->get_datas_from_url($feed_data->link);
		}
		// it's video provider link, lets go to complete our datas from the source url 
		elseif ($feed_data->type == "video"){
			$datas->source = $feed_data->link;
			$list_datas = $this->get_datas_from_url($feed_data->link);
		}
		// it's a facebook picture item, let's pick it
		elseif ($feed_data->picture != ""){
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			// if we have no source for the picture, recheck API datas from the link
			if (!$feed_data->source){
				$list_datas = $this->get_datas_from_facebook($feed_data->object_id,$quick);
			}
			else{
				$datas->source = $feed_data->source;
				$datas->pictures[] = new RedboxPictureDataContainer($feed_data->source);
				$pict_idx = (count($datas->pictures)-1);
			}
			
			// if it's a facebook photo, but maybe we have a posted website links into the message ?
			// let's complete data with it!
			$an_url = null;
			preg_match_all('!https?://[\S]+!', $datas->message, $match);
			foreach ($match[0] as $an_url) {
				$list_datas = $this->get_datas_from_url($an_url,$quick);
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
		if (trim($datas->title)==""){
			if ($datas->message!="") $base = $datas->message;
			if ($base=="") $base = $datas->description;
			preg_match('/^(?>\S+\s*){1,MAX_WORDS_FOR_TITLE_GENERATED}/', $base, $match);
			$datas->title = $match[0]."...";
		}
		// still no origin for the source?
		if (trim($datas->origin)=="") $datas->origin = "www.facebook.com";
		
		// feed the list_data and return it
		$this->list_datas[] = $datas;
		return $datas;
	}

	private function get_datas_from_url($url,$quick=false){
		
		// we don't know what is behind the url ..
		$is_image_url = false;
		
		// instantiate a data container that we will add to the list_data
		$datas = new RedboxDataContainer();
		$datas->url = $url;
		$datas->author_picture = new RedboxPictureDataContainer();
				
		// if still no origin, get the origin url
		if (trim($datas->origin)==""){
			$parsed =  parse_url($url);
			$datas->origin = $parsed['host'];
		}
		
		//first, check if a picture is in the url
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);
		if ($matches) {
			$datas->pictures[] =  new RedboxPictureDataContainer($matches[0]);
			$pict_idx = (count($datas->pictures)-1);
			$is_image_url = true;
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			$datas->source = $url;
		}
	
		// if it's not a simple picture, let's go deeper in HTML code exploration
		if (!$is_image_url){
			
			// get the HTML response from the url (HTML code and DOMdoc)
			libxml_use_internal_errors(true);
			$doc = new DomDocument();
			$html = $this->fetchUrl($url);
			$doc->loadHTML($html);
			
			// check the caracter coding for html
			$metas = $doc->getElementsByTagName('meta');
			for ($i = 0; $i < $metas->length; $i++)	{
				$meta = $metas->item($i);
				$found = false;
				if(($meta->getAttribute('http-equiv') == 'Content-Type')||($meta->getAttribute('http-equiv') == 'content-type')){
					$found = true;
					break;
				}
			}
			if (!$found) $doc->loadHTML(utf8_decode($html));
			$xpath = new DOMXPath($doc);


			// get the icon for the author picture if no exists
			if (trim($datas->author_picture->url)==""){
				$metas = $doc->getElementsByTagName('link');
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if($meta->getAttribute('rel') == 'shortcut icon'){
						$datas->author_picture = new RedboxPictureDataContainer($meta->getAttribute('href'));
						break;
					}
				}
			}

			// look for title from the website's OpenGraph meta data
			if (trim($datas->title) ==""){
				$query = '//*/meta[starts-with(@property, \'og:title\')]';
				$metas = $xpath->query($query);
				foreach ($metas as $meta) {
					if ($meta->getAttribute('property') == "og:title"){
						$datas->title = $meta->getAttribute('content');
						break;
					}
				}
			}

			// if we don't have title, take ifr from the title HTML TAG
			if (trim($datas->title) ==""){
				$nodes = $doc->getElementsByTagName( "title" );
				$datas->title = $nodes->item(0)->nodeValue;
			}

			// let's look for a picture !
			$picture_url = "";
			$picture_title = "";
			
			// look for picture from the website's OpenGraph meta data
			$query = '//*/meta[starts-with(@property, \'og:image\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:image"){
					$picture_url = $meta->getAttribute('content');
					$tmp_url = explode("?",$picture_url);
					$picture_url = $tmp_url[0];
					break;
				}
			}

			// if no picture found, get the first "big" picture in html (width>400px)
			if (trim($picture_url) ==""){
				$metas = $doc->getElementsByTagName('img');
				$nb_pict = 0;
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					$dimensions = null;
					$dimensions = getimagesize( $meta->getAttribute('src') );
					if ($dimensions[0] > MIN_WIDTH_OF_PICTURES_IF_SEEKED_IN_HTML){						
						$picture_url = $meta->getAttribute('src');
						$picture_title = $meta->getAttribute('title');
						// check if it's not a double
						$exists = false;
						foreach($datas->pictures as $exists_pict){
							if ($exists_pict->url == $picture_url) {
								$exists = true;
								break;
							}
						}
						if (!$exists){
							$nb_pict++;
							$datas->pictures[] = new RedboxPictureDataContainer($picture_url);
							$pict_idx = (count($datas->pictures)-1);
							if ($datas->pictures[$pict_idx]->title)  $datas->pictures[$pict_idx]->title = $picture_title;
							if ($nb_pict == NUMBER_OF_PICTURES_IF_SEEKED_IN_HTML) break;
						}
					}
				}
			}
			else{
				// finaly create the picture instance in this object
				$datas->pictures[] = new RedboxPictureDataContainer($picture_url);
				$pict_idx = (count($datas->pictures)-1);			
				if ($datas->pictures[$pict_idx]->title)  $datas->pictures[$pict_idx]->title = $picture_title;
			}
			// check for embed link for a video in the OpenGraph meta data
			$query = '//*/meta[starts-with(@property, \'og:video\')]';
			$metas = $xpath->query($query);
			foreach ($metas as $meta) {
				if ($meta->getAttribute('property') == "og:video"){
					$datas->type = "video";
					$datas->category = $this->categories["video"];
					$datas->video_datas = $this->get_video_data_from_provider($meta->getAttribute('content'));
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
					$tmp_description = $meta->getAttribute('content');
					break;
				}
			}
			
			// check for description in the classic meta data
			if (trim($tmp_description) ==""){
			$metas = $doc->getElementsByTagName('meta');
				for ($i = 0; $i < $metas->length; $i++)	{
					$meta = $metas->item($i);
					if($meta->getAttribute('name') == 'description')
						$tmp_description = $meta->getAttribute('content');			 	
				}
			}
			
			// check in the html content tags for the first "big" string (more than 20 words)
			$p_tag_description = "";
			$metas = $doc->getElementsByTagName('p');
			for ($i = 0; $i < $metas->length; $i++)	{
				$meta = $metas->item($i);
				$text = $meta->textContent;
				if (str_word_count($text) > MIN_WORDS_FOR_DESCRIPTION_IN_HTML_TAGS){
					$p_tag_description = strip_tags($text);
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
			if (trim($datas->title)==""){
				preg_match('/^(?>\S+\s*){1,MAX_WORDS_FOR_TITLE_GENERATED}/', $datas->description, $match);
				$datas->title = $match[0]."...";
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
			if (trim($datas->video_datas->url) == ''){
				$datas->type = "article";
				$datas->category = $this->categories["article"];
			}
			$datas->source = $url;
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
		// modify tiny urls from youtube links
		$url = str_replace("youtu.be","youtube.com/v",$url);

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
				'<object width="425" height="355"><param name="movie"' .
				' value="http://www.youtube.com/v/'.$id.
				'&hl=fr"></param><param name="wmode" value="transparent"></param><embed' .
				' src="http://www.youtube.com/v/'.$id.
				'&hl=fr" type="application/x-shockwave-flash" wmode="transparent" width="425"' .
				' height="355"></embed></object>';
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
			$xml_string = @file_get_contents("http://vimeo.com/api/clip/".$id.".xml");
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
		
		
		if ($type=="youtube"||$type=="dailymotion"||$type=="vimeo") {
			$tmp_url = explode("?",$url);
			$s_url = $tmp_url[0];
			$shortcode = "[".$type." ".$s_url."]";
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
			for($j=0;$j<count($this->list_datas);$j++){
				if ($better = $this->get_better_description($this->list_datas[$i]->description,$this->list_datas[$j]->description)){
					$this->list_datas[$i]->description = $better;
					$this->list_datas[$j]->description = "";
				}
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

	private function fetchUrl($url){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 80000);
		//You may need to add the line below
		//curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
		$feedData = curl_exec($ch);
		curl_close($ch); 
		return $feedData;
	}

} // END CLASS RedboxDataRetriever

// CLASS RedboxDataContainer
class RedboxDataContainer{
	public function __construct(){
		$this->url = "";
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

	public function __construct($url=null){
		$this->url = $url;
		$this->ext = "";
		$this->title = "";
		if (trim($this->url)!=""){
			$this->get_size();
		}
		else{ 
			$this->width = 0;
			$this->height = 0;
		}
		return $this;
	}
	
	public function get_size(){
		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $this->url, $matches);
		if ($matches) {
			$dimensions = null;
			$dimensions = getimagesize( $this->url );
			$this->width = $dimensions[0];
			$this->height = $dimensions[1];
		}
		return $this;
	}
} // END CLASS RedboxPictureDataContainer


?>
