<?php
/*
Plugin Name: RedBox

Plugin URI: https://github.com/sakwe/wpRedBox

Description: The base of this plugin is import datas from other WebSites. It can receive URLs and get datas from HTML tags, meta tags, OpenGraph tags. If you have a Facebook Id and app/secret ID, you can feed it to access Facebook Graph API datas directly. So it can import pictures, posts from websites or facebook, get the datas as title, description, picture(s) urls (title and width/height when possible), video datas (title, duration, author, etc)
You can also auto import and synchronize your Facebook fan page posts.
With RedBox, you also get a blog page where people can make "propositions" (that use wp comments management). The admins and editors can auto import propositions.

Version: 1.0

Author: Gregory Wojtalik

Author URI: mailto:gregory@wojtalik.be

License: GPL2

Copyright Sakwe 2014 (email : gregory@wojtalik.be)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

session_start();

/* French (fr) is the default supported language
 * You can put "debug" if you want to translate defines for your language file
 * If PLUGIN_DIR/lang/ is writable, this plugin can auto make a translation file via google translate
 **/ 
define ("REDBOX_LANGUAGE","fr");
define ("REDBOX_DEBUG",false);
// let's load languages support for RedBox 
require_once(WP_PLUGIN_DIR.'/redbox/lang/redbox-lang.php');


// Include child files of RedBox Plugin
$redbox_dir = WP_PLUGIN_DIR.'/redbox/includes/';
$files = scandir($redbox_dir);
includeFiles($files, $redbox_dir);

function includeFiles ($files, $currentPath='') {
	foreach ($files as $include) {
		if ($include != '.' && $include != '..') { // ignores self and parent directory
			$newPath = $currentPath . $include . '/';
			if (is_dir($newPath)) { // if a directory, re-run function to get directory contents
				$files = scandir($newPath);
				includeFiles($files, $newPath);
			} else {
				if (strstr($include, '.php') && !strstr($include, '.beta.')) { // only grab .php files
					require_once($currentPath.$include);
				}
			}
		}
	}
}


class RedBox{
	public function __construct(){
		// get the global configuration in the instance
		$this->configuration = new RedBoxConfiguration($this);
		// get the data retriver in the instance
		$this->retriever = new RedBoxDataRetriever($this->configuration->categories,$this->configuration->fb_config,$this);
		// get the data manager in the instance
		$this->manager = new RedBoxDataManager($this);
		// get the admin in the instance
		$this->admin = new RedBoxAdmin($this);
		// get the user interface in the instance
		$this->user = new RedBoxUser($this);
		// get the blog integration in the instance
		$this->blog = new RedBoxBlog($this);
		// get the facebook integration in the instance
		$this->facebook = new RedBoxFacebook($this);
		// get the diaspora integration in the instance
		$this->diaspora = new RedBoxDiaspora($this);		
		// load redbox XMPP support
		$this->xmpp = new RedBoxXMPP($this);
		// load redbox auto update management
		$this->autoUpdate = new RedBoxAutoUpdate($this);
		// load redbox tools into the action dispatcher
		$this->dispatcher = new RedBoxDispatcher($this);
		$options = get_option('redbox_options');
		if (isset($options['redbox_page_id']))
			$this->page_id = $options['redbox_page_id'];
	}
}

// load RedBox plugin in WordPress
$redbox = new RedBox();

?>
