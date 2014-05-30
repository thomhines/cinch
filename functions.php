<?php

/*----------------------------------------------------------------------*
	FUNCTIONS
*----------------------------------------------------------------------*/

function cinch($_settings) {
	global $cachefile, $file_array, $settings, $error;
	
	// If no files have been selected, stop here
	if(!isset($_settings['files'])) {
		echo "cinch: No files have been selected.";
		return;
	}
	
	// Set Default Settings
	$settings = setDefaults($_settings);
	
	
	// Separate files into CSS and JS lists
	sortFiles($settings['files']);
	
	if($settings['type'] == 'js') unset($file_array['css']);
	else if($settings['type'] == 'css') unset($file_array['js']);
	
	print_r($file_array);
	
	
	
	if($settings['reload']) {
		printReloadLink('css');
		printReloadLink('js');
		return;
	}
	

	
	foreach($file_array as $file_type => $type_file_array) {
		// Clear error log; 
		$error = $settings;
		
		// See if cache file exists and is current
		$cachefile = 'cinch/cache/'.md5(implode(",", $type_file_array)) . "." .$file_type;
		$cachefile_timestamp = hasCacheFile($cachefile, $type_file_array);
/*
		// If user has file cached in browser, send 304 Not Modified Header
		if ($has_cache_file && ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$cachefile))) {
			header('HTTP/1.1 304 Not Modified');		
			exit();
		}
*/
		
		// If cache file exists on server and is current, send that.
		if($cachefile_timestamp && !$settings['force']) {
			printCacheLink($cachefile, $file_type);
		} 
	
		// Else, build new cache files, CSS then JS
		else if(count($type_file_array)) {
			// Process all files of this type (external libraries, preprocessors, minification, etc.)
			$file_output = processFileList($type_file_array);
			
			// Add error messages to output
			if($settings['debug'] && $error) $file_output = "/*\n\nERROR:\n$error\n*/\n\n" . $file_output; 

			// Save output to cache file
			if($handle = @fopen($cachefile, 'w')) {
				if(fwrite($handle, $file_output) === FALSE) {
					echo "Cannot write to cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
				}
			}
			else echo "Cannot open cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
			if($handle) fclose($handle);
			
			// Print output
			printCacheLink($cachefile, $file_type);

			
		}
	}
}




function setDefaults($_settings) {
	foreach($_settings as $key => $value) {
		if($_settings[$key] === 'false') $_settings[$key] = false;
		elseif($_settings[$key] === 'true') $_settings[$key] = true;
	}
	$_settings['type'] = isset($_settings['type']) ? $_settings['type'] : 'auto';
	$_settings['min'] = isset($_settings['min']) ? $_settings['min'] : true;
	$_settings['force'] = isset($_settings['force']) ? $_settings['force'] : false;
	$_settings['debug'] = isset($_settings['debug']) ? $_settings['debug'] : true;
	$_settings['reload'] = isset($_settings['reload']) ? $_settings['reload'] : true;
	
	return $_settings;
}





// See if cache file exists and is older than all of the requested files
function hasCacheFile($cachefile, $file_array) {

	// Check to see if file exists
	if(file_exists($cachefile)) $timestamp = filemtime($cachefile);
	else {
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
				if(is_file($filepath.$file)) if(filemtime($filepath.$file) > $timestamp) return false;
			}
		}
	}
	
	return $timestamp;
}







function getFileType($file) {
	if(substr($file, -3) == '.js' || substr($file, -7) == '.coffee') return 'js'; // || $library_info['type'] == 'js') $_GET['type'] = 'js';
	else if(substr($file, -4) == '.css' || substr($file, -5) == '.scss' || substr($file, -5) == '.sass' || substr($file, -5) == '.less') return 'css';
	else return;
}



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

	






function processFileList($type_file_array) {
	$content = '';
	foreach($type_file_array as $key => $file) { // combine files
		if(is_array($file)) {
			$content .= "/* [$key] */\n";
			foreach($file as $sub_file) {
				$content .= processFile($sub_file);
			}
		}
		else $content .= processFile($file);	
	}
	return $content;
}









function processFile($file) {
	global $error, $settings;
	
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
		//$temp_content = $css_optimizer->process($temp_content);
		$temp_content = minifyCSS($temp_content);
	}

	// FIX LINKS TO EXTERNAL FILES IN CSS
	if($file_type == 'css') {
		$path = "../".dirname($file)."/"; // trailing slash just in case user didn't add a leading slash to their CSS file path
		$temp_content = preg_replace("/url\s?\(['\"]?((?!http|\/)[^'\"\)]*)['\"]?\)/", "url(".$path."$1)", $temp_content); // if path is absolute, leave it alone. otherwise, relink assets based on path from css file
	}
	
	if($settings['debug'] && $library_info) $temp_content = "/* ".$library_info['library_name']." */\n".$temp_content."\n\n";
	elseif($settings['debug'] && $temp_content) $temp_content = "/* $file */\n".$temp_content."\n\n";
	if($temp_content) $content .= $temp_content;
	
	return $temp_content;

	
}







