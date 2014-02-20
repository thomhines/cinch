<?php

/*----------------------------------------------------------------------*

	Cinch
	https://github.com/thomhines/cinch
	
	A simple, streamlined plugin to minimize and cache JS/CSS files

	Copyright 2013, Thom Hines
	MIT License
	
*----------------------------------------------------------------------*/

// GZIP CONTENT WHEN AVAILABLE
if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) ob_start("ob_gzhandler"); 
else ob_start(); 

if(!isset($_GET['files'])) exit(); // if no files have been selected, stop here


// SET DEFAULTS
foreach($_GET as $key => $value) {
	if($_GET[$key] == 'false') $_GET[$key] = false;
	elseif($_GET[$key] == 'true') $_GET[$key] = true;
}
$_GET['type'] = isset($_GET['type']) ? $_GET['type'] : 'auto';
$_GET['min'] = isset($_GET['min']) ? $_GET['min'] : true;
$_GET['force'] = isset($_GET['force']) ? $_GET['force'] : false;
$_GET['debug'] = isset($_GET['debug']) ? $_GET['debug'] : true;




// PREPARE VARIABLES
$file_array = explode(',', $_GET['files']); 
$cachefile = 'cache/'.md5(implode(",", $_GET)); // build cache filename based on current parameters
$filepath = $_SERVER['SCRIPT_FILENAME']; // set path to site root folder
$filepath = str_replace('cinch/index.php', '', $filepath); // remove reference to cinch folder


// LOAD LIST OF LIBRARIES
$libraries = json_decode(file_get_contents('libraries.json'), true);



// USE CORRECT CONTENT TYPE 
if($_GET['type']=='auto') { // auto detect content type
	if(substr($file_array[0], 0, 1) == '[') {
		preg_match("/\[([^\/]*)\/?(.*)?\]/", $file_array[0], $library_array);
		$library_info = $libraries[$library_array[1]];
	}
	
	if(substr($file_array[0], -3) == '.js' || substr($file_array[0], -7) == '.coffee' || $library_info['type'] == 'js') $_GET['type'] = 'js';
	else $_GET['type'] = 'css';
} 

if($_GET['type']=='js') { // JS
	header("Content-type: application/x-javascript; charset: UTF-8"); 
	$cachefile .= '.js';
}
else { // otherwise, assume CSS
	header("Content-type: text/css; charset: UTF-8");
	$cachefile .= '.css';
}

$new_changes = false;

if(file_exists($cachefile)) $timestamp = filemtime($cachefile);
else {
	$timestamp = 0;
	$new_changes = true;
}

// CLEAR CACHE IF CACHE IS OLDER THAN ONE MONTH
$one_month_ago = strtotime("-1 month");
if($timestamp != 0 && $timestamp < $one_month_ago) {
	clearCache();
	$new_changes = true;
} 

// CHECK TO SEE IF CACHE IS OLDER THAN ALL OF THE FILES
else {
	foreach($file_array as $file) {
		if(substr($file,0,1) == "!") $file = substr($file,1); // remove '!'
		if(is_file($filepath.$file)) if(filemtime($filepath.$file) > $timestamp) $new_changes = true;
	}
}


