<?php

require_once('config.php');




// Set constants and global variables
define('CWD', dirname($_SERVER['PHP_SELF']));
define('CINCH_ABS_PATH', realpath(dirname(__FILE__)) . "/");
define('SITE_ABS_PATH', realpath(dirname(__FILE__) . "/..") . "/");
define('CINCH_REL_PATH', basename(dirname($_SERVER['PHP_SELF'])) . "/");


$settings = array();
$file_array = array('css' => array(), 'js' => array());
$error = "";


/*----------------------------------------------------------------------*
	FUNCTIONS
*----------------------------------------------------------------------*/

// Main cinch function that coordinates all actions, including caching and setting MIME type
function cinchMain($_settings) {
	global $cachefile, $file_array, $settings, $error;
	

	
	// If no files have been selected, stop here
	if(!isset($_settings['files'])) {
		echo "cinch: No files have been selected.";
		return;
	}
	
	// Set Default Settings
	$settings = setDefaultSettings($_settings);
	
	// Send correct MIME type
	if($settings['type'] == 'js') {
		unset($file_array['css']);
		header("Content-type: application/x-javascript; charset: UTF-8"); 
	}
	else if($settings['type'] == 'css') {
		unset($file_array['js']);
		header("Content-type: text/css; charset: UTF-8");
	}
	
	// Separate files into CSS and JS lists
	sortFiles($settings['files']);
	
	// See if cache file exists and is current
	$cachefile = 'cache/'.md5(implode(",", $settings)) . "." .$settings['type'];
	
	// If PRODUCTION is set, send cache file and quit
	if(PRODUCTION) {
		printCacheFile($cachefile);
		exit;
	}
	
	
	// Check to see if cache file exists and if user has most recent version
	$cachefile_timestamp = hasCacheFile($cachefile, $file_array[$settings['type']]);
	$gmt_mtime = gmdate('r', $cachefile_timestamp);
	session_cache_limiter(‘public’);
	header('ETag: "'.md5($cachefile_timestamp.$cachefile).'"');
	header('Last-Modified: '.$gmt_mtime);
	header('Cache-Control: public');
	// If user has file cached in browser, send 304 Not Modified Header
	if (!$settings['force'] && $cachefile_timestamp && ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($cachefile_timestamp.$cachefile))) {
		header('HTTP/1.1 304 Not Modified');	
		exit();
	}
	
	// Load Live Reload
	if($settings['type'] == 'js' && $settings['reload']) $file_array[$settings['type']][] = "!".CINCH_REL_PATH."libraries/live.min.js";
	
	// If cache file exists on server and is current, but user doesn't have it yet, send that.
	if(($cachefile_timestamp && !$settings['force'])) {
		printCacheFile($cachefile);
	} 

	// Else, build new cache files, CSS then JS
	else if(count($file_array[$settings['type']])) {
		// Process all files of this type (external libraries, preprocessors, minification, etc.)
		$file_output = processFileList($file_array[$settings['type']]);
		
		// Save output to cache file
		if($handle = fopen($cachefile, 'w')) {
			if(fwrite($handle, $file_output) === FALSE) {
				echo "Cannot write to cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
			}
		}
		else echo "Cannot open cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
		if($handle) fclose($handle);
		
		// Print output
		printCacheFile($cachefile);
	}
}



// Set all settings to default values if no value is given
function setDefaultSettings($_settings) {
	global $defaults;
	
	// Make true/false values consistent
	foreach($_settings as $key => $value) {
		if($_settings[$key] === 'false' || $_settings[$key] === 0) $_settings[$key] = false;
		elseif($_settings[$key] === 'true' || $_settings[$key] === 0) $_settings[$key] = true;
	}
	
	// If no value is given for a setting, use default
	foreach($defaults as $name => $default_value) {
		$_settings[$name] = isset($_settings[$name]) ? $_settings[$name] : $default_value;
	}
	
	// Auto detect content type, based on file extension
	if($_settings['type']=='auto') { 
		foreach($_settings['files'] as $filename) {
			$filename = strtolower($filename);
			if(substr($filename, -3) == '.js' || substr($filename, -7) == '.coffee') {
				$_settings['type'] = 'js';
				break;
			}
			elseif(substr($filename, -4) == '.css' || substr($filename, -5) == '.scss' || substr($filename, -5) == '.sass' || substr($filename, -5) == '.less') {
				$_settings['type'] = 'css';
				break;
			}
		}
	}
	// If type still can't be detected, default to JS
	if($_settings['type']=='auto') $_settings['type'] = 'js';
	
	return $_settings;
}