function loadFile($file) {
	global $error;
	
	// Remote file
	if(substr($file, 0, 4) == 'http') {
		//$file = preg_replace("/\[(.*)\]/", "$1", $file);
		if(!$content = @file_get_contents($file)) $error .= "- '".$file."' is not a valid remote file.\n";
	}
	
	// Library file
	elseif(substr($file, 0, 1) == '[') { // LOAD A FILE FROM EXTERNALLY HOSTED LIBRARY
		$compress_file = false;
		$library_info = loadLibraryInfo($file);
		$file = $library_info['library_url'];
		$content = loadExternalLibrary($file);
	}
	
	// Local file
	else {
		if(!is_file($filepath.$file)) $error .= "- '".$file."' is not a valid file.\n";
		else {
			if(!$handle = fopen($filepath.$file, "r")) $error .= "- There was an error trying to open '".$file."'.\n";
			else {
				$content = "";
				if(filesize($filepath.$file) == 0) $error .= "- '".$file."' is an empty file.\n";
				elseif(!$content = fread($handle, filesize($filepath.$file))) $error .= "- There was an error trying to open '".$file."'.\n";
				fclose($handle);
			}
		}
	}
	return $content;
	
}





// PARSES SASS/SCSS
function convertSASS($src, $file) {
	global $error;
	
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
		if($settings['debug']) $error .= "- Sass error in $file: " . $e->getMessage() . ".\n";
		return;
	}
	return $css;
}





// PARSES LESS
function convertLESS($src, $file) {
	global $error;
	
	$path = "../".dirname($file)."/";
	require_once('processors/less/lessc.inc.php');
	$less = new lessc();
	$less->addImportDir($path);
	try {
		$css = $less->compile($src);	
	} catch(Exception $e) {
		if($settings['debug']) $error .= "- Less error in $file: " . $e->getMessage() . ".\n";
		return;
	}
	return $css;
}

// Minimize CSS
function minifyCSS($_src) {
	$_out = preg_replace("/\s+/", " ", $_src); // remove excess spaces
	$_out = preg_replace("/\s?(,|;|:|{|})\s?/", "$1", $_out); // remove spaces next to CSS specific characters (ie. ;, {, })
	$_out = preg_replace("/\/\*(.|[\r\n])*?\*\//", "", $_out); // remove comments	
	$_out = trim($_out);

	return $_out;
}



// PARSES COFFEESCRIPT
function convertCoffee($src) {
	global $error;
	if (version_compare(PHP_VERSION, '5.3.0') >= 0) require_once('processors/coffeescript/Init.php');
	else $error .=  "- The coffeescript processor requires PHP 5.3 or greater, which you don't have. All .coffee files are currently being skipped.\n";
}




/*

function loadLibraryInfo($file) {
	global $libraries, $error;
	
	preg_match("/\[([^\/]*)\/?(.*)?\]/", $file, $file_array);
	$library_info['library_name'] = strtolower($file_array[1]);
	
	$library = $libraries[$library_info['library_name']];
	
	if(!$library) {
		$error .= "- '".$file."' is not a valid library name.\n";
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
	global $error;

	// IF SERVER CAN'T REMOTELY ACCESS FILES, OR NO VERSION NUMBER IS SELECTED, USE LOCAL VERSION
	if(ini_get("allow_url_fopen") == 0) {
		if(ini_get("allow_url_fopen") == 0) $error .= "- Your server does not allow for access to remote libraries. You may need to upload the file to your web server manually.\n";
	}
	
	// GET FILE CONTENTS	
	$file_contents = @file_get_contents($library_url);
	

	// UPDATE RELATIVE LINKS WITH LIBRARY URL
	$file_contents = preg_replace("/(url\(\'?)/", "$1".dirname($library_url)."/", $file_contents);
	
	return $file_contents;
}
*/


	
/* INCOMPLETE */
function getExternalLibraryFiles($library) {
	global $error;
/*
	// Retrieve github repo url from bower listing
	
	// If bower list is over a week old, retrieve latest version from site and update local version
	if(file_exists('cache/bower-directory.json')) $bower_directory = json_decode(file_get_contents('cache/bower-directory.json'));
	else {
		$bower_directory_contents = file_get_contents('http://bower.herokuapp.com/packages/');
		$bower_directory = json_decode($bower_directory_contents);
		
		if($handle = fopen('cache/bower-directory.json', 'w')) {
			if(fwrite($handle, $bower_directory_contents) === FALSE) {
				$error .= "- Cannot write to bower directory. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
			}
		}
		else $error .= "- Cannot open bower directory. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
		fclose($handle);
	}
	
	

	foreach($bower_directory as $bower_item) {
		if($bower_item->name == $library) {
			$repo_url = $bower_item->url;
			break;
		}
	}
	
	
	
	
*/

	if(strpos($library, '#')) $package = explode('#', $library);
	else $package = array($library);
	if($package[1]) $package_path = "cinch/cache/".$package[0]."-".$package[1]."/";
	else $package_path = "cinch/cache/".$package[0]."-master/";
	
	// If package repo exists on the server, use local version
	// Otherwise, download and unzip repo from github
	if(!file_exists($package_path)) downloadRepo($package);

	// Retrieve library file list from github repo
	//$repo_path = str_replace("git://github.com/", "", $repo_url);
	//$repo_path = str_replace(".git", "", $repo_path);
	//$repo_raw_url = "https://raw.githubusercontent.com/" . $repo_path . "/master/";
	$package_info = @json_decode(file_get_contents($package_path."bower.json"));
	if(!$package_info) $package_info = @json_decode(file_get_contents($package_path."package.json"));
	
	if(!$package_info) $error .= "- The package '" . $package[0] . "' doesn't have a valid bower.json file.";
	
	$library_files = $package_info->main;

	
	if(!$library_files) $error .= "- The package '" . $package[0] . "' hasn't stated which of its files to include in its bower.json.";
	
	
	if(is_array($library_files)) {
		foreach($library_files as $library_file) {
			$library_file_url = $package_path . $library_file;
			$library_file_list[] = preg_replace("/\/\.\//", "/", $library_file_url);
		}
	} else $library_file_list[] = preg_replace("/\/\.\//", "/", $package_path . $library_files);
	
	return $library_file_list;
}



