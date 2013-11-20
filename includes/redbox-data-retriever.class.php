<?
/* The RedboxDataRetriever class
 *
 * This PHP class can retrieve base datas from an url ab return a datas objects with : 
 *
 * - type	: url/article/picture/video/gallery
 * - human type	: categorised as Links/Articles/Pictures/Short videos/Documentary/Pictures gallery
 * - title	: The title of the document
 * - description: Description found in the HTML document 
 * - pictures	:	- url -> The picture found for the document (can retrieve Facebook galleries, so retrieves all pictures)
 *			- description -> generally empty, it contains the "title" tags of the img if found or the Facebook description
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


	public function get_datas($url,$datas=null,$quick=false){
		//first, check if it's a facebook link
		if ($fb_id=$this->get_facebook_url_id($url)){
			$datas = $this->get_datas_from_facebook($fb_id,$datas,$quick);
			$datas->url = $url;
		}
		else{
			$datas = $this->get_datas_from_url($url,$datas);
		}
		return $datas;
	}
	
	private function get_facebook_url_id($url){
		$parsed =  parse_url($url);
		$dns = $parsed['host'];
		$dns = str_replace('www.','',$dns);
		if ($dns=="facebook.com"){
			$fb_id = "";			

			// this analyse an url for a fb gallery or photo theater 
			preg_match_all("/.*?(\\d+).*?\\d+.*?[.](\\d+)/is",$url,$matches);
			$gallery_id=$matches[1][0];
			$author_id=$matches[2][0];
			if ($gallery_id>0 && $author_id>0) $fb_id = $gallery_id;

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
				$fb_id = $page_id . "_" . $post_id;
			}
			return trim($fb_id);
		}
		else{
			return false;
		}
	}
	
	private function get_datas_from_facebook($id_fb,$datas=null,$quick=false){
	
		// If we don't receive any data, initialise ours
		if (!$datas){
			$datas = new RedboxDataContainer();
			$datas->id_fb = $id_fb;
			$datas->author_picture = new RedboxPictureDataContainer();
		}

		// get the data flow from facebook open graph api
		$authToken = $this->fetchUrl("https://graph.facebook.com/oauth/access_token?grant_type=client_credentials&client_id={$this->fb_config['app_id']}&client_secret={$this->fb_config['app_secret']}");	
		$json_object = $this->fetchUrl("https://graph.facebook.com/".$id_fb."?{$authToken}");
		$feed_data = json_decode($json_object);

		// set basic datas
		$datas->url = "https://www.facebook.com/".$id_fb;
		$datas->fb_id = $id_fb;
		$datas->pictures = array();
		$datas->video_datas = new RedboxVideoDataContainer();
		$datas->type = $feed_data->type;
		$datas->category = $this->categories["link"];
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
			$datas->author_picture->title = "profile";
			break;
		}
		
		// if we found a picture gallery, let's pick them !
		if ($feed_data->cover_photo != ""){
			$datas->type = "gallery";
			$datas->category = $this->categories["gallery"];
			$json_object = $this->fetchUrl("https://graph.facebook.com/".$feed_data->cover_photo."?{$authToken}");
			$cover_data = json_decode($json_object);
			$datas->pictures[] = new RedboxPictureDataContainer($cover_data->source);
			$pict_idx = (count($datas->pictures)-1);
			$datas->pictures[$pict_idx]->title = $cover_data->name;
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
		elseif ($feed_data->type == "link"){
			$datas->type = "article";
			$datas->category = $this->categories["article"];
			$datas->source = $feed_data->link;
			$datas = $this->get_datas_from_url($feed_data->link,$datas);
		}
		elseif ($feed_data->type == "video"){
			$datas->source = $feed_data->link;
			$datas = $this->get_datas_from_url($feed_data->link,$datas);
		}		
		elseif ($feed_data->picture != ""){
			$datas->type = "picture";
			$datas->category = $this->categories["picture"];
			$datas->source = $feed_data->source;
			$datas->pictures[] = new RedboxPictureDataContainer($feed_data->source);
			$pict_idx = (count($datas->pictures)-1);
		}		
		if (trim($datas->description)==""){
			$datas->description = $feed_data->description;
		}
		if (trim($datas->title)==""){
			if ($feed_data->message!="") $base = $feed_data->message;
			if ($base=="") $base = $feed_data->message;
			if ($base=="") $base = $feed_data->description;
			preg_match('/^(?>\S+\s*){1,7}/', $feed_data->name, $match);
			$datas->title = $match[0]."...";
		}
		if (trim($datas->origin)=="") $datas->origin = "facebook";
		
		return $datas;
	}

	private function get_datas_from_url($url,$datas=null){
		// we don't know what is behind the url ..
		$is_image_url = false;

		// If we don't receive any data, initialise ours
		if (!$datas){
			$datas = new RedboxDataContainer();
			$datas->url = $url;
			$datas->source = $url;			
			$datas->author_picture = new RedboxPictureDataContainer();
		}
		
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
		}
	
		// if it's not a simple picture, let's go deeper in HTML code exploration
		if (!$is_image_url){
			// we consider it's an article from a website
			$datas->type = "article";
			$datas->category = $this->categories["article"];

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
					if ($dimensions[0] > 400){						
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
							if ($nb_pict == 3) break;
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
					if ($datas->video_datas->duration > 900){
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
			// check in the html content for the first "big" string (more than 20 words)
			if (trim($tmp_description) ==""){
				preg_match_all("/<p>(.*)<\/p>/",$html,$matches);
				foreach($matches as $match){
					if (array_key_exists(0,$match)){
						if (str_word_count($match[0]) > 20){
							$tmp_description = strip_tags($match[0]);
							break;
						}
					}
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
			if (((strpos($datas->description,$tmp_description)>=0) && (strlen($tmp_description) > strlen($datas->description) )) 
			|| trim($datas->description) == ""){
				$datas->description = $tmp_description;
			}
			
			// check if we had a description before this function execution (ex: facebook give us his description)
			if (((strpos($datas->video_datas->description,$tmp_description)>=0) && (strlen($datas->video_datas->description) > strlen($tmp_description) )) 
			|| trim($datas->description) == ""){
				$datas->description = $datas->video_datas->description;
				$datas->video_datas->description="";
			}
			
			// clean video description if it is the same than master description
			if($datas->video_datas->description==$datas->description) 
				$datas->video_datas->description = "";
			
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
						break;
					}
				}
			}
		}
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
		$this->source = "";
		$this->fb_id = "";		
		$this->type = "url";
		$this->category = "link";
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