// See if cache file exists and is older than all of the requested files
function hasCacheFile($cachefile, $file_array) {

	// Check to see if file exists
	if(file_exists($cachefile)) {
		$timestamp = filemtime($cachefile);
		if(PRODUCTION) return $timestamp;
	} else {
		return false;
	}
	
	// Clear all cache files if cache is older than one month, in order to keep cache folder clean
	$one_month_ago = strtotime("-1 month");
	if($timestamp != 0 && $timestamp < $one_month_ago) {
		clearCache();
		return false();
	} 

	// Check to see if cache is older than all of its compenent files
	else {
		foreach($file_array as $file) {
			if(!is_array($file)) { // Ignore package files
				if(substr($file,0,1) == "!") $file = substr($file,1); // remove '!'
				if(is_file(SITE_ABS_PATH.$file)) {
					if(filemtime(SITE_ABS_PATH.$file) > $timestamp) {
						return false;
					}
				}
			}
		}
	}
	
	return $timestamp;
}






// Find file type (js or css) of selected file
function getFileType($file) {
	if(substr($file, -3) == '.js' || substr($file, -7) == '.coffee') return 'js'; // || $library_info['type'] == 'js') $_GET['type'] = 'js';
	else if(substr($file, -4) == '.css' || substr($file, -5) == '.scss' || substr($file, -5) == '.sass' || substr($file, -5) == '.less') return 'css';
	else return;
}


// Sort files into $file_array by type
function sortFiles($file_list_array, $package = "") {
	global $file_array, $settings;
	
	$x = 0;
	foreach($file_list_array as $file) {
		// If file is an external, load library file names into $file_list_array
		if(substr($file, 0, 1) == "[") {
			$library = trim($file, '[');
			$library = trim($library, ']');
			$library_files = getExternalLibraryFiles($library);
			sortFiles($library_files, $library);
		} else {
			if(getFileType($file) == 'js' && $package) $file_array['js'][$package][] = $file;

			else if(getFileType($file) == 'css' && $package) $file_array['css'][$package][] = $file;
			
			else if(getFileType($file) == 'js') $file_array['js'][] = $file; // || $library_info['type'] == 'js') $_GET['type'] = 'js';

			else if(getFileType($file) == 'css') $file_array['css'][] = $file;
		}
		$x++;
	}
} 

	





// Process all files and combine them into one place
function processFileList($type_file_array) {
	global $settings;
	$content = '';
	foreach($type_file_array as $key => $file) { // combine files
		if(is_array($file)) {
			if($settings['debug']) $content .= "/* [$key] */\n";
			foreach($file as $sub_file) {
				$content .= processFile($sub_file);
			}
		}
		else $content .= processFile($file);	
	}
	return $content;
}