function getRepoUrl($package) {
	global $error;
	
	$bower_local = 'cinch/cache/bower-directory.json';

	// If version number is given, separate file from version number
	//if(strpos($package, '#')) $package = explode('#', $package);
	//else $package = array($package);
	
	// Retrieve github repo url from bower listing
	// If bower list is over a week old, retrieve latest version from site and update local version
	if(file_exists($bower_local)) $bower_directory = json_decode(file_get_contents($bower_local));
	else {
		$bower_directory_contents = file_get_contents('http://bower.herokuapp.com/packages/');
		$bower_directory = json_decode($bower_directory_contents);
		
		if($handle = fopen($bower_local, 'w')) {
			if(fwrite($handle, $bower_directory_contents) === FALSE) {
				$error .= "- Cannot write to local bower directory file. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
			}
		}
		else $error .= "- Cannot open bower directory file. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
		if($handle) fclose($handle);
	}
	
	

	foreach($bower_directory as $bower_item) {
		if($bower_item->name == $package[0]) {
			$repo_root_url = $bower_item->url;
			break;
		}
	}
	
	$error .= $repo_root_url;
	
	if(!$repo_root_url) {
		$error .= "- Cannot find package '" . $package[0] . "' in Bower directory.";
		return false;
	}
	
	$repo_id = str_replace("git://github.com/", "", $repo_root_url);
	$repo_id = str_replace(".git", "", $repo_id);
	
	$tag_id = $package[1];
	
	// If no version number was given, return path to raw repo files
	if(!$package[1]) return "https://github.com/" . $repo_id . "/archive/master.zip"; //return "https://raw.githubusercontent.com/" . $repo_id . "/master/";
	
	
	
	// If version number was included, get version repo url
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


function getVersion($package) {
	
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
}


function downloadRepo($package) {
	$repo_url = getRepoUrl($package);
	$zip_file = "cinch/cache/".$package.".zip";
	
	if($repo_url) {
		// Download repo from github
		file_put_contents($zip_file, fopen($repo_url, 'r'));
		
		// Unzip repo to cache folder
		$zip = new ZipArchive;
		if ($zip->open($zip_file) === TRUE) {
		    $zip->extractTo("cinch/cache/");
		    $zip->close();
		    // Delete zip file
		    unlink($zip_file);
		    
		} else {
			$error .= "- Cannot open zip file.\n";
		}
	}
}



function printReloadLink($file_type) {
	global $settings;

	//print_r($settings['files']);

	foreach($settings['files'] as $file) {
		if(is_array($file)) {
			$file_list .= implode(',', $file) . ",";
		} else $file_list .= $file . ",";
	}
	
	$file_list .= "cinch/libraries/live.min.js";
	
	if($file_type == 'css') {
		echo '<link rel="Stylesheet" href="cinch/?files=' . $file_list . '&type=' . $file_type . '&force=1" type="text/css" media="all">';				
	} else {
		echo '<script type="text/javascript" src="cinch/?files=' . $file_list . '&type=' . $file_type . '&force=1"></script>';
	}
	
}



function printCacheLink($cachefile, $file_type) {
	if($file_type == 'css') {
		echo '<link rel="Stylesheet" href="'.$cachefile.'" type="text/css" media="all">';				
	} else {
		echo '<script type="text/javascript" src="'.$cachefile.'"></script>';
	}
}




function printCacheFile($cachefile) {
	global $error;
	
	if(!$handle = fopen($cachefile, "r")) {
		$error .=  "- There was an error trying to open cache file: '".$cachefile."'.\n";
		//exit;
	} else {
		$content = fread($handle, filesize($cachefile));
		fclose($handle);
	}
	
	if($_settings['debug'] && isset($error)) echo "/*\n\nERROR:\n$error\n*/\n\n";
	echo trim($content);	
}




// DELETES ALL CACHE FILES
function clearCache() {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
		if(is_file($file)) unlink($file);
	}
}








?>