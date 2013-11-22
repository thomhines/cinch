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



// DELETE CACHE FILES IF CLEARCACHE IS TRUE
// TO DISABLE REMOTE CLEARING, JUST DELETE/COMMENT THE LINE BELOW
if($_GET['clearcache']) clearCache();


if(!isset($_GET['files'])) exit(); // if no files have been selected, stop here



// PREPARE VARIABLES
$file_array = explode(',', $_GET['files']); 
$cachefile = 'cache/'.md5(implode(",", $_GET)); // build cache filename based on current parameters
$filepath = $_SERVER['SCRIPT_FILENAME']; // set path to site root folder
$filepath = str_replace('cinch/index.php', '', $filepath); // remove reference to cinch folder



// USE CORRECT CONTENT TYPE 
if($_GET['t']=='auto' || !isset($_GET['t'])) { // auto detect content type
	if(substr($file_array[0], -3) == '.js' || substr($file_array[0], -7) == '.coffee' || substr($file_array[0], 0, 1) == '[') $_GET['t'] = 'js';
	else $_GET['t'] = 'css';
} 

if($_GET['t']=='js') { // JS
	header("Content-type: application/x-javascript; charset: UTF-8"); 
	$cachefile .= '.js';
}
else { // otherwise, assume CSS
	header("Content-type: text/css; charset: UTF-8");
	$cachefile .= '.css';
}


// CHECK TO SEE IF CACHE IS OLDER THAN ALL OF THE FILES
if(file_exists($cachefile)) $timestamp = filemtime($cachefile);
else $timestamp = 0;
$new_changes = false;
foreach($file_array as $file) {
	if(substr($file,0,1) == "!") $file = substr($file,1); ; // remove '!'
	if(is_file($filepath.$file)) if(filemtime($filepath.$file) > $timestamp) $new_changes = true;
}



// IF NEW CHANGES HAVE BEEN DETECTED, REBUILD CACHE FILE
if($new_changes || $_GET['force'] || $_GET['clearcache']) {

	$content = '';
	foreach($file_array as $file) { // combine files
		
		// MINIFY FILES
		if($_GET['min'] === true || !isset($_GET['min'])) {
			$compress_file = true;
			require_once('processors/minify.php');
		}
		else $compress_file = false;
		
		if(substr($file,0,1) == "!") { // don't minify file if marked with an '!'
			$compress_file = false;
			if(substr($file,0,1) == "!") $file = substr($file,1); // remove '!' from the front of filename
		}
		
		
		// LOAD AND READ FILE
		
		if(substr($file, 0, 1) == '[') { // LOAD A FILE FROM GOOGLE HOSTED LIBRARIES
			$compress_file = false;
			$temp_content = getGoogleLibrary($file);
/*
			$file = preg_replace("/[\[\]\s]/", "", $file); // remove brackets and spaces
			$library_array = explode('/', $file); 
			
			$google_filenames = array(
				'angularjs' => 'angular.min.js',
				'chrome-frame' => 'CFInstall.min.js',
				'dojo' => 'dojo/dojo.js',
				'ext-core' => 'ext-core.js',
				'jquery' => 'jquery.min.js',
				'jqueryui' => 'jquery-ui.min.js',
				'mootools' => 'mootools-yui-compressed.js',
				'prototype' => 'prototype.js',
				'scriptaculous' => 'scriptaculous.js',
				'swfobject' => 'swfobject.js',
				'webfont' => 'webfont.js',
			);
			
			
			$library_file = 'https://ajax.googleapis.com/ajax/libs/'.$library_array[0].'/'.$library_array[1].'/'.$google_filenames[$library_array[0]];
*/
			if(!$temp_content) $error .= "'".$file."' is not a valid Google Library file.\n";
		} 
		
		
		else { // LOAD A LOCAL FILE
			if(!is_file($filepath.$file)) $error .= "'".$file."' is not a valid file.\n";
			else {
				if(!$handle = fopen($filepath.$file, "r")) $error .= "There was an error trying to open '".$file."'.\n";
				else {
					$temp_content = "";
					if(!$temp_content = fread($handle, filesize($filepath.$file))) $error .= "There was an error trying to open '".$file."'.\n";
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

			// MINIFY CONTENT
			if($compress_file && $_GET['t']=='js') $temp_content = minifyJS($temp_content);
			elseif($compress_file) $temp_content = minifyCSS($temp_content);

			// FIX LINKS TO EXTERNAL FILES IN CSS
			if($_GET['t'] == 'css') {
				$path = "../".dirname($file)."/"; // trailing slash just in case user didn't add a leading slash to their CSS file path
				$temp_content = preg_replace("/url\([']?((?!http)[^\/][^'\)]*)[']?\)/", "url(".$path."$1)", $temp_content); // if path is absolute, leave it alone. otherwise, relink assets based on path from css file
			}
			
			if($_GET['debug'] && $temp_content) $temp_content = "/* $file */\n".$temp_content;
			if($temp_content) $content .= $temp_content."\n\n";
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





/*----------------------------------------------------------------------*
	SUPPORT FUNCTIONS
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


// RETRIEVE LIBRARY FILE FROM GOOGLE HOSTED LIBRARIES
function getGoogleLibrary($library) {
	$library = preg_replace("/[\[\]\s]/", "", $library); // remove brackets and spaces
	$library_array = explode('/', $library); 
	
	$google_filenames = array(
		'angularjs' => 'angular.min.js',
		'chrome-frame' => 'CFInstall.min.js',
		'dojo' => 'dojo/dojo.js',
		'ext-core' => 'ext-core.js',
		'jquery' => 'jquery.min.js',
		'jqueryui' => 'jquery-ui.min.js',
		'mootools' => 'mootools-yui-compressed.js',
		'prototype' => 'prototype.js',
		'scriptaculous' => 'scriptaculous.js',
		'swfobject' => 'swfobject.js',
		'webfont' => 'webfont.js',
	);
	
	
	$library_file = 'https://ajax.googleapis.com/ajax/libs/'.$library_array[0].'/'.$library_array[1].'/'.$google_filenames[$library_array[0]];
	
	return @file_get_contents($library_file);
}


// DELETES ALL CACHE FILES
function clearCache() {
	$files = glob('cache/*.*s'); // select all .js and .css files
	foreach($files as $file){
		if(is_file($file)) unlink($file);
	}
}

?>