// Handle minification and pre-processing of file as necessary
function processFile($file) {
	global $settings;
	
	// ENABLE/DISABLE FILE MINIFICATION
	$compress_file = $settings['min'];
	if(substr($file,0,1) == "!") { // don't minify file if marked with an '!'
		$compress_file = false;
		$file = substr($file,1); // remove '!' from the front of filename
	}
	
	// LOAD FILE CONTENTS
	$temp_content = loadFile($file);
	$file_type = getFileType($file);
	
	
	// IF SASS, PROCESS AND CONVERT TO CSS
	if(substr($file, -5) == '.scss' || substr($file, -5) == '.sass') $temp_content = convertSASS($temp_content, $file);
	
	// IF LESS, PROCESS AND CONVERT TO CSS
	if(substr($file, -5) == '.less') $temp_content = convertLESS($temp_content, $file);

	// IF COFFEESCRIPT, PROCESS AND CONVERT TO JS
	if(substr($file, -7) == '.coffee') $temp_content = convertCoffee($temp_content);




	// MINIFY/PACK CONTENT		
	if($compress_file && $file_type == 'js'){	// Javascript		

		if($compress_file === 'pack') {
			require_once('processors/packer-1.1/class.JavaScriptPacker.php');
			$packer = new JavaScriptPacker($temp_content, 'Normal', true, false);
			$temp_content = $packer->pack();
		} else {
			require_once('processors/jsShrink.php');
			$temp_content = jsShrink($temp_content);
		
		}
	} elseif($compress_file) { //CSS
		require_once('processors/css_optimizer/css_optimizer.php');
		$css_optimizer = new css_optimizer();
		$temp_content = $css_optimizer->process($temp_content);
		$temp_content = minifyCSS($temp_content);
	}
	
	

	// FIX LINKS TO EXTERNAL FILES IN CSS
	if($file_type == 'css') {
		//$path = "../".dirname($file)."/"; // trailing slash just in case user didn't add a leading slash to their CSS file path
		$path = "../".dirname($file)."/"; // trailing slash just in case user didn't add a leading slash to their CSS file path
		$temp_content = preg_replace("/url\s?\(['\"]?((?!http|\/)[^'\"\)]*)['\"]?\)/", "url(".$path."$1)", $temp_content); // if path is absolute, leave it alone. otherwise, relink assets based on path from css file
	}
	
	if($settings['debug'] && $library_info) $temp_content = "/* ".$library_info['library_name']." */\n".$temp_content."\n\n";
	elseif($settings['debug'] && $temp_content) $temp_content = "/* $file */\n".$temp_content."\n\n";
	if($temp_content) $content .= $temp_content;
	
	
	return $temp_content;

	
}






// Load file contents into memory
function loadFile($file) {

	// Remote file
	if(substr($file, 0, 4) == 'http') {
		//$file = preg_replace("/\[(.*)\]/", "$1", $file);
		if(!$content = @file_get_contents($file)) recordError("'".$file."' is not a valid remote file.");
	}
	
/*
	// Library file
	elseif(substr($file, 0, 1) == '[') { // LOAD A FILE FROM EXTERNALLY HOSTED LIBRARY
		$compress_file = false;
		$library_info = loadLibraryInfo($file);
		$file = $library_info['library_url'];
		$content = loadExternalLibrary($file);
	}
*/
	
	// Local file
	else {
		if(!is_file(SITE_ABS_PATH.$file)) recordError("'".$file."' is not a valid file.");
		else {
			if(!$handle = fopen(SITE_ABS_PATH.$file, "r")) recordError("There was an error trying to open '".$file."'");
			else {
				$content = "";
				if(filesize(SITE_ABS_PATH.$file) == 0) recordError("'".$file."' is an empty file.");
				elseif(!$content = fread($handle, filesize(SITE_ABS_PATH.$file))) recordError("There was an error trying to open '".$file."'.");
				fclose($handle);
			}
		}
	}
	return $content;
	
}




/*

function loadLibraryInfo($file) {
	global $libraries, $error;
	
	preg_match("/\[([^\/]*)\/?(.*)?\]/", $file, $file_array);
	$library_info['library_name'] = strtolower($file_array[1]);
	
	$library = $libraries[$library_info['library_name']];
	
	if(!$library) {
		recordError("'".$file."' is not a valid library name.");
		return;
	}
	
	$library_info['version'] = $file_array[2] ? $file_array[2] : $library['ver'];
		
	$library_info['library_url'] =  str_replace('{version}', $library_info['version'], $library['url']);
	
	return $library_info;
}

*/