// IF NEW CHANGES HAVE BEEN DETECTED, REBUILD CACHE FILE
if($new_changes || $_GET['force']) {


	$content = '';
	foreach($file_array as $file) { // combine files

		$temp_content = '';
		$library_info = '';
		
		// ENABLE/DISABLE FILE MINIFICATION
		$compress_file = $_GET['min'];
		if(substr($file,0,1) == "!") { // don't minify file if marked with an '!'
			$compress_file = false;
			$file = substr($file,1); // remove '!' from the front of filename
		}
		
		// LOAD A REMOTE FILE
		if(strpos($file, 'http://') !== false) {
			$file = preg_replace("/\[(.*)\]/", "$1", $file);
			if(!$temp_content = @file_get_contents($file)) $error .= "- '".$file."' is not a valid file.\n";
		}
		
		// LOAD LIBRARY FILE
		elseif(substr($file, 0, 1) == '[') { // LOAD A FILE FROM EXTERNALLY HOSTED LIBRARY
			$compress_file = false;
			$library_info = loadLibraryInfo($file);
			$file = $library_info['library_url'];
			$temp_content = loadExternalLibrary($file);
		}
		
		// ELSE, LOAD A LOCAL FILE
		else {
			if(!is_file($filepath.$file)) $error .= "- '".$file."' is not a valid file.\n";
			else {
				if(!$handle = fopen($filepath.$file, "r")) $error .= "- There was an error trying to open '".$file."'.\n";
				else {
					$temp_content = "";
					if(filesize($filepath.$file) == 0) $error .= "- '".$file."' is an empty file.\n";
					elseif(!$temp_content = fread($handle, filesize($filepath.$file))) $error .= "- There was an error trying to open '".$file."'.\n";
					fclose($handle);
				}
			}
		}

		// NO ERRORS, LOAD FILE
		if($temp_content) {
			
			// IF SASS, PROCESS AND CONVERT TO CSS
			if(substr($file, -5) == '.scss' || substr($file, -5) == '.sass') $temp_content = convertSASS($temp_content, $file);
			
			// IF LESS, PROCESS AND CONVERT TO CSS
			if(substr($file, -5) == '.less') $temp_content = convertLESS($temp_content, $file);

			// IF COFFEESCRIPT, PROCESS AND CONVERT TO JS
			if(substr($file, -7) == '.coffee') $temp_content = convertCoffee($temp_content);

			// MINIFY/PACK CONTENT		
			if($compress_file && $_GET['type']=='js'){	// Javascript		

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
			}

			// FIX LINKS TO EXTERNAL FILES IN CSS
			if($_GET['type'] == 'css') {
				$path = "../".dirname($file)."/"; // trailing slash just in case user didn't add a leading slash to their CSS file path
				$temp_content = preg_replace("/url\s?\(['\"]?((?!http|\/)[^'\"\)]*)['\"]?\)/", "url(".$path."$1)", $temp_content); // if path is absolute, leave it alone. otherwise, relink assets based on path from css file
			}
			
			if($_GET['debug'] && $library_info) $temp_content = "/* ".$library_info['library_name']." */\n".$temp_content."\n\n";
			elseif($_GET['debug'] && $temp_content) $temp_content = "/* $file */\n".$temp_content."\n\n";
			if($temp_content) $content .= $temp_content;
		}			
	}	
	
	// OUTPUT FILE
	if($handle = fopen($cachefile, 'w')) {
		if(fwrite($handle, $content) === FALSE) {
			$error .= "- Cannot write to cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
		}
	}
	else $error .= "- Cannot open cache file: '".$cachefile."'. Make sure the permissions on your server are set to allow write access to the 'cache' folder.\n";
	fclose($handle);

	$timestamp = filemtime($cachefile);
	if(!$timestamp) $timestamp = date();
} 

// SET HEADERS TO CACHE FILES PROPERLY IN BROWSER
$gmt_mtime = gmdate('r', $timestamp);
session_cache_limiter(‘public’);
header('ETag: "'.md5($timestamp.$cachefile).'"');
header('Last-Modified: '.$gmt_mtime);
header('Cache-Control: public');



// IF THERE IS A NEW CACHE FILE, SEND NEW CONTENT
if($content) printOutputContent($content);


// IF FILE IS ALREADY IN USER'S CACHE, SEND 304 NOT MODIFIED HEADER
elseif ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$cachefile)) {
	header('HTTP/1.1 304 Not Modified');		
	exit();
}



// OTHERWISE, LOAD CACHED FILE AND SEND TO USER
else {
	if(!$handle = fopen($cachefile, "r")) {
		$error .=  "- There was an error trying to open cache file: '".$cachefile."'.\n";
		//exit;
	} else {
		$content = fread($handle, filesize($cachefile));
		fclose($handle);
	}
	
	printOutputContent($content);
}

ob_end_flush();



/*----------------------------------------------------------------------*
	FUNCTIONS
*----------------------------------------------------------------------*/

function printOutputContent($content) {
	global $error;
	if($_GET['debug'] && isset($error)) echo "/*\n\nERROR:\n$error\n*/\n\n";
	echo trim($content);	
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
		if($_GET['debug']) $error .= "- Sass error in $file: " . $e->getMessage() . ".\n";
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
		if($_GET['debug']) $error .= "- Less error in $file: " . $e->getMessage() . ".\n";
		return;
	}
	return $css;
}

// PARSES COFFEESCRIPT
function convertCoffee($src) {
	global $error;
	if (version_compare(PHP_VERSION, '5.3.0') >= 0) require_once('processors/coffeescript/Init.php');
	else $error .=  "- The coffeescript processor requires PHP 5.3 or greater, which you don't have. All .coffee files are currently being skipped.\n";
}

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

// DELETES ALL CACHE FILES
function clearCache() {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
		if(is_file($file)) unlink($file);
	}
}

?>