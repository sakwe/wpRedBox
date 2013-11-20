<?php
/*
Plugin Name: RedBox

Plugin URI: https://github.com/sakwe/wpRedBox

Description: The base of this plugin is import datas from other WebSites. It can receive an URL and get datas from HTML tags, meta tags, OpenGraph tags. If you have a Facebook Id and app/secret ID, you can give it to access Facebook Graph API datas directy. So it can import pictures, posts from websites or facebook, get the datas as title, description, picture(s) urls (title and width/height when possible), video datas (title, duration, author, etc)
You can also auto import and synchronize your Facebook fan page posts.
With RedBox, you also get a blog page where people can make "propositions" (that use wp comments management). The admins and editors can auto import propositions.

Version: 1.0

Author: Gregory Wojtalik

Author URI: mailto:gregory@wojtalik.be

License: GPL2

Copyright 2013  Sakwe  (email : gregory@wojtalik.be)

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

/* French (fr) is the default supported language
 * You can put "debug" if you want to translate defines for your language file
 * If PLUGIN_DIR/lang/ is writable, this plugin can auto make a translation file
 **/ 
define ("REDBOX_LANGUAGE","fr");

// let's load languages support for RedBox 
require_once(WP_PLUGIN_DIR.'/redbox/lang/redbox-lang.php');

// use this important (master) class : RedBoxDataImporter (and UrlDataRetriever)
require_once(WP_PLUGIN_DIR.'/redbox/includes/redbox-data-importer.class.php');

// load plugin configuration for admins
require_once(WP_PLUGIN_DIR.'/redbox/includes/redbox-admin-interface.class.php');

// load plugin options for everyone
require_once(WP_PLUGIN_DIR.'/redbox/includes/redbox-user-interface.class.php');

// integrate RedBox in the blog a get RedBox interfaces
require_once(WP_PLUGIN_DIR.'/redbox/includes/redbox-blog-interface.class.php');

class RedBox{

	public function __construct(){
		// get the admin in the instance
		$this->admin = new RedBoxAdmin();
		// get the user interface in the instance
		$this->user = new RedBoxUser();
		// get the blog intgration in the instance
		//$this->blog = new RedBoxBlog();
	}
}

$redBox = new RedBox();

?>