/*
// RETRIEVE LIBRARY FILE FROM EXTERNALLY HOSTED LIBRARIES
function loadExternalLibrary($library_url) {

	// IF SERVER CAN'T REMOTELY ACCESS FILES, OR NO VERSION NUMBER IS SELECTED, USE LOCAL VERSION
	if(ini_get("allow_url_fopen") == 0) {
		if(ini_get("allow_url_fopen") == 0) recordError("Your server does not allow for access to remote libraries. You may need to upload the file to your web server manually.");
	}
	
	// GET FILE CONTENTS	
	$file_contents = @file_get_contents($library_url);
	

	// UPDATE RELATIVE LINKS WITH LIBRARY URL
	$file_contents = preg_replace("/(url\(\'?)/", "$1".dirname($library_url)."/", $file_contents);
	
	return $file_contents;
}
*/


	
/* INCOMPLETE */
// Find if package exists on server, and if not, download it. Then add web files to file list.
function getExternalLibraryFiles($library) {
	if(strpos($library, '#')) $package = explode('#', $library);
	elseif(strpos($library, '/')) $package = explode('/', $library);
	else $package = array($library);
	if($package[1]) $package_path = "cache/".$package[0]."-".$package[1]."/";
	else $package_path = "cache/".$package[0]."-master/";
	
	// If package repo exists on the server, use local version
	// Otherwise, download and unzip repo from github
	if(!file_exists(CINCH_ABS_PATH.$package_path)) downloadRepo($package);

	$package_info = @json_decode(file_get_contents(CINCH_ABS_PATH.$package_path."bower.json"));
	if(!$package_info) $package_info = @json_decode(file_get_contents(CINCH_ABS_PATH.$package_path."package.json"));
	if(!$package_info) {
		recordError("The package '" . $package[0] . "' doesn't have a valid bower.json file.");
		return false;
	}

	$library_files = $package_info->main;

	$package_path = CINCH_REL_PATH . $package_path;

	
	if(!$library_files) recordError("The package '" . $package[0] . "' hasn't stated which of its files to include in its bower.json.");
	
	if(is_array($library_files)) {
		foreach($library_files as $library_file) {
			$library_file_url = $package_path . $library_file;
			$library_file_list[] = preg_replace("/\/\.\//", "/", $library_file_url);
		}
	} else $library_file_list[] = preg_replace("/\/\.\//", "/", $package_path . $library_files);

	return $library_file_list;
}


// Retrieve git repository url for bower package
function getRepoUrl($package) {
	$bower_local = CINCH_ABS_PATH."cache/bower-directory.json";
	
	
	// INCOMPLETE: If bower list is over a week old, delete local version
	
	
	// Retrieve github repo url from bower listing
	if(file_exists($bower_local)) $bower_directory = json_decode(file_get_contents($bower_local));
	else {
		$bower_directory_contents = file_get_contents('http://bower.herokuapp.com/packages/');
		$bower_directory = json_decode($bower_directory_contents);
		
		if($handle = fopen($bower_local, 'w')) {
			if(fwrite($handle, $bower_directory_contents) === FALSE) {
				recordError("Cannot write to local bower directory file. Make sure the permissions on your server are set to allow write access to the 'cache' folder.");
			}
		}
		else recordError("Cannot open bower directory file. Make sure the permissions on your server are set to allow write access to the 'cache' folder.");
		if($handle) fclose($handle);
	}
	
	

	foreach($bower_directory as $bower_item) {
		if($bower_item->name == $package[0]) {
			$repo_root_url = $bower_item->url;
			break;
		}
	}
	
	if(!$repo_root_url) {
		recordError("Cannot find package '" . $package[0] . "' in bower package list.");
		return false;
	}
	
	$repo_id = str_replace("git://github.com/", "", $repo_root_url);
	$repo_id = str_replace(".git", "", $repo_id);
	
	$tag_id = $package[1];
	
	// If no version number was given, return path to raw repo files
	if(!$package[1]) return "https://github.com/" . $repo_id . "/archive/master.zip"; //return "https://raw.githubusercontent.com/" . $repo_id . "/master/";
	
	
	
	// If version number was included, get version repo url
	// INCOMPLETE: if version number doesn't check out, check alternate forms (v1.0, 1.0, v1)
	else {
		// Load tag list
/*
		echo "https://api.github.com/repos/".$repo_id."/tags";
		$tag_json = file_get_contents("https://api.github.com/repos/".$repo_id."/tags");
		$tags = json_decode($tag_json);

		// Find version number
		foreach($tags as $tag) {
			if(int($tag->name) == $package[1]) {
				$tag_id = $tag->name;
				break;
			}
		}
*/
		
		// Get new $repo_id
		
		// Return path to raw repo files 
		// https://raw.githubusercontent.com/jquery/jquery/2.1.1-rc2/dist/jquery.js
		return "https://github.com/" . $repo_id . "/archive/".$tag_id.".zip"; //return "https://raw.githubusercontent.com/" . $repo_id . "/".$tag_id."/";
		
		//
		
	}
	
	
	
}



