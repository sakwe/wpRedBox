<?
/* The RedBoxDataImporter class
 * Author: Gregory Wojtalik
 * Package RedBox (For WordPress)
 *
 **/
 
// we need the RedboxDataRetriever class to proceed...
require_once "redbox-data-retriever.class.php";

class RedBoxDataImporter{

	public function __construct($categories=null,$fb_config=null){
		$this->retriever = new RedboxDataRetriever($categories,$fb_config);
	}

	public function import_post($url,$force_refresh=false,$force_update=false){
		global $wpdb;
		$post_id = null;
		
		// check if we received an url
		if (stripos('http',$url) >= 0){
			// check if we already have a post for the url
			$sql = 'SELECT * FROM ' . $wpdb->prefix .'postmeta WHERE meta_key="redbox_base_url" AND meta_value="'.$url.'"';
			if ($rows = $wpdb->get_results($sql)){
				$post_id = $rows[0]->ID;
			}
			if (!$post_id || ($post_id && $force_update)){
				$datas = $this->retriever->get_datas_from_url($url);
			}
		}
		// this case is for a facebook id received for import reference
		else{
		
		}
		
		
		
	}


}

?>
