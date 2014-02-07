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




if(file_exists($cachefile)) $timestamp = filemtime($cachefile);
else $timestamp = 0;
$new_changes = false;

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

		// ENABLE/DISABLE FILE MINIFICATION
		$compress_file = $_GET['min'];
		if(substr($file,0,1) == "!") { // don't minify file if marked with an '!'
			$compress_file = false;
			$file = substr($file,1); // remove '!' from the front of filename
		}
		
		// LOAD A REMOTE FILE
		if(strpos($file, 'http://') !== false) {
			$file = preg_replace("/\[(.*)\]/", "$1", $file);
			if(!$temp_content = @file_get_contents($file)) $error .= "'".$file."' is not a valid file.\n";
		}
		
		// LOAD LIBRARY FILE
		elseif(substr($file, 0, 1) == '[') { // LOAD A FILE FROM EXTERNALLY HOSTED LIBRARY
			//$compress_file = false;
			$temp_content = loadExternalLibrary($file);
			if(!$temp_content) $error .= "'".$file."' is not a valid library name.\n";
		}
		
		// ELSE, LOAD A LOCAL FILE
		else {
			if(!is_file($filepath.$file)) $error .= "'".$file."' is not a valid file.\n";
			else {
				if(!$handle = fopen($filepath.$file, "r")) $error .= "There was an error trying to open '".$file."'.\n";
				else {
					$temp_content = "";
					if(filesize($filepath.$file) == 0) $error .= "'".$file."' is an empty file.\n";
					elseif(!$temp_content = fread($handle, filesize($filepath.$file))) $error .= "There was an error trying to open '".$file."'.\n";
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
			
			if($_GET['debug'] && $temp_content) $temp_content = "/* $file */\n".$temp_content."\n\n";
			if($temp_content) $content .= $temp_content;
		}			
	}	
	
	// OUTPUT FILE
	if(!$handle = fopen($cachefile, 'w')) {
		 echo "ERROR: Cannot open cache file.\n";
		 exit;
	}
	if(fwrite($handle, $content) === FALSE) {
		echo "ERROR: Cannot write to cache file.\n";
		exit;
	}
	fclose($handle);

	$timestamp = filemtime($cachefile);
} 



// SET HEADERS TO CACHE FILES PROPERLY IN BROWSER
$gmt_mtime = gmdate('r', $timestamp);
header('ETag: "'.md5($timestamp.$cachefile).'"');
header('Last-Modified: '.$gmt_mtime);
header('Cache-Control: public');



// IF THERE IS A NEW CACHE FILE, SEND NEW CONTENT
if($content) {
	if($_GET['debug'] && isset($error)) echo "/*\n\nERROR:\n$error \n*/\n\n";
	echo $content;
}



// IF FILE IS ALREADY IN USER'S CACHE, SEND 304 NOT MODIFIED HEADER
elseif ($_SERVER['HTTP_IF_MODIFIED_SINCE'] == $gmt_mtime || str_replace('"', '', stripslashes($_SERVER['HTTP_IF_NONE_MATCH'])) == md5($timestamp.$cachefile)) {
	header('HTTP/1.1 304 Not Modified');		
	exit();
}



// OTHERWISE, LOAD CACHED FILE AND SEND TO USER
else {
	if(!$handle = fopen($cachefile, "r")) {
		echo "/*\nERROR: There was an error trying to open '".$cachefile."'.\n*/\n\n";
		exit;
	}
	$content = fread($handle, filesize($cachefile));
	fclose($handle);
	
	if($_GET['debug'] && isset($error)) echo "/*\nERROR:\n$error \n*/\n\n";
	echo $content;	
}

ob_end_flush();



/*----------------------------------------------------------------------*
	FUNCTIONS
*----------------------------------------------------------------------*/

// PARSES SASS/SCSS
function convertSASS($src, $file) {
	
	$path = "../".dirname($file)."/";
	// CHECK TO SEE IF FILE USES OLD SCHOOL INDENTATION STYLE
	if(strpos($src, "{") === false) {
		// IF SO, CONVERT IT TO SCSS
		require_once('processors/sass.php');
		$src = sassToScss($src);
	} 
	
	require_once('processors/scss/scss.inc.php');
	$scss = new scssc();
	$scss->addImportPath($path);
	try {
		$css = $scss->compile($src);	
	} catch(Exception $e) {
		if($_GET['debug']) echo "/*\nERROR: $file\n" . $e->getMessage() . " \n*/\n\n";
	}
	return $css;
}

// PARSES LESS
function convertLESS($src, $file) {
	
	$path = "../".dirname($file)."/";
	require_once('processors/less/lessc.inc.php');
	$less = new lessc();
	$less->addImportDir($path);
	try {
		$css = $less->compile($src);	
	} catch(Exception $e) {
		if($_GET['debug']) echo "/*\nERROR: $file\n" . $e->getMessage() . " \n*/\n\n";
	}
	return $css;
}

// PARSES COFFEESCRIPT
function convertCoffee($src) {	
	require_once('processors/coffeescript/Init.php');
	CoffeeScript\Init::load();
	return CoffeeScript\Compiler::compile($src);
}


// RETRIEVE LIBRARY FILE FROM EXTERNALLY HOSTED LIBRARIES
function loadExternalLibrary($file) {
	global $libraries, $error;
	
	preg_match("/\[([^\/]*)\/?(.*)?\]/", $file, $file_array);
	$library_name = strtolower($file_array[1]);
	
	$library = $libraries[$library_name];
	$version = $file_array[2] ? $file_array[2] : $library['ver'];
		
	$library_url =  str_replace('{version}', $version, $library['url']);
	$local_library_url = "libraries/$library_name/{$library[1]}/".basename($library_url);
	
	// IF SERVER CAN'T REMOTELY ACCESS FILES, OR NO VERSION NUMBER IS SELECTED, USE LOCAL VERSION
	if(ini_get("allow_url_fopen") == 0) {
		if(ini_get("allow_url_fopen") == 0) $error .= "Your server does not allow for access to remote libraries. You may need to load the file onto the server manually.\n";
		$library_url = $local_library_url; // reset url to local version
	}
	
	// GET FILE CONTENTS	
	$file_contents = @file_get_contents($library_url);
	
	// IF FILE CONTENTS FAILED TO LOAD, GET LOCAL VERSION
	if(!$file_contents) {
		$error .= "The external library '$library_name' could not be loaded. A local version was used instead.\n";
		$file_contents = @file_get_contents($local_library_url);
	}
	
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