// Download git repository and unzip it into cache folder
function downloadRepo($package) {
	$repo_url = getRepoUrl($package);
	$zip_file = CINCH_ABS_PATH."cache/".$package[0].".zip";
	
	if($repo_url) {
		// Download repo from github
		file_put_contents($zip_file, fopen($repo_url, 'r'));
		
		// Unzip repo to cache folder
		$zip = new ZipArchive;
		if ($zip->open($zip_file) === TRUE) {
		    $zip->extractTo(CINCH_ABS_PATH."cache/");
		    $zip->close();
		    // Delete zip file
		    unlink($zip_file);
		    
		} else {
			recordError("Cannot open zip file.");
		}
	}
}



// Parses sass/scss files
function convertSASS($src, $file) {
	
	if(substr($file, 0, 4) == 'http') $path = dirname($file)."/";
	else $path = "../".dirname($file)."/";
	
	// CHECK TO SEE IF FILE USES OLD SCHOOL INDENTATION STYLE
	if(strpos($src, "{") === false) {
		// IF SO, CONVERT IT TO SCSS
		require_once('processors/sass.php');
		$src = sassToScss($src);
	} 
	
	
	require_once('processors/scss/scss.inc.php');
	$scss = new scssc();
	$scss->addImportPath($path);
	
	// ADD BOURBON
	$src = "@import 'libraries/bourbon/_bourbon.scss';\n" . $src;
	
	try {
		$css = $scss->compile($src);	
	} catch(Exception $e) {
		recordError("Sass error in $file: " . $e->getMessage() . ".");
		return;
	}
	return $css;
}





// Parses less files
function convertLESS($src, $file) {
	
	$path = "../".dirname($file)."/";
	require_once('processors/less/lessc.inc.php');
	$less = new lessc();
	$less->addImportDir($path);
	try {
		$css = $less->compile($src);	
	} catch(Exception $e) {
		if($settings['debug']) recordError("Less error in $file: " . $e->getMessage() . ".");
		return;
	}
	return $css;
}

// Minimize css files
function minifyCSS($_src) {
	$_out = preg_replace("/\s+/", " ", $_src); // remove excess spaces
	$_out = preg_replace("/\s?(,|;|:|{|})\s?/", "$1", $_out); // remove spaces next to CSS specific characters (ie. ;, {, })
	$_out = preg_replace("/\/\*(.|[\r\n])*?\*\//", "", $_out); // remove comments	
	$_out = trim($_out);

	return $_out;
}



// Parses coffeescript files
function convertCoffee($src) {
	if (version_compare(PHP_VERSION, '5.3.0') >= 0) require_once('processors/coffeescript/Init.php');
	else recordError("The coffeescript processor requires PHP 5.3 or greater, which you don't have. All .coffee files are currently being skipped.");
}




// Loads and prints out cache file contents
function printCacheFile($cachefile) {
	global $settings, $error;
	
	if(!is_file($cachefile)) {
		recordError("Cache file does not exist.");
		return;
	}
	if(!$handle = fopen($cachefile, "r")) {
		recordError("There was an error trying to open cache file: '".$cachefile."'.");
		//exit;
	} else {
		$content = fread($handle, filesize($cachefile));
		fclose($handle);
	}
	
	// Add error messages to output
	if($settings['debug'] && $error) echo "/*\n\nERROR:\n$error\n*/\n\n";
	echo trim($content);	
}



// Deletes all cache files in cache folder
function clearCache() {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
		if(is_file($file)) unlink($file);
	}
}



// Keeps track of errors and saves them to a variable for reporting
function recordError($error_message) {
	global $error;
	$error .= "- " . $error_message . "\n";	
}



// Separates web files and creates links to cache files
function cinch($settings) {
	global $file_array, $error;
	sortFiles($settings['files']);
	unset($settings['files']);	
	
	
	foreach($file_array as $file_type => $file_type_array) {
		$url = CINCH_REL_PATH."?files=";
		foreach($file_type_array as $file) {
			if(is_array($file)) {
				foreach($file as $package_file) {
					$url .= $package_file . ",";
				}
			} else {
				$url .= $file . ",";
			}
		}
		$url = trim($url, ","); // Remove last comma
		
		// Add settings
		$url .= "&type=" . $file_type . "&" . http_build_query($settings);
		if($file_type == 'css') {
			echo '<link rel="Stylesheet" href="'.$url.'" type="text/css" media="all">';				
		} else {
			echo '<script type="text/javascript" src="'.$url.'"></script>';
		}
	}
}



?>